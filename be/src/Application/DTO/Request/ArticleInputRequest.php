<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class ArticleInputRequest
{
    public static function fromJsonBody(string $content, string $inputType): self
    {
        /** @var array<string, mixed> $body */
        $body = json_decode($content, true) ?? [];

        /** @var array<string, mixed> $fields */
        $fields = isset($body['fields']) && is_array($body['fields']) ? $body['fields'] : [];

        return new self(
            inputType: $inputType,
            fields: $fields,
            code: isset($body['code']) && is_scalar($body['code']) ? (string) $body['code'] : '',
        );
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Input type is required.')]
        public readonly string $inputType = '',

        public readonly array $fields = [],

        public readonly string $code = '',
    ) {
    }

    #[Assert\IsTrue(message: 'Code is required.')]
    public function isCodeProvided(): bool
    {
        if ('meta_fields' === $this->inputType) {
            return true;
        }

        return '' !== $this->code;
    }

    #[Assert\IsTrue(message: 'Fields are required for meta_fields input.')]
    public function isFieldsProvided(): bool
    {
        if ('meta_fields' !== $this->inputType) {
            return true;
        }

        return !empty($this->fields);
    }
}
