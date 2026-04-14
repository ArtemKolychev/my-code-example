<?php

declare(strict_types=1);

namespace App\Tests\Unit\Adapter;

use App\Adapter\PlatformFieldAggregator;
use App\Adapter\PlatformFieldProvider;
use App\Entity\Article;
use App\Enum\Category;
use App\Enum\Condition;
use App\Enum\Platform;
use PHPUnit\Framework\TestCase;

class PlatformFieldAggregatorTest extends TestCase
{
    private function makeProvider(Platform $platform, array $fields): PlatformFieldProvider
    {
        $mock = $this->createMock(PlatformFieldProvider::class);
        $mock->method('getPlatform')->willReturn($platform);
        $mock->method('getRequiredMetaFields')->willReturn($fields);

        return $mock;
    }

    // --- getMissingFields ---

    public function testGetMissingFieldsReturnsProviderFieldsWhenNotInMeta(): void
    {
        $provider = $this->makeProvider(Platform::Vinted, [
            'brand' => ['label' => 'Značka', 'type' => 'text'],
            'size' => ['label' => 'Velikost', 'type' => 'text'],
        ]);
        $aggregator = new PlatformFieldAggregator([$provider]);

        $article = new Article();
        $article->category = Category::Clothing; // has 'size', 'condition' required
        $article->condition = Condition::Good; // condition set — not missing

        $missing = $aggregator->getMissingFields($article);

        $this->assertArrayHasKey('brand', $missing);
        $this->assertArrayHasKey('size', $missing);
        $this->assertSame([Platform::Vinted], $missing['brand']['platforms']);
    }

    public function testGetMissingFieldsSkipsFieldsPresentInMeta(): void
    {
        $provider = $this->makeProvider(Platform::Vinted, [
            'brand' => ['label' => 'Značka', 'type' => 'text'],
            'size' => ['label' => 'Velikost', 'type' => 'text'],
        ]);
        $aggregator = new PlatformFieldAggregator([$provider]);

        $article = new Article();
        $article->meta = ['brand' => 'Nike', 'size' => 'M'];
        $article->category = Category::Clothing;
        $article->condition = Condition::Good;

        $missing = $aggregator->getMissingFields($article);

        $this->assertArrayNotHasKey('brand', $missing);
        $this->assertArrayNotHasKey('size', $missing);
    }

    public function testGetMissingFieldsDeduplicatesAcrossProviders(): void
    {
        $p1 = $this->makeProvider(Platform::Vinted, [
            'brand' => ['label' => 'Značka', 'type' => 'text'],
        ]);
        $p2 = $this->makeProvider(Platform::Bazos, [
            'brand' => ['label' => 'Značka', 'type' => 'text'],
        ]);
        $aggregator = new PlatformFieldAggregator([$p1, $p2]);

        $article = new Article();

        $missing = $aggregator->getMissingFields($article);

        $this->assertArrayHasKey('brand', $missing);
        $this->assertCount(2, $missing['brand']['platforms']);
        $this->assertContains(Platform::Vinted, $missing['brand']['platforms']);
        $this->assertContains(Platform::Bazos, $missing['brand']['platforms']);
    }

    public function testGetMissingFieldsIncludesCategoryFieldsWithEmptyPlatforms(): void
    {
        $aggregator = new PlatformFieldAggregator([]);

        $article = new Article();
        $article->category = Category::Car;
        // no meta, no condition

        $missing = $aggregator->getMissingFields($article);

        $this->assertArrayHasKey('brand', $missing);
        $this->assertArrayHasKey('model', $missing);
        $this->assertArrayHasKey('year', $missing);
        $this->assertArrayHasKey('condition', $missing);
        $this->assertSame([], $missing['brand']['platforms']);
    }

    // --- getMissingFieldsForPlatform ---

    public function testGetMissingFieldsForPlatformReturnsFieldsForMatchingProvider(): void
    {
        $provider = $this->makeProvider(Platform::Vinted, [
            'brand' => ['label' => 'Značka', 'type' => 'text'],
            'size' => ['label' => 'Velikost', 'type' => 'text'],
        ]);
        $aggregator = new PlatformFieldAggregator([$provider]);

        $article = new Article();
        $article->meta = ['brand' => 'Nike']; // brand present

        $missing = $aggregator->getMissingFieldsForPlatform($article, Platform::Vinted);

        $this->assertSame(['size'], $missing);
    }

    public function testGetMissingFieldsForPlatformReturnsEmptyForUnknownPlatform(): void
    {
        $aggregator = new PlatformFieldAggregator([]);

        $article = new Article();

        $missing = $aggregator->getMissingFieldsForPlatform($article, Platform::Bazos);

        $this->assertSame([], $missing);
    }

    // --- getCategoryMissingFields ---

    public function testGetCategoryMissingFieldsReturnsEmptyWhenNoCategorySet(): void
    {
        $aggregator = new PlatformFieldAggregator([]);
        $article = new Article();

        $this->assertSame([], $aggregator->getCategoryMissingFields($article));
    }

    public function testGetCategoryMissingFieldsReturnsBrandModelYearForCarWithNoMeta(): void
    {
        $aggregator = new PlatformFieldAggregator([]);

        $article = new Article();
        $article->category = Category::Car;
        // no condition, no meta

        $missing = $aggregator->getCategoryMissingFields($article);

        $this->assertContains('brand', $missing);
        $this->assertContains('model', $missing);
        $this->assertContains('year', $missing);
        $this->assertContains('condition', $missing);
    }

    public function testGetCategoryMissingFieldsOnlyReturnsConditionWhenMetaComplete(): void
    {
        $aggregator = new PlatformFieldAggregator([]);

        $article = new Article();
        $article->category = Category::Car;
        $article->meta = ['brand' => 'BMW', 'model' => 'M3', 'year' => '2020'];
        // condition not set

        $missing = $aggregator->getCategoryMissingFields($article);

        $this->assertSame(['condition'], $missing);
    }

    public function testGetCategoryMissingFieldsReturnsEmptyWhenAllSet(): void
    {
        $aggregator = new PlatformFieldAggregator([]);

        $article = new Article();
        $article->category = Category::Car;
        $article->meta = ['brand' => 'Audi', 'model' => 'A4', 'year' => '2021'];
        $article->condition = Condition::VeryGood;

        $missing = $aggregator->getCategoryMissingFields($article);

        $this->assertSame([], $missing);
    }

    // --- getAllFieldDefinitions ---

    public function testGetAllFieldDefinitionsAggregatesFromAllProviders(): void
    {
        $p1 = $this->makeProvider(Platform::Vinted, [
            'brand' => ['label' => 'Značka', 'type' => 'text'],
        ]);
        $p2 = $this->makeProvider(Platform::Bazos, [
            'phone' => ['label' => 'Telefon', 'type' => 'text'],
        ]);
        $aggregator = new PlatformFieldAggregator([$p1, $p2]);

        $all = $aggregator->getAllFieldDefinitions();

        $this->assertArrayHasKey('brand', $all);
        $this->assertArrayHasKey('phone', $all);
    }
}
