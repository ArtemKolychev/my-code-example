<?php

declare(strict_types=1);

namespace App\EntryPoint\Action;

use App\Application\DTO\Request\ArticleInputRequest;
use App\Application\Service\ArticleInputService;
use App\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/market/article/{id}/input', name: 'app_article_input', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
final class SubmitArticleInputAction extends AbstractController
{
    public function __construct(
        private readonly ArticleInputService $articleInputService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function __invoke(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $article = $this->articleInputService->findArticleByIdForUser($id, $user);

        if (!$article) {
            return new JsonResponse(['error' => 'Article not found'], Response::HTTP_NOT_FOUND);
        }

        $pendingInput = $article->getPendingInput();
        if (!$pendingInput || empty($pendingInput['jobId'])) {
            return new JsonResponse(['error' => 'No pending input for this article'], Response::HTTP_BAD_REQUEST);
        }

        /** @var array{inputType?: string, jobId: string} $pendingInput */
        $inputType = $pendingInput['inputType'] ?? '';
        $dto = ArticleInputRequest::fromJsonBody((string) $request->getContent(), $inputType);

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return new JsonResponse(['error' => (string) $errors->get(0)->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        if ('meta_fields' === $dto->inputType) {
            /** @var User $currentUser */
            $currentUser = $user;
            $result = $this->articleInputService->handleMetaFieldsInput($article, $currentUser, $dto->fields);

            if (!$result['success']) {
                return new JsonResponse(['error' => $result['error'] ?? 'Unknown error'], Response::HTTP_BAD_REQUEST);
            }

            return new JsonResponse(['success' => true]);
        }

        if ('vin_or_spz' === $dto->inputType) {
            $this->articleInputService->handleVinOrSpzInput($article, $id, $dto->code);

            return new JsonResponse(['success' => true]);
        }

        $this->articleInputService->handleCodeInput($article, $pendingInput['jobId'], $dto->code);

        return new JsonResponse(['success' => true]);
    }
}
