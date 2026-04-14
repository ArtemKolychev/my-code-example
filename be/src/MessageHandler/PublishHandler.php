<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\ArticleSubmission;
use App\Logging\TraceContext;
use App\Message\ClickerCommand;
use App\Message\PublishMessage;
use App\Repository\ArticleRepository;
use App\Repository\ArticleSubmissionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class PublishHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private ArticleRepository $articleRepository,
        private ArticleSubmissionRepository $submissionRepository,
        private MessageBusInterface $messageBus,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private string $appSecret,
        private string $internalAppUrl,
    ) {
    }

    public function __invoke(PublishMessage $message): void
    {
        $platform = $message->getPlatform();

        $article = $this->articleRepository->find($message->getArticleId());
        if (!$article) {
            throw new RuntimeException("Article id:[{$message->getArticleId()}] not found");
        }

        $user = $this->userRepository->find($message->getUserId());
        if (!$user) {
            throw new RuntimeException("User id:[{$message->getUserId()}] not found");
        }

        $credentialService = $platform->credentialService();
        $serviceCredential = $user->getCredentialForService($credentialService);
        if (!$serviceCredential && $platform->requiresCredential()) {
            throw new RuntimeException("No {$credentialService} credentials for user id:[{$message->getUserId()}]");
        }

        $images = [];
        foreach ($article->getImages() as $image) {
            $link = $image->getLink();
            if ($link) {
                $images[] = [
                    'name' => basename($link),
                    'mimetype' => 'image/jpeg',
                    'url' => rtrim($this->internalAppUrl, '/').$link,
                ];
            }
        }

        $jobId = uniqid($platform->value.'_', true);

        $submission = $this->submissionRepository->findByArticleAndPlatform($article, $platform);
        if ($submission) {
            $submission->setJobId($jobId);
            $submission->setStatus('pending');
        } else {
            $submission = new ArticleSubmission();
            $submission->setArticle($article);
            $submission->setPlatform($platform);
            $submission->setJobId($jobId);
            $submission->setStatus('pending');
            $this->entityManager->persist($submission);
        }
        $this->entityManager->flush();

        $this->messageBus->dispatch(new ClickerCommand('action.publish', [
            'jobId' => $jobId,
            'articleId' => $article->getId(),
            'userId' => $user->getId(),
            'platform' => $platform->value,
            'article' => [
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'description' => $article->getDescription(),
                'price' => $article->getPrice(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'phone' => $user->getPhone(),
                'address' => $user->getAddress(),
                'zip' => $user->getZip(),
                'images' => $images,
                'condition' => $article->condition?->label(),
                'meta' => $article->getMeta(),
            ],
            'credential' => [
                'login' => $serviceCredential?->getLogin() ?? ($user->getEmail() ?? ''),
                'password' => $serviceCredential?->getDecryptedPassword($this->appSecret) ?? '',
            ],
        ]));

        TraceContext::setJobId($jobId);
        $this->logger->info('Publish command sent to clicker via AMQP', [
            'jobId' => $jobId,
            'articleId' => $article->getId(),
            'platform' => $platform->value,
        ]);
    }
}
