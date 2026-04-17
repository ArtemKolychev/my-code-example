<?php

declare(strict_types=1);

namespace App\EntryPoint\Action;

use App\Application\Query\BatchProviderInterface;
use App\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/batch/{batchId}/status', name: 'app_batch_status', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class BatchStatusAction extends AbstractController
{
    public function __construct(
        private readonly BatchProviderInterface $batchProvider,
    ) {
    }

    public function __invoke(int $batchId): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $batch = $this->batchProvider->findForUser($batchId, $user);

        if (!$batch) {
            return new JsonResponse(['error' => 'Batch not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->batchProvider->getStatus($batch));
    }
}
