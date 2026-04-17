<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\ClickerCommand;
use App\Application\Command\DeleteArticleCommand;
use App\Application\DTO\Clicker\ActionDeletePayload;
use App\Application\DTO\Clicker\CredentialData;
use App\Application\Logging\TraceContext;
use App\Domain\Enum\Platform;
use App\Domain\Exception\ArticleMissingUrlException;
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

#[AsMessageHandler(handles: DeleteArticleCommand::class)]
final readonly class DeleteArticleHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private ArticleRepositoryInterface $articleRepository,
        private ArticleSubmissionRepositoryInterface $articleSubmissionRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        private string $appSecret,
    ) {
    }

    private function detectPlatformFromUrl(string $url): ?Platform
    {
        $host = (string) (parse_url($url, PHP_URL_HOST) ?? '');

        return match (true) {
            str_contains($host, 'bazos.cz') => Platform::Bazos,
            str_contains($host, 'sbazar.cz') => Platform::Seznam,
            str_contains($host, 'vinted.cz') => Platform::Vinted,
            str_contains($host, 'motoinzerce.cz') => Platform::MotoInzerce,
            default => null,
        };
    }

    public function __invoke(DeleteArticleCommand $message): void
    {
        $article = $this->articleRepository->findById($message->getArticleId());
        if (!$article) {
            throw ArticleNotFoundException::forId($message->getArticleId());
        }

        $adUrl = $message->getArticleUrl();

        if (!$adUrl) {
            throw ArticleMissingUrlException::forId($message->getArticleId());
        }

        // Detect the real platform from the URL (article.platform may be stale for old records)
        $platform = $this->detectPlatformFromUrl($adUrl) ?? $message->getPlatform();

        $user = $this->userRepository->findById($message->getUserId());
        if (!$user) {
            throw UserNotFoundException::forId($message->getUserId());
        }

        $credentialService = $platform->credentialService();
        $serviceCredential = $user->getCredentialForService($credentialService);
        if (!$serviceCredential) {
            throw MissingCredentialException::forUserAndService($message->getUserId(), $credentialService);
        }

        $jobId = uniqid($platform->value.'_delete_', true);

        // Update the submission's jobId so ClickerEventHandler can track delete completion
        $submission = $this->articleSubmissionRepository->findByArticleAndPlatform($article, $platform);
        if ($submission) {
            $submission->setJobId($jobId);
            $submission->setStatus(SubmissionStatus::Deleting);
            $this->articleSubmissionRepository->save($submission);
        }

        $this->messageBus->dispatch(new ClickerCommand('action.delete', new ActionDeletePayload(
            jobId: $jobId,
            articleId: $article->getId(),
            userId: $message->getUserId(),
            platform: $platform->value,
            articleUrl: $adUrl,
            credential: new CredentialData(
                login: $serviceCredential->getLogin() ?? '',
                password: $serviceCredential->getDecryptedPassword($this->appSecret) ?? '',
            ),
        )));

        TraceContext::setJobId($jobId);
        $this->logger->info('Delete command sent to clicker via AMQP', [
            'jobId' => $jobId,
            'articleId' => $article->getId(),
            'platform' => $platform->value,
        ]);
    }
}
