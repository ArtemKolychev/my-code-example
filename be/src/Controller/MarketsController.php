<?php

declare(strict_types=1);

namespace App\Controller;

use App\Adapter\PlatformFieldAggregator;
use App\DTO\Request\EditArticleRequest;
use App\DTO\Response\ArticleResponse;
use App\Entity\Article;
use App\Entity\ArticleSubmission;
use App\Entity\Image;
use App\Entity\ImageBatch;
use App\Entity\User;
use App\Enum\Category;
use App\Enum\Condition;
use App\Enum\Platform;
use App\Form\AddArticleImages;
use App\Form\EditArticleType;
use App\Message\DeleteMessage;
use App\Message\GroupImagesMessage;
use App\Message\PublishMessage;
use App\Message\SuggestPriceMessage;
use App\Registry\CategoryFieldRegistry;
use App\Repository\ArticleSubmissionRepository;
use App\Service\ArticleInputService;
use App\Service\ArticleService;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MarketsController extends AbstractController
{
    #[Route('/market/article/add_images', name: 'app_article_images')]
    #[IsGranted('ROLE_USER')]
    public function addImages(Request $request, ArticleService $articleService): Response
    {
        $form = $this->createForm(AddArticleImages::class);
        $form->handleRequest($request);

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($form->isSubmitted()) {
            /** @var UploadedFile[]|null $images */
            $images = $form->get('images')->getData();

            if ($form->isValid() && !empty($images)) {
                if (!$currentUser->hasTokens()) {
                    $this->addFlash('error', 'Vyčerpali jste všechny AI tokeny. Nelze přidávat další inzeráty.');

                    return $this->render('market/images_loader.html.twig', [
                        'form' => $form->createView(),
                        'tokenBalance' => $currentUser->getTokenBalance(),
                    ]);
                }

                $batch = $articleService->uploadImages($images, $currentUser);

                return $this->redirectToRoute('app_processing', ['batchId' => $batch->getId()]);
            }

            if (empty($images)) {
                $this->addFlash('error', 'Please select at least one image.');
            } elseif (!$form->isValid()) {
                $this->addFlash('error', 'Image upload failed. Please try another file.');
            }
        }

        return $this->render('market/images_loader.html.twig', [
            'form' => $form->createView(),
            'tokenBalance' => $currentUser->getTokenBalance(),
        ]);
    }

    #[Route('/market/processing/{batchId}', name: 'app_processing')]
    #[IsGranted('ROLE_USER')]
    public function processing(int $batchId, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $batch = $entityManager->getRepository(ImageBatch::class)->findOneBy([
            'id' => $batchId,
            'user' => $user,
        ]);

        if (!$batch) {
            $this->addFlash('error', 'Batch not found.');

            return $this->redirectToRoute('app_article_images');
        }

        // If already completed, redirect directly
        if ('completed' === $batch->getStatus() && !empty($batch->getArticleIds())) {
            return $this->redirectToRoute('app_edit_article', [
                'id' => implode('-', $batch->getArticleIds()),
            ]);
        }

        return $this->render('market/processing.html.twig', [
            'batchId' => $batchId,
        ]);
    }

    #[Route('/api/batch/{batchId}/status', name: 'app_batch_status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function batchStatus(int $batchId, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        $batch = $entityManager->getRepository(ImageBatch::class)->findOneBy([
            'id' => $batchId,
            'user' => $user,
        ]);

        if (!$batch) {
            return new JsonResponse(['error' => 'Batch not found'], 404);
        }

        $pending = $batch->pendingInput;
        if ($pending) {
            $toUrl = static fn (string $p) => str_starts_with($p, '/') ? $p : '/'.$p;
            if ('group_conditions' === ($pending['inputType'] ?? '')) {
                foreach ($pending['groups'] as &$group) {
                    $group['imageUrls'] = array_map($toUrl, $group['images'] ?? []);
                }
                unset($group);
            } elseif (!isset($pending['imageUrls'])) {
                $pending['imageUrls'] = array_map($toUrl, $batch->getImagePaths());
            }
        }

        return new JsonResponse([
            'status' => $batch->getStatus(),
            'articleIds' => $batch->getArticleIds(),
            'pendingInput' => $pending,
        ]);
    }

    #[Route('/api/batch/{batchId}/retry', name: 'app_batch_retry', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function retryBatch(int $batchId, EntityManagerInterface $em, MessageBusInterface $messageBus): JsonResponse
    {
        $user = $this->getUser();
        $batch = $em->getRepository(ImageBatch::class)->findOneBy(['id' => $batchId, 'user' => $user]);

        if (!$batch) {
            return new JsonResponse(['error' => 'Batch not found'], 404);
        }

        if ('failed' !== $batch->getStatus()) {
            return new JsonResponse(['error' => 'Batch is not in failed state'], 400);
        }

        $batch->setStatus('processing');
        $batch->setJobId(uniqid('group_', true));
        $em->flush();

        $messageBus->dispatch(new GroupImagesMessage($batch->getId()));

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/batch/{batchId}/input', name: 'app_batch_input', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function submitBatchInput(int $batchId, Request $request, EntityManagerInterface $em, MessageBusInterface $messageBus, ArticleService $articleService): JsonResponse
    {
        $user = $this->getUser();
        $batch = $em->getRepository(ImageBatch::class)->findOneBy(['id' => $batchId, 'user' => $user]);

        if (!$batch) {
            return new JsonResponse(['error' => 'Batch not found'], 404);
        }

        if ('needs_input' !== $batch->getStatus()) {
            return new JsonResponse(['error' => 'Batch is not waiting for input'], 400);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $request->getContent(), true) ?? [];

        $inputType = $batch->pendingInput['inputType'] ?? '';

        // Per-group condition confirmation — create articles now
        if ('group_conditions' === $inputType) {
            /** @var array<int|string, string> $groupConditions */
            $groupConditions = isset($body['groupConditions']) && is_array($body['groupConditions'])
                ? $body['groupConditions']
                : [];
            /** @var array<int|string, array<string, mixed>> $groupFields */
            $groupFields = isset($body['groupFields']) && is_array($body['groupFields'])
                ? $body['groupFields']
                : [];
            $groups = $batch->pendingInput['groups'] ?? [];
            foreach ($groups as &$group) {
                $idx = $group['index'] ?? null;
                if (null !== $idx && isset($groupConditions[(int) $idx])) {
                    $group['condition'] = $groupConditions[(int) $idx];
                }
                if (null !== $idx && isset($groupFields[(int) $idx])) {
                    $group['extracted_fields'] = array_merge(
                        $group['extracted_fields'] ?? [],
                        array_filter($groupFields[(int) $idx], static fn ($v) => '' !== $v && null !== $v)
                    );
                }
            }
            unset($group);

            /** @var User $user */
            $articles = $articleService->createArticlesFromGroups($groups, $user);
            $articleIds = array_map(fn ($a) => $a->getId(), $articles);

            $batch->setArticleIds($articleIds);
            $batch->pendingInput = null;
            $batch->setStatus('completed');
            $em->flush();

            foreach ($articles as $article) {
                $id = $article->getId();
                if ($id) {
                    $messageBus->dispatch(new SuggestPriceMessage($id));
                }
            }

            return new JsonResponse(['success' => true]);
        }

        // Legacy: vehicle identifier code input
        $code = isset($body['code']) ? trim((string) $body['code']) : '';

        if ('' === $code) {
            return new JsonResponse(['error' => 'code is required'], 400);
        }

        $batch->pendingInput = null;
        $batch->setStatus('processing');
        $em->flush();

        $messageBus->dispatch(new GroupImagesMessage($batchId, $code));

        return new JsonResponse(['success' => true]);
    }

    #[Route('/market/articles/edit', name: 'app_list_articles')]
    #[IsGranted('ROLE_USER')]
    public function listArticles(EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $articles = $user->getArticles()->toArray();

        /** @var ArticleSubmissionRepository $submissionRepo */
        $submissionRepo = $entityManager->getRepository(ArticleSubmission::class);
        $submissions = [];
        foreach ($articles as $article) {
            $articleId = $article->getId();
            if ($articleId) {
                foreach ($submissionRepo->findBy(['article' => $article]) as $sub) {
                    $submissions[$articleId][$sub->getPlatform()->value] = $sub;
                }
            }
        }

        return $this->render('market/index.html.twig', [
            'user' => $user,
            'articles' => $articles,
            'submissions' => $submissions,
            'tokenBalance' => $user->getTokenBalance(),
        ]);
    }

    #[Route('/market/article/{id}/edit', name: 'app_edit_article')]
    #[IsGranted('ROLE_USER')]
    public function editArticle(string $id, Request $request, EntityManagerInterface $entityManager, ArticleService $articleService, FormFactoryInterface $formFactory): Response
    {
        $ids = explode('-', $id);
        /** @var User $user */
        $user = $this->getUser();

        $articles = $entityManager->getRepository(Article::class)->findBy(['id' => $ids, 'user' => $user]);
        $forms = [];
        foreach ($articles as $article) {
            $form = $formFactory->createNamed('edit_article_'.$article->getId(), EditArticleType::class, $article);
            $form->handleRequest($request);

            $idData = $form->get('id')->getData();
            $formId = is_numeric($idData) ? (int) $idData : 0;
            if ($form->isSubmitted() && $form->isValid() && $formId === $article->getId()) {
                $articleService->updateArticle($article, new EditArticleRequest(
                    title: is_string($form->get('title')->getData()) ? $form->get('title')->getData() : null,
                    description: is_string($form->get('description')->getData()) ? $form->get('description')->getData() : null,
                    price: is_float($form->get('price')->getData()) || is_int($form->get('price')->getData()) ? $form->get('price')->getData() : null,
                    images: $form->get('images')->getData(),
                ));

                // Save category and condition from form
                $categoryData = $form->get('category')->getData();
                $conditionData = $form->get('condition')->getData();
                if ($categoryData instanceof Category) {
                    $article->setCategory($categoryData);
                }
                if ($conditionData instanceof Condition) {
                    $article->setCondition($conditionData);
                }

                // Save meta fields from dynamic JS inputs
                $metaFieldsJson = $request->request->get('meta_fields');
                if ($metaFieldsJson && is_string($metaFieldsJson)) {
                    $metaFields = json_decode($metaFieldsJson, true);
                    if (is_array($metaFields)) {
                        $currentMeta = $article->getMeta() ?? [];
                        $article->setMeta(array_merge($currentMeta, $metaFields));
                    }
                }

                $entityManager->persist($article);
                $entityManager->flush();

                $this->addFlash('success', 'Article updated successfully!');

                return $this->redirectToRoute('app_edit_article', ['id' => $id]);
            }

            $forms[] = [
                'form' => $form->createView(),
                'article' => $article,
            ];
        }

        /** @var ArticleSubmissionRepository $submissionRepo */
        $submissionRepo = $entityManager->getRepository(ArticleSubmission::class);
        $submissions = [];
        foreach ($articles as $article) {
            $articleId = $article->getId();
            if ($articleId) {
                $submissions[$articleId] = [];
                foreach (Platform::cases() as $platform) {
                    $submission = $submissionRepo->findByArticleAndPlatform($article, $platform);
                    if ($submission) {
                        $submissions[$articleId][$platform->value] = $submission;
                    }
                }
            }
        }

        $availablePlatforms = [];
        foreach (Platform::cases() as $platform) {
            if (Platform::Vinted === $platform) {
                continue;
            }
            if ($user->getCredentialForService($platform->credentialService())) {
                $availablePlatforms[] = $platform;
            }
        }

        // Build category field definitions for JS
        $categoryFields = [];
        foreach (Category::cases() as $cat) {
            $categoryFields[$cat->value] = self::getCategoryRequiredFields($cat);
        }

        return $this->render('market/edit_article.html.twig', [
            'forms' => $forms,
            'submissions' => $submissions,
            'availablePlatforms' => $availablePlatforms,
            'categoryFields' => $categoryFields,
        ]);
    }

    #[Route('/market/article/{ids}/post', name: 'app_post_articles')]
    #[IsGranted('ROLE_USER')]
    public function postArticles(string $ids, Request $request, EntityManagerInterface $entityManager, MessageBusInterface $messageBus, PlatformFieldAggregator $fieldAggregator): Response
    {
        $idsArray = array_map('intval', explode('-', $ids));
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'You must be logged in to post articles.');

            return $this->redirectToRoute('app_login');
        }

        if (!$user->getName() || !$user->getAddress() || !$user->getZip() || !$user->getPhone()) {
            $this->addFlash('profile_incomplete', 'Vyplňte svůj profil');

            return $this->redirectToRoute('app_edit_article', ['id' => $ids]);
        }

        $platformValue = $request->query->getString('platform');
        $platform = Platform::tryFrom($platformValue);
        if (!$platform) {
            $this->addFlash('error', 'Invalid platform specified.');

            return $this->redirectToRoute('app_edit_article', ['id' => $ids]);
        }

        $articles = $entityManager->getRepository(Article::class)
            ->findBy(['id' => $idsArray, 'user' => $user]);

        /** @var ArticleSubmissionRepository $submissionRepo */
        $submissionRepo = $entityManager->getRepository(ArticleSubmission::class);
        $articles = array_filter($articles, function (Article $a) use ($submissionRepo, $platform): bool {
            $existing = $submissionRepo->findByArticleAndPlatform($a, $platform);

            if (!$existing) {
                return true;
            }

            return in_array($existing->getStatus(), ['failed', 'withdrawn']);
        });

        $publishedCount = 0;
        $needsInputCount = 0;

        /** @var Article $article */
        foreach ($articles as $article) {
            $articleId = $article->getId();
            $userId = $user->getId();
            if (!$articleId || !$userId) {
                continue;
            }

            $missingFieldNames = $fieldAggregator->getMissingFieldsForPlatform($article, $platform);
            if (!empty($missingFieldNames)) {
                // Build category-aware field map for correct labels, types, options
                $categoryFieldMap = [];
                $category = $article->getCategory();
                if ($category) {
                    foreach (CategoryFieldRegistry::getFields($category) as $f) {
                        $categoryFieldMap[$f['key']] = $f;
                    }
                }

                $fields = [];
                foreach ($missingFieldNames as $fieldName) {
                    // Skip fields not relevant to this article's category
                    if (!empty($categoryFieldMap) && !isset($categoryFieldMap[$fieldName])) {
                        continue;
                    }
                    $def = $categoryFieldMap[$fieldName] ?? null;
                    if ($def) {
                        $entry = ['label' => $def['label'], 'type' => $def['type']];
                        if (isset($def['options'])) {
                            $entry['options'] = $def['options'];
                        }
                        $fields[$fieldName] = $entry;
                    }
                }

                if (empty($fields)) {
                    $messageBus->dispatch(new PublishMessage($articleId, $userId, $platform));
                    ++$publishedCount;
                    continue;
                }

                $article->setPendingInput([
                    'inputType' => 'meta_fields',
                    'jobId' => $platform->value.'_'.uniqid(),
                    'fields' => $fields,
                    'inputPrompt' => 'Vyplňte povinné údaje pro '.$platform->name,
                ]);
                ++$needsInputCount;
            } else {
                $messageBus->dispatch(new PublishMessage($articleId, $userId, $platform));
                ++$publishedCount;
            }
        }

        $entityManager->flush();

        if ($needsInputCount > 0) {
            $this->addFlash('warning', 'Některé inzeráty vyžadují doplnění údajů před publikováním.');
        }
        if ($publishedCount > 0) {
            $this->addFlash('success', 'Inzeráty byly přidány do fronty k publikování.');
        }
        if (0 === $publishedCount && 0 === $needsInputCount) {
            $this->addFlash('error', 'No articles found for publishing. Maybe it was already published?');
        }

        return $this->redirectToRoute('app_edit_article', [
            'id' => $ids,
        ]);
    }

    #[Route('/market/article/{id}/withdraw', name: 'app_withdraw_article', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function withdrawArticle(int $id, Request $request, EntityManagerInterface $entityManager, MessageBusInterface $messageBus): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], 401);
        }

        if (!$this->isCsrfTokenValid('withdraw_article', (string) $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $article = $entityManager->getRepository(Article::class)->findOneBy([
            'id' => $id,
            'user' => $user,
        ]);

        if (!$article) {
            return new JsonResponse(['error' => 'Inzerát nebyl nalezen'], 404);
        }

        $userId = $user->getId();
        $articleId = $article->getId();

        if (!$userId || !$articleId) {
            return new JsonResponse(['error' => 'Invalid user or article'], 500);
        }

        /** @var ArticleSubmissionRepository $submissionRepo */
        $submissionRepo = $entityManager->getRepository(ArticleSubmission::class);
        $publishedSubmissions = $submissionRepo->findPublishedByArticle($article);

        if (empty($publishedSubmissions)) {
            return new JsonResponse(['error' => 'Inzerát není publikován na žádné platformě'], 400);
        }

        $dispatched = 0;
        foreach ($publishedSubmissions as $submission) {
            $data = $submission->getResultData();
            $adUrl = $data['articleUrl'] ?? $data['adUrl'] ?? null;
            if (!$adUrl) {
                continue;
            }
            $messageBus->dispatch(new DeleteMessage($articleId, $userId, $submission->getPlatform(), $adUrl));
            ++$dispatched;
        }

        if (0 === $dispatched) {
            return new JsonResponse(['error' => 'Inzerát nemá URL na žádné platformě'], 400);
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/market/article/{id}/delete', name: 'app_delete_article', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function deleteArticle(int $id, EntityManagerInterface $entityManager, ArticleService $articleService): Response
    {
        $user = $this->getUser();

        $article = $entityManager->getRepository(Article::class)->findOneBy([
            'id' => $id,
            'user' => $user,
        ]);

        if (!$article) {
            $this->addFlash('error', 'Inzerát nebyl nalezen.');

            return $this->redirectToRoute('app_list_articles');
        }

        $articleService->deleteArticle($article);
        $this->addFlash('success', 'Inzerát byl smazán.');

        return $this->redirectToRoute('app_list_articles');
    }

    #[Route('/market/article/{id}', name: 'app_get_article_by_id', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getArticleById(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], 401);
        }

        $article = $entityManager->getRepository(Article::class)->findOneBy([
            'id' => $id,
            'user' => $user,
        ]);

        if (!$article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }

        /** @var ArticleSubmissionRepository $submissionRepo */
        $submissionRepo = $entityManager->getRepository(ArticleSubmission::class);

        return new JsonResponse(ArticleResponse::fromEntity($article, $submissionRepo->findBy(['article' => $article]))->toArray());
    }

    #[Route('/market/article/{id}/input', name: 'app_article_input', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function submitArticleInput(int $id, Request $request, EntityManagerInterface $em, ArticleInputService $inputService): JsonResponse
    {
        $user = $this->getUser();

        $article = $em->getRepository(Article::class)->findOneBy([
            'id' => $id,
            'user' => $user,
        ]);

        if (!$article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }

        $pendingInput = $article->getPendingInput();
        if (!$pendingInput || empty($pendingInput['jobId'])) {
            return new JsonResponse(['error' => 'No pending input for this article'], 400);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $request->getContent(), true) ?? [];
        $inputType = isset($pendingInput['inputType']) ? (string) $pendingInput['inputType'] : '';

        if ('meta_fields' === $inputType) {
            /** @var array<string, mixed> $fields */
            $fields = isset($body['fields']) && is_array($body['fields']) ? $body['fields'] : [];

            /** @var User $currentUser */
            $currentUser = $user;
            $result = $inputService->handleMetaFieldsInput($article, $currentUser, $fields);

            if (!$result['success']) {
                return new JsonResponse(['error' => $result['error']], 400);
            }

            return new JsonResponse(['success' => true]);
        }

        $code = isset($body['code']) ? (string) $body['code'] : '';

        if ('' === $code) {
            return new JsonResponse(['error' => 'Code is required'], 400);
        }

        if ('vin_or_spz' === $inputType) {
            $inputService->handleVinOrSpzInput($article, $id, $code);

            return new JsonResponse(['success' => true]);
        }

        $inputService->handleCodeInput($article, (string) $pendingInput['jobId'], $code);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/market/image/{id}/rotate', name: 'app_rotate_image', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function rotateImage(int $id, EntityManagerInterface $entityManager, ImageService $imageService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], 401);
        }

        $image = $entityManager->getRepository(Image::class)->findOneBy(['id' => $id]);

        if (!$image) {
            return new JsonResponse(['error' => 'Image not found'], 404);
        }

        $article = $image->getArticle();
        if (!$article || $article->getUser() !== $user) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        try {
            /** @var string $dir */
            $dir = $this->getParameter('public_directory');
            $absolutePath = $dir.'/'.$image->getLink();
            $imageService->rotateImage($absolutePath);

            return new JsonResponse(['success' => true]);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'Failed to rotate image'], 500);
        }
    }

    #[Route('/market/image/{id}/delete', name: 'app_delete_image', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteImage(int $id, EntityManagerInterface $entityManager, ArticleService $articleService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], 401);
        }

        $image = $entityManager->getRepository(Image::class)->findOneBy(['id' => $id]);

        if (!$image) {
            return new JsonResponse(['error' => 'Image not found'], 404);
        }

        $article = $image->getArticle();
        if (!$article || $article->getUser() !== $user) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        try {
            $articleService->deleteImage($image);

            return new JsonResponse(['success' => 'Image deleted successfully']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'Failed to delete image'], 500);
        }
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, required: bool, options?: array<string, string>}>
     */
    private static function getCategoryRequiredFields(Category $category): array
    {
        return CategoryFieldRegistry::getFields($category);
    }
}
