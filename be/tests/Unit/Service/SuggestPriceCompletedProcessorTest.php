<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Application\Service\SuggestPriceCompletedService;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Tests\Shared\Mother\ArticleMother;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SuggestPriceCompletedProcessorTest extends TestCase
{
    private ArticleRepositoryInterface&MockObject $articleRepository;
    private LoggerInterface&MockObject $logger;
    private SuggestPriceCompletedService $processor;

    public function testSetsPriceReasoningAndSources(): void
    {
        $article = ArticleMother::withId(12);
        $this->articleRepository->method('findById')->with(12)->willReturn($article);
        $this->articleRepository->expects($this->once())->method('save');

        $this->processor->process([
            'action' => 'suggest_price',
            'articleId' => 12,
            'price' => 4990.0,
            'reasoning' => 'Similar items sell for 5000',
            'sources' => [['name' => 'bazos', 'url' => 'https://bazos.cz/x']],
        ]);

        $this->assertSame(4990.0, $article->getPrice());
        $this->assertSame('Similar items sell for 5000', $article->getPriceReasoning());
        $this->assertCount(1, $article->getPriceSources() ?? []);
    }

    public function testDoesNotSetPriceWhenZero(): void
    {
        $article = ArticleMother::withId(13);
        $article->setPrice(1000.0);
        $this->articleRepository->method('findById')->with(13)->willReturn($article);
        $this->articleRepository->expects($this->once())->method('save');

        $this->processor->process([
            'action' => 'suggest_price',
            'articleId' => 13,
            'price' => 0,
        ]);

        $this->assertSame(1000.0, $article->getPrice());
    }

    public function testSkipsWhenNoArticleId(): void
    {
        $this->articleRepository->expects($this->never())->method('findById');
        $this->articleRepository->expects($this->never())->method('save');

        $this->processor->process(['action' => 'suggest_price', 'price' => 500.0]);
    }

    public function testSkipsWhenArticleNotFound(): void
    {
        $this->articleRepository->method('findById')->willReturn(null);
        $this->articleRepository->expects($this->never())->method('save');

        $this->processor->process(['action' => 'suggest_price', 'articleId' => 99, 'price' => 500.0]);
    }

    protected function setUp(): void
    {
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->processor = new SuggestPriceCompletedService($this->articleRepository, $this->logger);
    }
}
