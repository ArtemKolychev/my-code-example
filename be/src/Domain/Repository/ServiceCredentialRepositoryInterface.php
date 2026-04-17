<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\ServiceCredential;

interface ServiceCredentialRepositoryInterface
{
    public function save(ServiceCredential $credential): void;
}
