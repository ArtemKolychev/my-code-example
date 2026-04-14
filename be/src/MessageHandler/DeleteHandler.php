<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\Platform;
use App\Logging\TraceContext;
use App\Message\ClickerCommand;
use App\Message\DeleteMessage;
use App\Repository\ArticleRepository;
use App\Repository\ArticleSubmissionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class DeleteHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private ArticleRepository $articleRepository,
        private ArticleSubmissionRepository $submissionRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        private string $appSecret,
    ) {
    }

    public function __invoke(DeleteMessage $message): void
    {
        $article = $this->articleRepository->find($message->getArticleId());
        if (!$article) {
            throw new RuntimeException("Article id:[{$message->getArticleId()}] not found");
        }

        $adUrl = $message->getArticleUrl();

        if (!$adUrl) {
            throw new RuntimeException("Article id:[{$message->getArticleId()}] has no articleUrl — cannot delete");
        }

        // Detect the real platform from the URL (article.platform may be stale for old records)
        $platform = $this->detectPlatformFromUrl($adUrl) ?? $message->getPlatform();

        $user = $this->userRepository->find($message->getUserId());
        if (!$user) {
            throw new RuntimeException("User id:[{$message->getUserId()}] not found");
        }

        $credentialService = $platform->credentialService();
        $serviceCredential = $user->getCredentialForService($credentialService);
        if (!$serviceCredential) {
            throw new RuntimeException("No {$credentialService} credentials for user id:[{$message->getUserId()}]");
        }

        $jobId = uniqid($platform->value.'_delete_', true);

        // Update the submission's jobId so ClickerEventHandler can track delete completion
        $submission = $this->submissionRepository->findByArticleAndPlatform($article, $platform);
        if ($submission) {
            $submission->setJobId($jobId);
            $submission->setStatus('deleting');
            $this->entityManager->flush();
        }

        $this->messageBus->dispatch(new ClickerCommand('action.delete', [
            'jobId' => $jobId,
            'articleId' => $article->getId(),
            'userId' => $message->getUserId(),
            'platform' => $platform->value,
            'articleUrl' => $adUrl,
            'credential' => [
                'login' => $serviceCredential->getLogin(),
                'password' => $serviceCredential->getDecryptedPassword($this->appSecret),
            ],
        ]));

        TraceContext::setJobId($jobId);
        $this->logger->info('Delete command sent to clicker via AMQP', [
            'jobId' => $jobId,
            'articleId' => $article->getId(),
            'platform' => $platform->value,
        ]);
    }

    private function detectPlatformFromUrl(string $url): ?Platform
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';

        return match (true) {
            str_contains($host, 'bazos.cz') => Platform::Bazos,
            str_contains($host, 'sbazar.cz') => Platform::Seznam,
            str_contains($host, 'vinted.cz') => Platform::Vinted,
            str_contains($host, 'motoinzerce.cz') => Platform::MotoInzerce,
            default => null,
        };
    }
}
