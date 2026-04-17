<?php

declare(strict_types=1);

namespace App\EntryPoint\Service\Result;

/**
 * Result of processing the article edit forms for one request cycle.
 */
final readonly class ArticleEditFormsResult
{
    /**
     * @param list<array{form: mixed, article: mixed}> $forms
     * @param bool                                     $dispatched true when a command was dispatched and the caller should redirect
     */
    public function __construct(
        public array $forms,
        public bool $dispatched,
    ) {
    }
}
