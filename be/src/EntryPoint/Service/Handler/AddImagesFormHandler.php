<?php

declare(strict_types=1);

namespace App\EntryPoint\Service\Handler;

use App\Application\Service\ImageUploadService;
use App\Domain\Entity\User;
use App\Domain\Exception\InsufficientTokensException;
use App\EntryPoint\Form\AddArticleImages;
use App\EntryPoint\Service\Result\AddImagesResult;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

final readonly class AddImagesFormHandler
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private ImageUploadService $imageUploadService,
    ) {
    }

    /** @return FormInterface<array<string, mixed>|null> */
    public function createForm(): FormInterface
    {
        return $this->formFactory->create(AddArticleImages::class);
    }

    /** @param FormInterface<array<string, mixed>|null> $form */
    public function handle(FormInterface $form, User $user, Request $request): AddImagesResult
    {
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return new AddImagesResult(redirectNeeded: false);
        }

        /** @var UploadedFile[]|null $images */
        $images = $form->get('images')->getData();

        if (empty($images)) {
            return new AddImagesResult(
                redirectNeeded: false,
                flashes: ['error' => 'Please select at least one image.'],
            );
        }

        if (!$form->isValid()) {
            return new AddImagesResult(
                redirectNeeded: false,
                flashes: ['error' => 'Image upload failed. Please try another file.'],
            );
        }

        try {
            $batch = $this->imageUploadService->uploadImages($images, $user);
        } catch (InsufficientTokensException) {
            return new AddImagesResult(
                redirectNeeded: false,
                flashes: ['error' => 'Vyčerpali jste všechny AI tokeny. Nelze přidávat další inzeráty.'],
            );
        }

        return new AddImagesResult(
            redirectNeeded: true,
            batchId: (int) $batch->getId(),
        );
    }
}
