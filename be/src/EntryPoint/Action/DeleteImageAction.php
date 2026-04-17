<?php

declare(strict_types=1);

namespace App\EntryPoint\Action;

use App\Application\Command\DeleteImageCommand;
use App\Domain\Entity\Image;
use App\Domain\Security\Permission;
use Exception;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/market/image/{id}/delete', name: 'app_delete_image', methods: ['DELETE'])]
#[IsGranted(Permission::IMAGE_DELETE, subject: 'image')]
final class DeleteImageAction extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(#[MapEntity(id: 'id')] Image $image): JsonResponse
    {
        try {
            $this->messageBus->dispatch(new DeleteImageCommand((int) $image->getId()));

            return new JsonResponse(['success' => 'Image deleted successfully']);
        } catch (Exception) {
            return new JsonResponse(['error' => 'Failed to delete image'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
