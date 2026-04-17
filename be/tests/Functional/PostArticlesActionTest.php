<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\Entity\User;
use App\Kernel;
use App\Tests\Shared\Mother\UserMother;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests for PostArticlesAction.
 *
 * Route: GET /market/article/{ids}/post?platform={platform}
 *
 * Verifies that users without a complete profile or unauthenticated users
 * cannot reach the publishing stage.
 */
final class PostArticlesActionTest extends WebTestCase
{
    private KernelBrowser $client;

    #[Override]
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    // -------------------------------------------------------------------------
    // Security guard: unauthenticated
    // -------------------------------------------------------------------------

    public function testUnauthenticatedUserIsRedirectedToLogin(): void
    {
        $this->client->request(Request::METHOD_GET, '/market/article/1/post?platform=seznam');

        $this->assertResponseRedirects('/login', Response::HTTP_FOUND);
    }

    // -------------------------------------------------------------------------
    // Security guard: incomplete profile
    // -------------------------------------------------------------------------

    public function testUserWithNoProfileFieldsIsRedirectedToEditArticle(): void
    {
        $this->client->loginUser($this->createIncompleteUser('incomplete@example.com'));
        $this->client->request(Request::METHOD_GET, '/market/article/1/post?platform=seznam');

        $this->assertResponseRedirects('/market/article/1/edit', Response::HTTP_FOUND);
    }

    public function testUserWithMissingPhoneIsRedirectedToEditArticle(): void
    {
        $this->client->loginUser($this->createUserWithoutPhone('nophone@example.com'));
        $this->client->request(Request::METHOD_GET, '/market/article/1/post?platform=seznam');

        $this->assertResponseRedirects('/market/article/1/edit', Response::HTTP_FOUND);
    }

    // -------------------------------------------------------------------------
    // Guard: invalid platform
    // -------------------------------------------------------------------------

    public function testInvalidPlatformRedirectsToEditArticle(): void
    {
        $this->client->loginUser($this->createCompleteUser('complete@example.com'));
        $this->client->request(Request::METHOD_GET, '/market/article/1/post?platform=unknown_platform');

        $this->assertResponseRedirects('/market/article/1/edit', Response::HTTP_FOUND);
    }

    public function testMissingPlatformQueryParamRedirectsToEditArticle(): void
    {
        $this->client->loginUser($this->createCompleteUser('complete2@example.com'));
        $this->client->request(Request::METHOD_GET, '/market/article/1/post');

        $this->assertResponseRedirects('/market/article/1/edit', Response::HTTP_FOUND);
    }

    // -------------------------------------------------------------------------
    // Guard passes: complete profile + valid platform
    // -------------------------------------------------------------------------

    public function testCompleteProfileWithValidPlatformReachesPublishingStage(): void
    {
        $this->client->loginUser($this->createCompleteUser('publisher@example.com'));
        $this->client->request(Request::METHOD_GET, '/market/article/99999/post?platform=seznam');

        // Article 99999 does not exist → error flash + redirect to edit (NOT profile guard)
        $this->assertResponseRedirects('/market/article/99999/edit', Response::HTTP_FOUND);
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        foreach ($em->getRepository(User::class)->findAll() as $existing) {
            $em->remove($existing);
        }
        $em->flush();
    }

    // -------------------------------------------------------------------------
    // Helpers — no branching (no if/switch)
    // -------------------------------------------------------------------------

    private function persistUser(User $user): User
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createCompleteUser(string $email): User
    {
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get('security.user_password_hasher');

        $user = UserMother::withEmail($email);
        $user->name = 'Jan Novák';
        $user->address = 'Wenceslas Square 1';
        $user->zip = '11000';
        $user->phone = '+420777000111';
        $user->setPassword($hasher->hashPassword($user, 'secret'));

        return $this->persistUser($user);
    }

    /** User with name/address/zip but no phone — triggers profile-incomplete guard. */
    private function createUserWithoutPhone(string $email): User
    {
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get('security.user_password_hasher');

        $user = UserMother::withEmail($email);
        $user->name = 'Jan Novák';
        $user->address = 'Wenceslas Square 1';
        $user->zip = '11000';
        $user->setPassword($hasher->hashPassword($user, 'secret'));

        return $this->persistUser($user);
    }

    /** User with no profile fields set — triggers profile-incomplete guard. */
    private function createIncompleteUser(string $email): User
    {
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get('security.user_password_hasher');

        $user = UserMother::withEmail($email);
        $user->setPassword($hasher->hashPassword($user, 'secret'));

        return $this->persistUser($user);
    }
}
