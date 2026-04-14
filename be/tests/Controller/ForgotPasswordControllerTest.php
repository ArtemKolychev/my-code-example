<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Kernel;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ForgotPasswordControllerTest extends WebTestCase
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

        foreach ($userRepository->findAll() as $user) {
            $em->remove($user);
        }

        $em->flush();

        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $container->get('security.user_password_hasher');

        $user = (new User())->setEmail('user@example.com');
        $user->setPassword($passwordHasher->hashPassword($user, 'password'));

        $em->persist($user);
        $em->flush();
    }

    public function testGetForgotPasswordReturns200(): void
    {
        $this->client->request('GET', '/forgot-password');

        self::assertResponseIsSuccessful();
    }

    public function testPostForgotPasswordWithKnownEmailRedirectsWithFlash(): void
    {
        $this->client->request('POST', '/forgot-password', ['email' => 'user@example.com']);

        self::assertResponseRedirects('/forgot-password');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'If that email address is in our database');
    }

    public function testPostForgotPasswordWithUnknownEmailShowsSameMessage(): void
    {
        $this->client->request('POST', '/forgot-password', ['email' => 'nobody@example.com']);

        self::assertResponseRedirects('/forgot-password');
        $this->client->followRedirect();

        // Same message regardless of whether the email exists (no enumeration)
        self::assertSelectorTextContains('.alert-success', 'If that email address is in our database');
    }

    public function testGetResetPasswordWithInvalidTokenRedirectsWithError(): void
    {
        $this->client->request('GET', '/reset-password/invalidtoken');

        self::assertResponseRedirects('/forgot-password');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'invalid or has expired');
    }

    public function testGetResetPasswordWithValidTokenReturns200(): void
    {
        $token = $this->createValidTokenForUser('user@example.com');

        $this->client->request('GET', '/reset-password/'.$token);

        self::assertResponseIsSuccessful();
    }

    public function testGetResetPasswordWithExpiredTokenRedirectsWithError(): void
    {
        $expiredToken = $this->createExpiredTokenForUser('user@example.com');

        $this->client->request('GET', '/reset-password/'.$expiredToken);

        self::assertResponseRedirects('/forgot-password');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'invalid or has expired');
    }

    public function testPostResetPasswordWithValidTokenResetsPasswordAndRedirects(): void
    {
        $token = $this->createValidTokenForUser('user@example.com');

        $this->client->request('POST', '/reset-password/'.$token, [
            'password' => 'newpassword123',
            'confirm_password' => 'newpassword123',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-success', 'Your password has been reset');

        // Reset the identity map so we read from DB, not from in-memory cache
        $container = static::getContainer();
        $doctrine = $container->get('doctrine');
        $doctrine->resetManager();
        $em = $doctrine->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'user@example.com']);

        $this->assertNotNull($user);
        $this->assertNull($user->getResetPasswordToken());
        $this->assertNull($user->getResetPasswordTokenExpiresAt());
    }

    public function testPostResetPasswordTokenCannotBeReusedAfterSuccessfulReset(): void
    {
        $token = $this->createValidTokenForUser('user@example.com');

        $this->client->request('POST', '/reset-password/'.$token, [
            'password' => 'newpassword123',
            'confirm_password' => 'newpassword123',
        ]);
        self::assertResponseRedirects('/login');

        // Second attempt with the same token must fail
        $this->client->request('POST', '/reset-password/'.$token, [
            'password' => 'anotherpassword456',
            'confirm_password' => 'anotherpassword456',
        ]);

        self::assertResponseRedirects('/forgot-password');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'invalid or has expired');
    }

    public function testNewPasswordIsUsableAfterReset(): void
    {
        $token = $this->createValidTokenForUser('user@example.com');

        $this->client->request('POST', '/reset-password/'.$token, [
            'password' => 'newpassword123',
            'confirm_password' => 'newpassword123',
        ]);
        self::assertResponseRedirects('/login');

        // Attempt login with new password
        $this->client->request('POST', '/login', [
            '_username' => 'user@example.com',
            '_password' => 'newpassword123',
        ]);

        // Successful login redirects away from /login
        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertNotSame('/login', $location);
        self::assertResponseRedirects();
    }

    public function testPostResetPasswordWithMismatchedPasswordsShowsError(): void
    {
        $token = $this->createValidTokenForUser('user@example.com');

        $this->client->request('POST', '/reset-password/'.$token, [
            'password' => 'newpassword123',
            'confirm_password' => 'differentpassword',
        ]);

        // Should re-render the form with an error, not redirect to login
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    private function createValidTokenForUser(string $email): string
    {
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        $user->setResetPasswordToken($hashedToken);
        $user->setResetPasswordTokenExpiresAt(new DateTimeImmutable('+1 hour'));
        $em->flush();

        return $rawToken;
    }

    private function createExpiredTokenForUser(string $email): string
    {
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        $user->setResetPasswordToken($hashedToken);
        $user->setResetPasswordTokenExpiresAt(new DateTimeImmutable('-1 hour'));
        $em->flush();

        return $rawToken;
    }
}
