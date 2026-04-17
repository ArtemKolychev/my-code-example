<?php

declare(strict_types=1);

namespace App\EntryPoint\Action;

use App\Application\Query\BatchProviderInterface;
use App\Domain\Entity\User;
use App\Domain\ValueObject\BatchStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/market/processing/{batchId}', name: 'app_processing')]
#[IsGranted('ROLE_USER')]
final class ProcessingAction extends AbstractController
{
    public function __construct(
        private readonly BatchProviderInterface $batchProvider,
    ) {
    }

    public function __invoke(int $batchId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $batch = $this->batchProvider->findForUser($batchId, $user);

        if (!$batch) {
            $this->addFlash('error', 'Batch not found.');

            return $this->redirectToRoute('app_article_images');
        }

        if (BatchStatus::Completed === $batch->status && !empty($batch->articleIds)) {
            return $this->redirectToRoute('app_edit_article', [
                'id' => implode('-', $batch->articleIds),
            ]);
        }

        return $this->render('market/processing.html.twig', [
            'batchId' => $batchId,
        ]);
    }
}
