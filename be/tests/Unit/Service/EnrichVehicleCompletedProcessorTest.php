<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Application\Service\EnrichVehicleCompletedService;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Tests\Shared\Mother\ArticleMother;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class EnrichVehicleCompletedProcessorTest extends TestCase
{
    private ArticleRepositoryInterface&MockObject $articleRepository;
    private LoggerInterface&MockObject $logger;
    private EnrichVehicleCompletedService $processor;

    public function testMergesVehicleDataIntoMeta(): void
    {
        $article = ArticleMother::withId(14);
        $article->setMeta(['brand' => 'BMW']); // existing value must NOT be overwritten
        $this->articleRepository->method('findById')->with(14)->willReturn($article);
        $this->articleRepository->expects($this->once())->method('save');

        $this->processor->process([
            'action' => 'enrich_vehicle',
            'articleId' => 14,
            'found' => true,
            'vehicleData' => [
                'brand' => 'Audi', // should NOT override existing
                'model' => 'A4',
                'year' => '2019',
            ],
        ]);

        $meta = $article->getMeta();
        $this->assertNotNull($meta);
        $this->assertSame('BMW', $meta['brand']); // existing wins
        $this->assertSame('A4', $meta['model']);
        $this->assertSame('2019', $meta['year']);
    }

    public function testSkipsWhenNotFound(): void
    {
        $article = ArticleMother::withId(15);
        $this->articleRepository->method('findById')->with(15)->willReturn($article);
        $this->articleRepository->expects($this->never())->method('save');

        $this->processor->process([
            'action' => 'enrich_vehicle',
            'articleId' => 15,
            'found' => false,
            'vehicleData' => [],
        ]);

        $this->assertNull($article->getMeta());
    }

    public function testSkipsWhenNoArticleId(): void
    {
        $this->articleRepository->expects($this->never())->method('findById');
        $this->articleRepository->expects($this->never())->method('save');

        $this->processor->process(['action' => 'enrich_vehicle', 'found' => true, 'vehicleData' => ['model' => 'A4']]);
    }

    public function testSkipsWhenArticleNotFound(): void
    {
        $this->articleRepository->method('findById')->willReturn(null);
        $this->articleRepository->expects($this->never())->method('save');

        $this->processor->process(['action' => 'enrich_vehicle', 'articleId' => 99, 'found' => true, 'vehicleData' => ['model' => 'A4']]);
    }

    protected function setUp(): void
    {
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->processor = new EnrichVehicleCompletedService($this->articleRepository, $this->logger);
    }
}
