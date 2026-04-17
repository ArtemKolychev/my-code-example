<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Entity\ServiceCredential;
use App\Domain\Repository\ServiceCredentialRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceCredential>
 */
class ServiceCredentialRepository extends ServiceEntityRepository implements ServiceCredentialRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceCredential::class);
    }

    public function save(ServiceCredential $credential): void
    {
        $this->getEntityManager()->persist($credential);
        $this->getEntityManager()->flush();
    }
}
