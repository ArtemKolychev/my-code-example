<?php

declare(strict_types=1);

namespace App\EntryPoint\Action;

use App\Domain\Entity\User;
use App\EntryPoint\Service\Handler\UserCredentialsFormHandler;
use App\EntryPoint\Service\Handler\UserProfileFormHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile', name: 'app_user_profile')]
#[IsGranted('ROLE_USER')]
final class UserProfileAction extends AbstractController
{
    public function __construct(
        private readonly UserProfileFormHandler $userProfileFormHandler,
        private readonly UserCredentialsFormHandler $userCredentialsFormHandler,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $profileResult = $this->userProfileFormHandler->handle($user, $request);

        if ($profileResult->redirectNeeded) {
            $this->addFlash('success', 'Profil byl úspěšně uložen.');

            return $this->redirectToRoute('app_user_profile');
        }

        $credentialsResult = $this->userCredentialsFormHandler->handle($user, $request);

        if ($credentialsResult->redirectNeeded) {
            $this->addFlash('success', 'Přihlašovací údaje byly úspěšně uloženy.');

            return $this->redirectToRoute('app_user_profile');
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'articles' => $user->getArticles()->toArray(),
            'profileForm' => $profileResult->profileForm->createView(),
            'sbazarForm' => $credentialsResult->credentialForms['sbazar']->createView(),
            'hasSbazarCredential' => $credentialsResult->hasCredential['sbazar'],
            'bazosForm' => $credentialsResult->credentialForms['bazos']->createView(),
            'hasBazosCredential' => $credentialsResult->hasCredential['bazos'],
            'vintedForm' => $credentialsResult->credentialForms['vinted']->createView(),
            'hasVintedCredential' => $credentialsResult->hasCredential['vinted'],
            'motoinzerceForm' => $credentialsResult->credentialForms['motoinzerce']->createView(),
            'hasMotoinzerceCredential' => $credentialsResult->hasCredential['motoinzerce'],
        ]);
    }
}
