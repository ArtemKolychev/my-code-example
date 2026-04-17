<?php

declare(strict_types=1);

namespace App\EntryPoint\Service\Handler;

use App\Application\Command\UpdateArticleCommand;
use App\Application\DTO\Payload\ArticleUpdatePayload;
use App\Domain\Entity\Article;
use App\EntryPoint\Form\EditArticleType;
use App\EntryPoint\Service\Result\ArticleEditFormsResult;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ArticleEditFormHandler
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * Builds one named form per article, handles the submitted request, and dispatches
     * UpdateArticleCommand when a valid submission is found.
     *
     * @param Article[] $articles
     */
    public function handle(array $articles, Request $request): ArticleEditFormsResult
    {
        $forms = [];

        foreach ($articles as $article) {
            $payload = ArticleUpdatePayload::fromArticle($article);
            $form = $this->formFactory->createNamed(
                'edit_article_'.$article->getId(),
                EditArticleType::class,
                $payload,
            );
            $form->handleRequest($request);

            /** @var UploadedFile[] $images */
            $images = $form->get('images')->getData() ?? [];

            if ($this->tryDispatchSubmission($form, $article, $images)) {
                return new ArticleEditFormsResult(forms: [], dispatched: true);
            }

            $forms[] = ['form' => $form->createView(), 'article' => $article];
        }

        return new ArticleEditFormsResult(forms: $forms, dispatched: false);
    }

    /**
     * @param FormInterface<ArticleUpdatePayload> $form
     * @param UploadedFile[]                      $images
     */
    private function tryDispatchSubmission(FormInterface $form, Article $article, array $images): bool
    {
        $articleId = $article->getId();

        if (!$form->isSubmitted() || !$form->isValid() || !$articleId) {
            return false;
        }

        /** @var ArticleUpdatePayload $submitted */
        $submitted = $form->getData();
        $payloadId = is_numeric($submitted->id) ? (int) $submitted->id : 0;

        if ($payloadId !== $articleId) {
            return false;
        }

        $this->messageBus->dispatch(new UpdateArticleCommand(
            articleId: $articleId,
            title: $submitted->title,
            description: $submitted->description,
            price: $submitted->price,
            category: $submitted->category,
            condition: $submitted->condition,
            metaFields: $submitted->metaFields,
            images: $images,
        ));

        return true;
    }
}
