<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ServiceCredentialRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServiceCredentialRepository::class)]
#[ORM\Table(name: 'service_credential')]
#[ORM\UniqueConstraint(name: 'unique_user_service', columns: ['user_id', 'service'])]
class ServiceCredential
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore property.unusedType (Doctrine hydrates this field) */
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'serviceCredentials')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    private ?string $service = null;

    #[ORM\Column(length: 255)]
    private ?string $login = null;

    #[ORM\Column(type: 'text')]
    private ?string $encryptedPassword = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getService(): ?string
    {
        return $this->service;
    }

    public function setService(string $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function getLogin(): ?string
    {
        return $this->login;
    }

    public function setLogin(string $login): static
    {
        $this->login = $login;

        return $this;
    }

    public function getEncryptedPassword(): ?string
    {
        return $this->encryptedPassword;
    }

    public function setEncryptedPassword(string $encryptedPassword): static
    {
        $this->encryptedPassword = $encryptedPassword;

        return $this;
    }

    public function setPassword(string $plainPassword, string $appSecret): static
    {
        $key = sodium_crypto_generichash($appSecret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = sodium_crypto_secretbox($plainPassword, $nonce, $key);
        $this->encryptedPassword = base64_encode($nonce.$encrypted);

        return $this;
    }

    public function getDecryptedPassword(string $appSecret): ?string
    {
        if (null === $this->encryptedPassword) {
            return null;
        }

        $key = sodium_crypto_generichash($appSecret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $decoded = base64_decode($this->encryptedPassword);
        $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        if (false === $plaintext) {
            return null;
        }

        return $plaintext;
    }
}
