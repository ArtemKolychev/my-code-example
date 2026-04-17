<?php

declare(strict_types=1);

namespace App\EntryPoint\Action;

use App\Application\Query\BatchProviderInterface;
use App\Application\Service\BatchService;
use App\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/batch/{batchId}/retry', name: 'app_batch_retry', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
final class RetryBatchAction extends AbstractController
{
    public function __construct(
        private readonly BatchProviderInterface $batchProvider,
        private readonly BatchService $batchService,
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

        $result = $this->batchService->retryBatch($batch);

        if (!$result->success) {
            return new JsonResponse(['error' => $result->error ?? 'Unknown error'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['success' => true]);
    }
}
