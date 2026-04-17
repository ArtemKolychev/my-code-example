<?php

declare(strict_types=1);

namespace App\Tests\Shared\Mother;

use App\Domain\Entity\Image;

/**
 * Object Mother for Image domain entities.
 *
 * All factory methods return real instances — no mocks.
 */
final class ImageMother
{
    /**
     * An Image with a link set. Useful for payload-mapping tests.
     */
    public static function withLink(string $link): Image
    {
        $image = new Image();
        $image->setLink($link);

        return $image;
    }

    /**
     * An Image without a link (null). Use to test that null-link images are filtered out.
     */
    public static function withoutLink(): Image
    {
        return new Image();
    }

    /**
     * A minimal Image with a generic link. Use when the specific path does not matter.
     */
    public static function any(): Image
    {
        return self::withLink('/uploads/images/1/any.jpg');
    }
}
