<?php

declare(strict_types=1);

namespace App\EntryPoint\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

/**
 * Converts between a PHP array and a JSON string for use in HiddenType form fields.
 *
 * @implements DataTransformerInterface<array<string, mixed>|null, string>
 */
final class JsonStringTransformer implements DataTransformerInterface
{
    /**
     * Array → JSON string (for rendering the hidden input value).
     *
     * @param array<string, mixed>|null $value
     */
    public function transform(mixed $value): string
    {
        if (!is_array($value) || [] === $value) {
            return '';
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * JSON string → array (after form submission).
     *
     * @return array<string, mixed>|null
     */
    public function reverseTransform(mixed $value): ?array
    {
        if (!is_string($value) || '' === $value) {
            return null;
        }

        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
