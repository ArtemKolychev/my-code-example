<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

class BatchInputRequest
{
    public static function fromJsonBody(string $content, string $inputType): self
    {
        /** @var array<string, mixed> $body */
        $body = json_decode($content, true) ?? [];

        /** @var array<int|string, string> $groupConditions */
        $groupConditions = isset($body['groupConditions']) && is_array($body['groupConditions'])
            ? $body['groupConditions']
            : [];
        /** @var array<int|string, array<string, mixed>> $groupFields */
        $groupFields = isset($body['groupFields']) && is_array($body['groupFields'])
            ? $body['groupFields']
            : [];
        $code = isset($body['code']) && is_scalar($body['code']) ? trim((string) $body['code']) : '';

        return new self(
            inputType: $inputType,
            groupConditions: $groupConditions,
            groupFields: $groupFields,
            code: $code,
        );
    }

    /**
     * @param array<int|string, string>               $groupConditions
     * @param array<int|string, array<string, mixed>> $groupFields
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Input type must be determined from the batch.')]
        public readonly string $inputType = '',

        public readonly array $groupConditions = [],

        public readonly array $groupFields = [],

        #[Assert\Length(max: 50)]
        public readonly string $code = '',
    ) {
    }

    #[Assert\IsTrue(message: 'Code is required for vehicle identifier input.')]
    public function isCodeValidForLegacyInput(): bool
    {
        if ('group_conditions' === $this->inputType) {
            return true;
        }

        return '' !== $this->code;
    }
}
