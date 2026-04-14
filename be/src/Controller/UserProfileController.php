<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\UserProfileType;
use App\Service\ServiceCredentialManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ServiceCredentialManager $credentialManager,
    ) {
    }

    #[Route('/profile', name: 'app_user_profile')]
    #[IsGranted('ROLE_USER')]
    public function index(Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $articles = $user->getArticles();

        // Handle user profile form (address + phone)
        $profileForm = $this->createForm(UserProfileType::class, [
            'name' => $user->getName(),
            'address' => $user->getAddress(),
            'zip' => $user->getZip(),
            'phone' => $user->getPhone(),
        ]);

        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            /** @var array{name?: string|null, address?: string|null, zip?: string|null, phone?: string|null} $profileData */
            $profileData = $profileForm->getData();
            $user->setName($profileData['name'] ?? null);
            $user->setAddress($profileData['address'] ?? null);
            $user->setZip($profileData['zip'] ?? null);
            $user->setPhone($profileData['phone'] ?? null);
            $this->entityManager->flush();

            $this->addFlash('success', 'Profil byl úspěšně uložen.');

            return $this->redirectToRoute('app_user_profile');
        }

        // Handle service credential forms
        $services = [
            'sbazar' => [],
            'vinted' => [],
            'bazos' => ['plain_password' => true],
            'motoinzerce' => ['plain_password' => true, 'show_login' => false],
        ];

        $credentialForms = [];
        foreach ($services as $service => $options) {
            $result = $this->credentialManager->handleCredentialForm($service, $user, $request, $options);

            if ($result['saved']) {
                $this->addFlash('success', ucfirst($service).' credentials saved successfully.');

                return $this->redirectToRoute('app_user_profile');
            }

            $credentialForms[$service] = $result;
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'articles' => $articles,
            'profileForm' => $profileForm->createView(),
            'sbazarForm' => $credentialForms['sbazar']['form']->createView(),
            'hasSbazarCredential' => $credentialForms['sbazar']['hasCredential'],
            'bazosForm' => $credentialForms['bazos']['form']->createView(),
            'hasBazosCredential' => $credentialForms['bazos']['hasCredential'],
            'vintedForm' => $credentialForms['vinted']['form']->createView(),
            'hasVintedCredential' => $credentialForms['vinted']['hasCredential'],
            'motoinzerceForm' => $credentialForms['motoinzerce']['form']->createView(),
            'hasMotoinzerceCredential' => $credentialForms['motoinzerce']['hasCredential'],
        ]);
    }
}
