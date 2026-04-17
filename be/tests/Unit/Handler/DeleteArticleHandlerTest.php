<?php

declare(strict_types=1);

namespace App\Tests\Unit\Handler;

use App\Application\Command\ClickerCommand;
use App\Application\Command\DeleteArticleCommand;
use App\Application\Handler\DeleteArticleHandler;
use App\Domain\Enum\Platform;
use App\Domain\Exception\ArticleMissingUrlException;
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
use App\Tests\Shared\Mother\UserMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class DeleteArticleHandlerTest extends TestCase
{
    private ArticleRepositoryInterface&MockObject $articleRepository;
    private UserRepositoryInterface&MockObject $userRepository;
    private ArticleSubmissionRepositoryInterface&MockObject $submissionRepository;
    private MessageBusInterface&MockObject $messageBus;
    private LoggerInterface&MockObject $logger;

    private DeleteArticleHandler $handler;

    /** @return array<string, array{string, string, string}> */
    public static function platformUrlDetectionProvider(): array
    {
        return [
            'bazos.cz URL → bazos platform' => ['https://www.bazos.cz/inzerat/1', 'bazos', 'bazos'],
            'sbazar.cz URL → seznam platform' => ['https://www.sbazar.cz/inzerat/1', 'sbazar', 'seznam'],
        ];
    }

    // -------------------------------------------------------------------------
    // Exception paths
    // -------------------------------------------------------------------------

    public function testThrowsArticleNotFoundExceptionWhenArticleMissing(): void
    {
        $this->articleRepository->method('findById')->willReturn(null);

        $this->expectException(ArticleNotFoundException::class);
        $this->expectExceptionMessage('Article id:[7] not found');

        ($this->handler)(new DeleteArticleCommand(7, 1, Platform::Bazos, 'https://bazos.cz/ad/1'));
    }

    public function testThrowsArticleMissingUrlExceptionWhenUrlIsNull(): void
    {
        $this->articleRepository->method('findById')->willReturn(ArticleMother::any());

        $this->expectException(ArticleMissingUrlException::class);
        $this->expectExceptionMessage('has no articleUrl');

        ($this->handler)(new DeleteArticleCommand(5, 1, Platform::Bazos));
    }

    public function testThrowsUserNotFoundExceptionWhenUserMissing(): void
    {
        $this->articleRepository->method('findById')->willReturn(ArticleMother::any());
        $this->userRepository->method('findById')->willReturn(null);

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User id:[99] not found');

        ($this->handler)(new DeleteArticleCommand(5, 99, Platform::Bazos, 'https://bazos.cz/ad/1'));
    }

    public function testThrowsMissingCredentialExceptionWhenUserHasNoCredential(): void
    {
        $this->articleRepository->method('findById')->willReturn(ArticleMother::any());
        $this->userRepository->method('findById')->willReturn(UserMother::withoutCredential());

        $this->expectException(MissingCredentialException::class);

        ($this->handler)(new DeleteArticleCommand(5, 1, Platform::Bazos, 'https://bazos.cz/ad/1'));
    }

    // -------------------------------------------------------------------------
    // Happy paths
    // -------------------------------------------------------------------------

    public function testDispatchesDeleteCommandWithCorrectRoutingKeyAndPayload(): void
    {
        $articleId = random_int(1, 1000);
        $userId = random_int(1, 1000);
        $adUrl = 'https://www.bazos.cz/inzerat/'.$articleId;

        $this->articleRepository->method('findById')->willReturn(ArticleMother::withId($articleId));
        $this->userRepository->method('findById')
            ->willReturn(UserMother::withCredential(CredentialMother::forService('bazos')));
        $this->submissionRepository->method('findByArticleAndPlatform')->willReturn(null);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (ClickerCommand $cmd) use ($articleId, $adUrl): bool {
                $payload = $cmd->getPayload()->toArray();

                return 'action.delete' === $cmd->getRoutingKey()
                    && $adUrl === $payload['articleUrl']
                    && $articleId === $payload['articleId']
                    && 'bazos' === $payload['platform'];
            }))
            ->willReturn(new Envelope(new stdClass()));

        ($this->handler)(new DeleteArticleCommand($articleId, $userId, Platform::Bazos, $adUrl));
    }

    public function testSetsExistingSubmissionStatusToDeletingAndUpdatesJobId(): void
    {
        $articleId = random_int(1, 1000);
        $submission = ArticleSubmissionMother::completed(Platform::Bazos, 'old-job-id');

        $this->articleRepository->method('findById')->willReturn(ArticleMother::withId($articleId));
        $this->userRepository->method('findById')
            ->willReturn(UserMother::withCredential(CredentialMother::forService('bazos')));
        $this->submissionRepository->method('findByArticleAndPlatform')->willReturn($submission);
        $this->submissionRepository->expects($this->once())->method('save')->with($submission);
        $this->stubDispatch();

        ($this->handler)(new DeleteArticleCommand($articleId, 1, Platform::Bazos, 'https://www.bazos.cz/inzerat/1'));

        $this->assertSame(SubmissionStatus::Deleting, $submission->getStatus());
        $this->assertNotSame('old-job-id', $submission->getJobId());
    }

    public function testDoesNotSaveSubmissionWhenNoneExists(): void
    {
        $this->articleRepository->method('findById')->willReturn(ArticleMother::any());
        $this->userRepository->method('findById')
            ->willReturn(UserMother::withCredential(CredentialMother::forService('bazos')));
        $this->submissionRepository->method('findByArticleAndPlatform')->willReturn(null);
        $this->submissionRepository->expects($this->never())->method('save');
        $this->stubDispatch();

        ($this->handler)(new DeleteArticleCommand(1, 1, Platform::Bazos, 'https://www.bazos.cz/inzerat/1'));
    }

    // -------------------------------------------------------------------------
    // Platform auto-detection from URL
    // -------------------------------------------------------------------------

    #[DataProvider('platformUrlDetectionProvider')]
    public function testDetectsPlatformFromAdUrl(
        string $adUrl,
        string $credentialService,
        string $expectedPlatform,
    ): void {
        $this->articleRepository->method('findById')->willReturn(ArticleMother::any());
        $this->userRepository->method('findById')
            ->willReturn(UserMother::withCredentialForService(
                $credentialService,
                CredentialMother::forService($credentialService),
            ));
        $this->submissionRepository->method('findByArticleAndPlatform')->willReturn(null);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static fn (ClickerCommand $cmd): bool => $expectedPlatform === $cmd->getPayload()->toArray()['platform']))
            ->willReturn(new Envelope(new stdClass()));

        ($this->handler)(new DeleteArticleCommand(1, 1, Platform::Seznam, $adUrl));
    }

    protected function setUp(): void
    {
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->submissionRepository = $this->createMock(ArticleSubmissionRepositoryInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new DeleteArticleHandler(
            $this->userRepository,
            $this->articleRepository,
            $this->submissionRepository,
            $this->messageBus,
            $this->logger,
            appSecret: 'test-secret',
        );
    }

    private function stubDispatch(): void
    {
        $this->messageBus->method('dispatch')->willReturn(new Envelope(new stdClass()));
    }
}
