<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Application\Command\ClickerCommand;
use App\Application\Command\EnrichVehicleCommand;
use App\Application\Command\PublishArticleCommand;
use App\Application\Service\ArticleInputService;
use App\Domain\Entity\User;
use App\Domain\Enum\Category;
use App\Domain\Enum\Condition;
use App\Domain\Enum\Platform;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Tests\Shared\Mother\ArticleMother;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ArticleInputServiceTest extends TestCase
{
    private ArticleRepositoryInterface&MockObject $articleRepository;
    private MessageBusInterface&MockObject $messageBus;
    private ArticleInputService $service;

    // --- handleMetaFieldsInput ---

    public function testHandleMetaFieldsInputReturnsErrorWhenFieldsEmpty(): void
    {
        $article = ArticleMother::any();
        $user = $this->createMock(User::class);

        $result = $this->service->handleMetaFieldsInput($article, $user, []);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('required', $result['error'] ?? '');
    }

    public function testHandleMetaFieldsInputReturnsErrorWhenPlatformUnknown(): void
    {
        $article = ArticleMother::any();
        $article->setPendingInput(['jobId' => 'unknownplatform_abc123']);
        $user = $this->createMock(User::class);

        $result = $this->service->handleMetaFieldsInput($article, $user, ['foo' => 'bar']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('platform', $result['error'] ?? '');
    }

    public function testHandleMetaFieldsInputMergesMetaAndDispatchesPublish(): void
    {
        $article = ArticleMother::any();
        $article->id = 10;
        $article->setPendingInput(['jobId' => 'seznam_abc123']);
        $article->setMeta(['existing' => 'value']);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(42);

        $this->articleRepository->expects($this->once())->method('save');

        $dispatchedMessage = null;
        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(PublishArticleCommand::class))
            ->willReturnCallback(function (object $msg) use (&$dispatchedMessage): Envelope {
                $dispatchedMessage = $msg;

                return new Envelope($msg);
            });

        $result = $this->service->handleMetaFieldsInput($article, $user, ['brand' => 'Toyota']);

        $this->assertTrue($result['success']);
        $this->assertSame(['existing' => 'value', 'brand' => 'Toyota'], $article->getMeta());
        $this->assertNull($article->getPendingInput());
        $this->assertInstanceOf(PublishArticleCommand::class, $dispatchedMessage);
        $this->assertSame(Platform::Seznam, $dispatchedMessage->getPlatform());
    }

    public function testHandleMetaFieldsInputDoesNotDispatchWhenArticleIdNull(): void
    {
        $article = ArticleMother::any();
        $article->id = null; // id is null
        $article->setPendingInput(['jobId' => 'bazos_xyz']);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $this->messageBus->expects($this->never())->method('dispatch');
        $this->articleRepository->expects($this->once())->method('save');

        $result = $this->service->handleMetaFieldsInput($article, $user, ['price' => '500']);

        $this->assertTrue($result['success']);
    }

    // --- handleCategoryFieldsInput ---

    public function testHandleCategoryFieldsInputReturnsErrorWhenFieldsEmpty(): void
    {
        $article = ArticleMother::any();

        $result = $this->service->handleCategoryFieldsInput($article, []);

        $this->assertFalse($result['success']);
    }

    public function testHandleCategoryFieldsInputReturnsErrorWhenNoCategorySet(): void
    {
        $article = ArticleMother::any(); // no category

        $result = $this->service->handleCategoryFieldsInput($article, ['brand' => 'BMW']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('category', $result['error'] ?? '');
    }

    public function testHandleCategoryFieldsInputReturnsErrorForInvalidCondition(): void
    {
        $article = ArticleMother::any();
        $article->category = Category::Car;

        $result = $this->service->handleCategoryFieldsInput($article, ['condition' => 'broken']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('condition', $result['error'] ?? '');
    }

    public function testHandleCategoryFieldsInputSetsConditionAndMergesMeta(): void
    {
        $article = ArticleMother::any();
        $article->category = Category::Car;
        $article->setPendingInput(['jobId' => 'x']);

        $this->articleRepository->expects($this->once())->method('save');

        $result = $this->service->handleCategoryFieldsInput($article, [
            'condition' => 'good',
            'brand' => 'BMW',
            'model' => 'M3',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(Condition::Good, $article->getCondition());
        $this->assertSame(['brand' => 'BMW', 'model' => 'M3'], $article->getMeta());
        $this->assertNull($article->getPendingInput());
    }

    public function testHandleCategoryFieldsInputSkipsNullAndEmptyValues(): void
    {
        $article = ArticleMother::any();
        $article->category = Category::Electronics;

        $this->articleRepository->method('save');

        $result = $this->service->handleCategoryFieldsInput($article, [
            'brand' => 'Sony',
            'model' => null,
            'color' => '',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(['brand' => 'Sony'], $article->getMeta());
    }

    // --- handleVinOrSpzInput ---

    public function testHandleVinOrSpzInputDispatchesVinWhenValidVin(): void
    {
        $article = ArticleMother::any();
        $vin = 'WBA3A5C51DF354762'; // 17-char valid VIN pattern

        $dispatchedMessage = null;
        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (object $msg) use (&$dispatchedMessage): Envelope {
                $dispatchedMessage = $msg;

                return new Envelope($msg);
            });

        $this->articleRepository->expects($this->once())->method('save');

        $this->service->handleVinOrSpzInput($article, 99, $vin);

        $this->assertInstanceOf(EnrichVehicleCommand::class, $dispatchedMessage);
        $this->assertSame(strtoupper($vin), $dispatchedMessage->getVin());
        $this->assertNull($dispatchedMessage->getSpz());
        $this->assertNull($article->getPendingInput());
    }

    public function testHandleVinOrSpzInputDispatchesSpzWhenNotVin(): void
    {
        $article = ArticleMother::any();
        $spz = '1AB2345';

        $dispatchedMessage = null;
        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (object $msg) use (&$dispatchedMessage): Envelope {
                $dispatchedMessage = $msg;

                return new Envelope($msg);
            });

        $this->articleRepository->method('save');

        $this->service->handleVinOrSpzInput($article, 5, $spz);

        $this->assertInstanceOf(EnrichVehicleCommand::class, $dispatchedMessage);
        $this->assertNull($dispatchedMessage->getVin());
        $this->assertSame($spz, $dispatchedMessage->getSpz());
    }

    // --- handleCodeInput ---

    public function testHandleCodeInputDispatchesClickerCommandAndClearsPendingInput(): void
    {
        $article = ArticleMother::any();
        $article->setPendingInput(['inputType' => 'code']);

        $dispatchedMessage = null;
        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ClickerCommand::class))
            ->willReturnCallback(function (object $msg) use (&$dispatchedMessage): Envelope {
                $dispatchedMessage = $msg;

                return new Envelope($msg);
            });

        $this->articleRepository->expects($this->once())->method('save');

        $this->service->handleCodeInput($article, 'seznam_job1', '12345');

        $this->assertNull($article->getPendingInput());
    }

    protected function setUp(): void
    {
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->service = new ArticleInputService($this->articleRepository, $this->messageBus);
    }
}
