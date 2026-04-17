<?php

declare(strict_types=1);

namespace App\Tests\Unit\Handler;

use App\Application\Command\ClickerCommand;
use App\Application\Command\PublishArticleCommand;
use App\Application\Handler\PublishArticleHandler;
use App\Domain\Entity\ArticleSubmission;
use App\Domain\Enum\Platform;
use App\Domain\Exception\ArticleNotFoundException;
use App\Domain\Exception\MissingCredentialException;
use App\Domain\Exception\UserNotFoundException;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Domain\Repository\ArticleSubmissionRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\SubmissionStatus;
use App\Tests\Shared\Mother\ArticleMother;
use App\Tests\Shared\Mother\ArticleSubmissionMother;
use App\Tests\Shared\Mother\CredentialMother;
use App\Tests\Shared\Mother\ImageMother;
use App\Tests\Shared\Mother\UserMother;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class PublishArticleHandlerTest extends TestCase
{
    private ArticleRepositoryInterface&MockObject $articleRepository;
    private UserRepositoryInterface&MockObject $userRepository;
    private ArticleSubmissionRepositoryInterface&MockObject $submissionRepository;
    private MessageBusInterface&MockObject $messageBus;
    private LoggerInterface&MockObject $logger;

    private PublishArticleHandler $handler;

    // -------------------------------------------------------------------------
    // Exception paths
    // -------------------------------------------------------------------------

    public function testThrowsArticleNotFoundExceptionWhenArticleMissing(): void
    {
        $this->articleRepository->method('findById')->willReturn(null);

        $this->expectException(ArticleNotFoundException::class);
        $this->expectExceptionMessage('Article id:[99] not found');

        ($this->handler)(new PublishArticleCommand(99, 1));
    }

    public function testThrowsUserNotFoundExceptionWhenUserMissing(): void
    {
        $this->articleRepository->method('findById')->willReturn(ArticleMother::any());
        $this->userRepository->method('findById')->willReturn(null);

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User id:[55] not found');

        ($this->handler)(new PublishArticleCommand(1, 55));
    }

    public function testThrowsMissingCredentialExceptionWhenPlatformRequiresCredential(): void
    {
        $this->articleRepository->method('findById')->willReturn(ArticleMother::any());
        $this->userRepository->method('findById')->willReturn(UserMother::withoutCredential());

        $this->expectException(MissingCredentialException::class);

        ($this->handler)(new PublishArticleCommand(1, 1, Platform::Seznam));
    }

    // -------------------------------------------------------------------------
    // Happy paths
    // -------------------------------------------------------------------------

    public function testCreatesNewSubmissionAndDispatchesCommandWhenNoExistingSubmission(): void
    {
        $articleId = random_int(1, 1000);
        $userId = random_int(1, 1000);

        $this->articleRepository->method('findById')->willReturn(ArticleMother::withId($articleId));
        $this->userRepository->method('findById')->willReturn(UserMother::withCredential());
        $this->submissionRepository->method('findByArticleAndPlatform')->willReturn(null);

        $savedSubmission = null;
        $this->submissionRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(ArticleSubmission::class))
            ->willReturnCallback(static function (ArticleSubmission $s) use (&$savedSubmission): void {
                $savedSubmission = $s;
            });

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new stdClass()));

        ($this->handler)(new PublishArticleCommand($articleId, $userId, Platform::Seznam));

        $this->assertInstanceOf(ArticleSubmission::class, $savedSubmission);
        $this->assertSame(SubmissionStatus::Pending, $savedSubmission->getStatus());
        $this->assertSame(Platform::Seznam, $savedSubmission->getPlatform());
    }

    public function testReusesExistingSubmissionAndResetsStatusToPending(): void
    {
        $articleId = random_int(1, 1000);
        $userId = random_int(1, 1000);
        $submission = ArticleSubmissionMother::failed(Platform::Seznam, 'old-job-id');

        $this->articleRepository->method('findById')->willReturn(ArticleMother::withId($articleId));
        $this->userRepository->method('findById')->willReturn(UserMother::withCredential());
        $this->submissionRepository->method('findByArticleAndPlatform')->willReturn($submission);
        $this->submissionRepository->expects($this->once())->method('save')->with($submission);
        $this->messageBus->method('dispatch')->willReturn(new Envelope(new stdClass()));

        ($this->handler)(new PublishArticleCommand($articleId, $userId, Platform::Seznam));

        $this->assertSame(SubmissionStatus::Pending, $submission->getStatus());
        $this->assertNotSame('old-job-id', $submission->getJobId());
    }

    public function testArticleImagesAreMappedIntoClickerPayload(): void
    {
        $article = ArticleMother::withImages(
            ImageMother::withLink('/uploads/images/10/photo.jpg'),
        );

        $this->articleRepository->method('findById')->willReturn($article);
        $this->userRepository->method('findById')->willReturn(UserMother::withCredential());
        $this->submissionRepository->method('findByArticleAndPlatform')->willReturn(null);
        $this->submissionRepository->method('save');

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (ClickerCommand $cmd): bool {
                /** @var array<string, mixed> $payload */
                $payload = $cmd->getPayload()->toArray();
                /** @var array<string, mixed> $article */
                $article = $payload['article'];
                /** @var array<int, array{name: string, url: string}> $images */
                $images = $article['images'];

                return 1 === count($images)
                    && 'photo.jpg' === $images[0]['name']
                    && str_ends_with((string) $images[0]['url'], '/uploads/images/10/photo.jpg');
            }))
            ->willReturn(new Envelope(new stdClass()));

        ($this->handler)(new PublishArticleCommand($article->getId() ?? 1, 1, Platform::Seznam));
    }

    public function testImagesWithNullLinkAreExcludedFromPayload(): void
    {
        $article = ArticleMother::withImages(ImageMother::withoutLink());

        $this->articleRepository->method('findById')->willReturn($article);
        $this->userRepository->method('findById')->willReturn(UserMother::withCredential());
        $this->submissionRepository->method('findByArticleAndPlatform')->willReturn(null);
        $this->submissionRepository->method('save');

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (ClickerCommand $cmd): bool {
                /** @var array<string, mixed> $payload */
                $payload = $cmd->getPayload()->toArray();
                /** @var array<string, mixed> $article */
                $article = $payload['article'];

                return [] === $article['images'];
            }))
            ->willReturn(new Envelope(new stdClass()));

        ($this->handler)(new PublishArticleCommand($article->getId() ?? 1, 1, Platform::Seznam));
    }

    public function testSucceedsWithoutCredentialWhenPlatformDoesNotRequireIt(): void
    {
        $this->articleRepository->method('findById')->willReturn(ArticleMother::any());
        $this->userRepository->method('findById')->willReturn(UserMother::withoutCredential());
        $this->submissionRepository->method('findByArticleAndPlatform')->willReturn(null);
        $this->submissionRepository->method('save');
        $this->messageBus->method('dispatch')->willReturn(new Envelope(new stdClass()));

        ($this->handler)(new PublishArticleCommand(1, 1, Platform::MotoInzerce));

        $this->expectNotToPerformAssertions();
    }

    public function testDispatchedCommandContainsCorrectRoutingKeyAndPlatform(): void
    {
        $this->articleRepository->method('findById')->willReturn(ArticleMother::any());
        $this->userRepository->method('findById')
            ->willReturn(UserMother::withCredential(CredentialMother::forService('bazos')));
        $this->submissionRepository->method('findByArticleAndPlatform')->willReturn(null);
        $this->submissionRepository->method('save');

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static fn (ClickerCommand $cmd): bool => 'action.publish' === $cmd->getRoutingKey()
                && 'bazos' === $cmd->getPayload()->toArray()['platform']))
            ->willReturn(new Envelope(new stdClass()));

        ($this->handler)(new PublishArticleCommand(1, 1, Platform::Bazos));
    }

    protected function setUp(): void
    {
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->submissionRepository = $this->createMock(ArticleSubmissionRepositoryInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new PublishArticleHandler(
            $this->userRepository,
            $this->articleRepository,
            $this->submissionRepository,
            $this->messageBus,
            $this->logger,
            appSecret: 'test-secret',
            internalAppUrl: 'http://app.test',
        );
    }
}
