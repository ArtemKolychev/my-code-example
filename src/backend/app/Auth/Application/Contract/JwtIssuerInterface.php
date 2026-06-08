<?php

declare(strict_types=1);

namespace App\Auth\Application\Contract;

use App\Auth\Application\DTO\Response\TokenPairDto;
use App\Auth\Domain\User\User;

interface JwtIssuerInterface
{
    public function issueTokenPair(User $user, string $jobId): TokenPairDto;
}
