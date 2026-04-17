<?php

declare(strict_types=1);

namespace App\EntryPoint\Action;

use App\Application\DTO\Request\BatchInputRequest;
use App\Application\Query\BatchProviderInterface;
use App\Application\Service\BatchService;
use App\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/batch/{batchId}/input', name: 'app_batch_input', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
final class SubmitBatchInputAction extends AbstractController
{
    public function __construct(
        private readonly BatchProviderInterface $batchProvider,
        private readonly BatchService $batchService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function __invoke(int $batchId, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $batch = $this->batchProvider->findForUser($batchId, $user);

        if (!$batch) {
            return new JsonResponse(['error' => 'Batch not found'], Response::HTTP_NOT_FOUND);
        }

        $rawInputType = $batch->pendingInput['inputType'] ?? null;
        $inputType = is_string($rawInputType) ? $rawInputType : '';
        $dto = BatchInputRequest::fromJsonBody((string) $request->getContent(), $inputType);

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return new JsonResponse(['error' => (string) $errors->get(0)->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->batchService->handleInput($batch, $dto);

        if (!$result->success) {
            return new JsonResponse(['error' => $result->error ?? 'Unknown error'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['success' => true]);
    }
}
