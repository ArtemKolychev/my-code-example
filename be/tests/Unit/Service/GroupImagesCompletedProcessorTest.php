<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Application\Service\GroupImagesCompletedService;
use App\Domain\Repository\ImageBatchRepositoryInterface;
use App\Domain\ValueObject\BatchStatus;
use App\Tests\Shared\Mother\ImageBatchMother;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GroupImagesCompletedProcessorTest extends TestCase
{
    private ImageBatchRepositoryInterface&MockObject $batchRepository;
    private LoggerInterface&MockObject $logger;
    private GroupImagesCompletedService $processor;

    public function testSetsBatchNeedsInputWithGroupConditions(): void
    {
        $batch = ImageBatchMother::withId(20, 'batch_job1');
        $this->batchRepository->method('findById')->with(20)->willReturn($batch);
        $this->batchRepository->expects($this->once())->method('save');

        $this->processor->process([
            'action' => 'group_images',
            'batchId' => 20,
            'groups' => [
                [
                    'category' => 'car',
                    'title' => 'BMW M3',
                    'images' => ['a.jpg'],
                    'missing_fields' => ['brand', 'model'],
                ],
            ],
        ]);

        $this->assertSame(BatchStatus::NeedsInput, $batch->getStatus());
        $this->assertIsArray($batch->pendingInput);
        $this->assertSame('group_conditions', $batch->pendingInput['inputType']);
        $this->assertCount(1, (array) ($batch->pendingInput['groups'] ?? []));
        $this->assertNotEmpty($batch->pendingInput['conditionOptions']);
    }

    public function testSetsBatchFailedWhenEmptyGroups(): void
    {
        $batch = ImageBatchMother::withId(21, 'batch_job2');
        $this->batchRepository->method('findById')->with(21)->willReturn($batch);
        $this->batchRepository->expects($this->once())->method('save');

        $this->processor->process([
            'action' => 'group_images',
            'batchId' => 21,
            'groups' => [],
        ]);

        $this->assertSame(BatchStatus::Failed, $batch->getStatus());
    }

    public function testSkipsWhenNoBatchId(): void
    {
        $this->batchRepository->expects($this->never())->method('findById');
        $this->batchRepository->expects($this->never())->method('save');

        $this->processor->process(['action' => 'group_images', 'groups' => []]);
    }

    public function testSkipsWhenBatchNotFound(): void
    {
        $this->batchRepository->method('findById')->willReturn(null);
        $this->batchRepository->expects($this->never())->method('save');

        $this->processor->process(['action' => 'group_images', 'batchId' => 99, 'groups' => []]);
    }

    protected function setUp(): void
    {
        $this->batchRepository = $this->createMock(ImageBatchRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->processor = new GroupImagesCompletedService($this->batchRepository, $this->logger);
    }
}
