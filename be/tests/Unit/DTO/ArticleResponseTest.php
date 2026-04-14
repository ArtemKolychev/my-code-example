<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\Response\ArticleResponse;
use App\Entity\Article;
use App\Entity\Image;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

class ArticleResponseTest extends TestCase
{
    public function testFromEntityMapsAllFields(): void
    {
        $article = new Article();
        $article->setTitle('Test Article');
        $article->setDescription('Test Description');
        $article->setPrice(99.99);
        $article->setPublicResultData(['isOk' => true]);

        $image = new Image();
        $image->setLink('/uploads/images/1/test.jpeg');
        $image->setArticle($article);

        $article->setImages(new ArrayCollection([$image]));

        $response = ArticleResponse::fromEntity($article);

        $this->assertSame('Test Article', $response->title);
        $this->assertSame('Test Description', $response->description);
        $this->assertSame(99.99, $response->price);
        $this->assertSame(['isOk' => true], $response->publicResultData);
        $this->assertCount(1, $response->images);
        $this->assertSame('/uploads/images/1/test.jpeg', $response->images[0]['link']);
        $this->assertSame([], $response->submissions);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $article = new Article();
        $article->setTitle('Test');
        $article->setDescription('Desc');
        $article->setImages(new ArrayCollection());

        $response = ArticleResponse::fromEntity($article);
        $array = $response->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('price', $array);
        $this->assertArrayHasKey('submissions', $array);
        $this->assertArrayHasKey('publicResultData', $array);
        $this->assertArrayHasKey('images', $array);
    }

    public function testFromEntityWithEmptyImages(): void
    {
        $article = new Article();
        $article->setTitle('No Images');
        $article->setDescription('Empty');

        $response = ArticleResponse::fromEntity($article);

        $this->assertSame([], $response->images);
    }
}
