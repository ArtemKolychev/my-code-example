<?php

declare(strict_types=1);

namespace App\Tests\Shared\Mother;

use App\Domain\Entity\Article;
use App\Domain\Entity\Image;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Object Mother for Article domain entities.
 *
 * Creates pre-configured Article instances for use in unit tests.
 * All factory methods return real entities — no mocks.
 */
final class ArticleMother
{
    /**
     * A minimal Article with a random ID and no images.
     * Use this whenever the specific ID does not matter to the test.
     */
    public static function any(): Article
    {
        $article = new Article();
        $article->id = random_int(1, 1000);

        return $article;
    }

    /**
     * An Article whose ID is controlled by the caller.
     * Use this when the test must assert that the postArticlesHandler reads the ID from the command,
     * not from a hard-coded value.
     */
    public static function withId(int $id): Article
    {
        $article = new Article();
        $article->id = $id;

        return $article;
    }

    /**
     * An Article pre-loaded with the given Image objects.
     */
    public static function withImages(Image ...$images): Article
    {
        $article = self::any();
        $article->images = new ArrayCollection(array_values($images));

        return $article;
    }
}
