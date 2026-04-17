<?php

declare(strict_types=1);

namespace App\EntryPoint\Action;

use App\Domain\Entity\User;
use App\EntryPoint\Service\Handler\AddImagesFormHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/market/article/add_images', name: 'app_article_images')]
#[IsGranted('ROLE_USER')]
final class AddImagesAction extends AbstractController
{
    public function __construct(
        private readonly AddImagesFormHandler $addImagesFormHandler,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->addImagesFormHandler->createForm();
        $result = $this->addImagesFormHandler->handle($form, $user, $request);

        foreach ($result->flashes as $type => $message) {
            $this->addFlash($type, $message);
        }

        if ($result->redirectNeeded) {
            return $this->redirectToRoute('app_processing', ['batchId' => $result->batchId]);
        }

        return $this->render('market/images_loader.html.twig', [
            'form' => $form->createView(),
            'tokenBalance' => $user->getTokenBalance(),
        ]);
    }
}
