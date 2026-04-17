<?php

namespace App\Tests;

use App\Domain\Entity\User;
use App\Kernel;
use App\Tests\Shared\Mother\UserMother;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegistrationControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    #[Override]
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testRegistrationSuccess(): void
    {
        // Test successful registration
        $this->client->request(Request::METHOD_GET, '/register');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Register', [
            'register[email]' => 'new@example.com',
            'register[password][first]' => 'password123',
            'register[password][second]' => 'password123',
            'register[agreeTerms]' => '1',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        // Verify the user was created in the database
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $userRepository = $em->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => 'new@example.com']);

        self::assertNotNull($user);
    }

    public function testRegistrationWithExistingEmail(): void
    {
        // Test registration with an email that's already in use
        $this->client->request(Request::METHOD_GET, '/register');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Register', [
            'register[email]' => 'existing@example.com',
            'register[password][first]' => 'password123',
            'register[password][second]' => 'password123',
            'register[agreeTerms]' => '1',
        ]);

        // Should stay on the registration page with an error
        self::assertResponseStatusCodeSame(200);
    }

    public function testRegistrationWithInvalidPassword(): void
    {
        // Test registration with a password that's too short
        $this->client->request(Request::METHOD_GET, '/register');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Register', [
            'register[email]' => 'another@example.com',
            'register[password][first]' => 'short',
            'register[password][second]' => 'short',
            'register[agreeTerms]' => '1',
        ]);

        // Should stay on the registration page with an error
        self::assertResponseStatusCodeSame(200);
    }

    public function testRegistrationWithMismatchedPasswords(): void
    {
        // Test registration with passwords that don't match
        $this->client->request(Request::METHOD_GET, '/register');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Register', [
            'register[email]' => 'another@example.com',
            'register[password][first]' => 'password123',
            'register[password][second]' => 'different123',
            'register[agreeTerms]' => '1',
        ]);

        // Should stay on the registration page with an error
        self::assertResponseStatusCodeSame(200);
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $userRepository = $em->getRepository(User::class);

        // Remove any existing users from the test database
        foreach ($userRepository->findAll() as $user) {
            $em->remove($user);
        }

        $em->flush();

        // Create a User fixture for testing duplicate email
        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $container->get('security.user_password_hasher');

        $user = UserMother::withEmail('existing@example.com');
        $user->setPassword($passwordHasher->hashPassword($user, 'password'));

        $em->persist($user);
        $em->flush();
    }
}
