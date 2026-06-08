<?php

declare(strict_types=1);

namespace App\Auth\Http\Request;

/**
 * @template T of object
 */
interface TypedRequest
{
    /**
     * @return T
     */
    public function getBody(): object;
}
