<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeImmutable;
use Deprecated;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 180)]
    public ?string $email = null;

    #[ORM\Column(length: 100, nullable: true)]
    public ?string $name = null;

    #[ORM\Column(length: 250, nullable: true)]
    public ?string $address = null;

    #[ORM\Column(length: 10, nullable: true)]
    public ?string $zip = null;

    #[ORM\Column(length: 20, nullable: true)]
    public ?string $phone = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    public array $roles = [];

    #[ORM\OneToMany(targetEntity: Article::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    /** @var Collection<int, Article> */
    public Collection $articles;

    #[ORM\OneToMany(targetEntity: ServiceCredential::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    /** @var Collection<int, ServiceCredential> */
    private Collection $serviceCredentials;

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    public ?string $password = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $resetPasswordToken = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $resetPasswordTokenExpiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $termsAcceptedAt = null;

    #[ORM\Column(type: 'integer', options: ['default' => 100000])]
    private int $tokenBalance = 100000;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
        $this->serviceCredentials = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Article $article): static
    {
        if (!$this->articles->contains($article)) {
            $this->articles[] = $article;
            $article->setUser($this);
        }

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getZip(): ?string
    {
        return $this->zip;
    }

    public function setZip(?string $zip): static
    {
        $this->zip = $zip;

        return $this;
    }

    public function setPhone(?string $phone): User
    {
        $this->phone = $phone;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getServiceCredentials(): Collection
    {
        return $this->serviceCredentials;
    }

    public function getCredentialForService(string $service): ?ServiceCredential
    {
        foreach ($this->serviceCredentials as $credential) {
            if ($credential->getService() === $service) {
                return $credential;
            }
        }

        return null;
    }

    public function getResetPasswordToken(): ?string
    {
        return $this->resetPasswordToken;
    }

    public function setResetPasswordToken(?string $resetPasswordToken): static
    {
        $this->resetPasswordToken = $resetPasswordToken;

        return $this;
    }

    public function getResetPasswordTokenExpiresAt(): ?DateTimeImmutable
    {
        return $this->resetPasswordTokenExpiresAt;
    }

    public function setResetPasswordTokenExpiresAt(?DateTimeImmutable $resetPasswordTokenExpiresAt): static
    {
        $this->resetPasswordTokenExpiresAt = $resetPasswordTokenExpiresAt;

        return $this;
    }

    public function getTermsAcceptedAt(): ?DateTimeImmutable
    {
        return $this->termsAcceptedAt;
    }

    public function setTermsAcceptedAt(?DateTimeImmutable $termsAcceptedAt): static
    {
        $this->termsAcceptedAt = $termsAcceptedAt;

        return $this;
    }

    public function getTokenBalance(): int
    {
        return $this->tokenBalance;
    }

    public function deductTokens(int $tokens): void
    {
        $this->tokenBalance = max(0, $this->tokenBalance - $tokens);
    }

    public function hasTokens(): bool
    {
        return $this->tokenBalance > 0;
    }
}
