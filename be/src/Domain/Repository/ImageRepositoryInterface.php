<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Image;

interface ImageRepositoryInterface
{
    public function findById(int $id): ?Image;

    public function save(Image $image): void;

    public function remove(Image $image): void;
}
