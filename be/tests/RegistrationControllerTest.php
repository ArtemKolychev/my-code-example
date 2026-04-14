<?php

namespace App\Tests;

use App\Entity\User;
use App\Kernel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegistrationControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
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

        $user = (new User())->setEmail('existing@example.com');
        $user->setPassword($passwordHasher->hashPassword($user, 'password'));

        $em->persist($user);
        $em->flush();
    }

    public function testRegistrationSuccess(): void
    {
        // Test successful registration
        $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Register', [
            'form[email]' => 'new@example.com',
            'form[password][first]' => 'password123',
            'form[password][second]' => 'password123',
            'form[agreeTerms]' => '1',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        // Verify the user was created in the database
        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $userRepository = $em->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => 'new@example.com']);

        self::assertNotNull($user);
    }

    public function testRegistrationWithExistingEmail(): void
    {
        // Test registration with an email that's already in use
        $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Register', [
            'form[email]' => 'existing@example.com',
            'form[password][first]' => 'password123',
            'form[password][second]' => 'password123',
            'form[agreeTerms]' => '1',
        ]);

        // Should stay on the registration page with an error
        self::assertResponseStatusCodeSame(200);
    }

    public function testRegistrationWithInvalidPassword(): void
    {
        // Test registration with a password that's too short
        $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Register', [
            'form[email]' => 'another@example.com',
            'form[password][first]' => 'short',
            'form[password][second]' => 'short',
            'form[agreeTerms]' => '1',
        ]);

        // Should stay on the registration page with an error
        self::assertResponseStatusCodeSame(200);
    }

    public function testRegistrationWithMismatchedPasswords(): void
    {
        // Test registration with passwords that don't match
        $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Register', [
            'form[email]' => 'another@example.com',
            'form[password][first]' => 'password123',
            'form[password][second]' => 'different123',
            'form[agreeTerms]' => '1',
        ]);

        // Should stay on the registration page with an error
        self::assertResponseStatusCodeSame(200);
    }
}
