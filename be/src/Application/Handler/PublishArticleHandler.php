<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\ClickerCommand;
use App\Application\Command\PublishArticleCommand;
use App\Application\DTO\Clicker\ActionPublishPayload;
use App\Application\DTO\Clicker\ArticleData;
use App\Application\DTO\Clicker\CredentialData;
use App\Application\DTO\Clicker\PublishImageData;
use App\Application\Logging\TraceContext;
use App\Domain\Entity\Article;
use App\Domain\Entity\ArticleSubmission;
use App\Domain\Enum\Platform;
use App\Domain\Exception\ArticleNotFoundException;
use App\Domain\Exception\MissingCredentialException;
use App\Domain\Exception\UserNotFoundException;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Domain\Repository\ArticleSubmissionRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\SubmissionStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(handles: PublishArticleCommand::class)]
final readonly class PublishArticleHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private ArticleRepositoryInterface $articleRepository,
        private ArticleSubmissionRepositoryInterface $articleSubmissionRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        private string $appSecret,
        private string $internalAppUrl,
    ) {
    }

    /** @return list<PublishImageData> */
    private function buildImagesPayload(Article $article): array
    {
        $images = [];
        foreach ($article->getImages() as $image) {
            $link = $image->getLink();
            if ($link) {
                $images[] = new PublishImageData(
                    name: basename($link),
                    mimetype: 'image/jpeg',
                    url: rtrim($this->internalAppUrl, '/').$link,
                );
            }
        }

        return $images;
    }

    private function upsertSubmission(Article $article, Platform $platform, string $jobId): void
    {
        $submission = $this->articleSubmissionRepository->findByArticleAndPlatform($article, $platform);
        if ($submission) {
            $submission->setJobId($jobId);
            $submission->setStatus(SubmissionStatus::Pending);
        } else {
            $submission = new ArticleSubmission();
            $submission->setArticle($article);
            $submission->setPlatform($platform);
            $submission->setJobId($jobId);
            $submission->setStatus(SubmissionStatus::Pending);
        }
        $this->articleSubmissionRepository->save($submission);
    }

    public function __invoke(PublishArticleCommand $message): void
    {
        $platform = $message->getPlatform();

        $article = $this->articleRepository->findById($message->getArticleId());
        if (!$article) {
            throw ArticleNotFoundException::forId($message->getArticleId());
        }

        $user = $this->userRepository->findById($message->getUserId());
        if (!$user) {
            throw UserNotFoundException::forId($message->getUserId());
        }

        $credentialService = $platform->credentialService();
        $serviceCredential = $user->getCredentialForService($credentialService);
        if (!$serviceCredential && $platform->requiresCredential()) {
            throw MissingCredentialException::forUserAndService($message->getUserId(), $credentialService);
        }

        $images = $this->buildImagesPayload($article);

        $jobId = uniqid($platform->value.'_', true);

        $this->upsertSubmission($article, $platform, $jobId);

        $this->messageBus->dispatch(new ClickerCommand('action.publish', new ActionPublishPayload(
            jobId: $jobId,
            articleId: $article->getId(),
            userId: $user->getId(),
            platform: $platform->value,
            article: new ArticleData(
                id: $article->getId(),
                title: $article->getTitle(),
                description: $article->getDescription(),
                price: $article->getPrice() !== null ? (int) $article->getPrice() : null,
                name: $user->getName(),
                email: $user->getEmail(),
                phone: $user->getPhone(),
                address: $user->getAddress(),
                zip: $user->getZip(),
                images: $images,
                condition: $article->condition?->label(),
                meta: $article->getMeta() ?? [],
            ),
            credential: new CredentialData(
                login: $serviceCredential?->getLogin() ?? ($user->getEmail() ?? ''),
                password: $serviceCredential?->getDecryptedPassword($this->appSecret) ?? '',
            ),
        )));

        TraceContext::setJobId($jobId);
        $this->logger->info('Publish command sent to clicker via AMQP', [
            'jobId' => $jobId,
            'articleId' => $article->getId(),
            'platform' => $platform->value,
        ]);
    }
}
