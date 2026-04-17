<?php

declare(strict_types=1);

namespace App\EntryPoint\Action;

use App\Application\Service\ImageServiceInterface;
use App\Domain\Entity\Image;
use App\Domain\Security\Permission;
use Exception;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/market/image/{id}/rotate', name: 'app_rotate_image', methods: ['POST'])]
#[IsGranted(Permission::IMAGE_ROTATE, subject: 'image')]
final class RotateImageAction extends AbstractController
{
    public function __construct(
        private readonly ImageServiceInterface $imageService,
        #[Autowire('%public_directory%')]
        private readonly string $publicDirectory,
    ) {
    }

    public function __invoke(#[MapEntity(id: 'id')] Image $image): JsonResponse
    {
        try {
            $this->imageService->rotateImage($this->publicDirectory.$image->getLink());

            return new JsonResponse(['success' => true]);
        } catch (Exception) {
            return new JsonResponse(['error' => 'Failed to rotate image'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
