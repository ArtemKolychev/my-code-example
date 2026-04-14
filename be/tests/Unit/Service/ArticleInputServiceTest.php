<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Article;
use App\Entity\User;
use App\Enum\Category;
use App\Enum\Condition;
use App\Enum\Platform;
use App\Message\ClickerCommand;
use App\Message\EnrichVehicleMessage;
use App\Message\PublishMessage;
use App\Service\ArticleInputService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ArticleInputServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private MessageBusInterface&MockObject $messageBus;
    private ArticleInputService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->service = new ArticleInputService($this->em, $this->messageBus);
    }

    // --- handleMetaFieldsInput ---

    public function testHandleMetaFieldsInputReturnsErrorWhenFieldsEmpty(): void
    {
        $article = new Article();
        $user = $this->createMock(User::class);

        $result = $this->service->handleMetaFieldsInput($article, $user, []);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('required', $result['error']);
    }

    public function testHandleMetaFieldsInputReturnsErrorWhenPlatformUnknown(): void
    {
        $article = new Article();
        $article->setPendingInput(['jobId' => 'unknownplatform_abc123']);
        $user = $this->createMock(User::class);

        $result = $this->service->handleMetaFieldsInput($article, $user, ['foo' => 'bar']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('platform', $result['error']);
    }

    public function testHandleMetaFieldsInputMergesMetaAndDispatchesPublish(): void
    {
        $article = new Article();
        $article->id = 10;
        $article->setPendingInput(['jobId' => 'seznam_abc123']);
        $article->setMeta(['existing' => 'value']);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(42);

        $this->em->expects($this->once())->method('flush');

        $dispatchedMessage = null;
        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(PublishMessage::class))
            ->willReturnCallback(function (object $msg) use (&$dispatchedMessage) {
                $dispatchedMessage = $msg;

                return new Envelope($msg);
            });

        $result = $this->service->handleMetaFieldsInput($article, $user, ['brand' => 'Toyota']);

        $this->assertTrue($result['success']);
        $this->assertSame(['existing' => 'value', 'brand' => 'Toyota'], $article->getMeta());
        $this->assertNull($article->getPendingInput());
        $this->assertInstanceOf(PublishMessage::class, $dispatchedMessage);
        $this->assertSame(Platform::Seznam, $dispatchedMessage->getPlatform());
    }

    public function testHandleMetaFieldsInputDoesNotDispatchWhenArticleIdNull(): void
    {
        $article = new Article(); // id is null
        $article->setPendingInput(['jobId' => 'bazos_xyz']);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $this->messageBus->expects($this->never())->method('dispatch');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->handleMetaFieldsInput($article, $user, ['price' => '500']);

        $this->assertTrue($result['success']);
    }

    // --- handleCategoryFieldsInput ---

    public function testHandleCategoryFieldsInputReturnsErrorWhenFieldsEmpty(): void
    {
        $article = new Article();

        $result = $this->service->handleCategoryFieldsInput($article, []);

        $this->assertFalse($result['success']);
    }

    public function testHandleCategoryFieldsInputReturnsErrorWhenNoCategorySet(): void
    {
        $article = new Article(); // no category

        $result = $this->service->handleCategoryFieldsInput($article, ['brand' => 'BMW']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('category', $result['error']);
    }

    public function testHandleCategoryFieldsInputReturnsErrorForInvalidCondition(): void
    {
        $article = new Article();
        $article->category = Category::Car;

        $result = $this->service->handleCategoryFieldsInput($article, ['condition' => 'broken']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('condition', $result['error']);
    }

    public function testHandleCategoryFieldsInputSetsConditionAndMergesMeta(): void
    {
        $article = new Article();
        $article->category = Category::Car;
        $article->setPendingInput(['jobId' => 'x']);

        $this->em->expects($this->once())->method('flush');

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
        $article = new Article();
        $article->category = Category::Electronics;

        $this->em->method('flush');

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
        $article = new Article();
        $vin = 'WBA3A5C51DF354762'; // 17-char valid VIN pattern

        $dispatchedMessage = null;
        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (object $msg) use (&$dispatchedMessage) {
                $dispatchedMessage = $msg;

                return new Envelope($msg);
            });

        $this->em->expects($this->once())->method('flush');

        $this->service->handleVinOrSpzInput($article, 99, $vin);

        $this->assertInstanceOf(EnrichVehicleMessage::class, $dispatchedMessage);
        $this->assertSame(strtoupper($vin), $dispatchedMessage->getVin());
        $this->assertNull($dispatchedMessage->getSpz());
        $this->assertNull($article->getPendingInput());
    }

    public function testHandleVinOrSpzInputDispatchesSpzWhenNotVin(): void
    {
        $article = new Article();
        $spz = '1AB2345';

        $dispatchedMessage = null;
        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (object $msg) use (&$dispatchedMessage) {
                $dispatchedMessage = $msg;

                return new Envelope($msg);
            });

        $this->em->method('flush');

        $this->service->handleVinOrSpzInput($article, 5, $spz);

        $this->assertInstanceOf(EnrichVehicleMessage::class, $dispatchedMessage);
        $this->assertNull($dispatchedMessage->getVin());
        $this->assertSame($spz, $dispatchedMessage->getSpz());
    }

    // --- handleCodeInput ---

    public function testHandleCodeInputDispatchesClickerCommandAndClearsPendingInput(): void
    {
        $article = new Article();
        $article->setPendingInput(['inputType' => 'code']);

        $dispatchedMessage = null;
        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ClickerCommand::class))
            ->willReturnCallback(function (object $msg) use (&$dispatchedMessage) {
                $dispatchedMessage = $msg;

                return new Envelope($msg);
            });

        $this->em->expects($this->once())->method('flush');

        $this->service->handleCodeInput($article, 'seznam_job1', '12345');

        $this->assertNull($article->getPendingInput());
    }
}
