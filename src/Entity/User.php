<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\UserRepository;
use App\State\UserPasswordHasherProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(
            validationContext: ['groups' => ['Default', 'user:create']],
            processor: UserPasswordHasherProcessor::class
        ),
        new Patch(
            processor: UserPasswordHasherProcessor::class
        ),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:write']]
)]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[Groups(['user:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['user:read', 'user:write'])]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    /**
     * Mot de passe hashé (stocké en DB).
     * IMPORTANT : write-only (jamais renvoyé par l'API)
     */
    #[ORM\Column]
    private ?string $password = null;

    /**
     * Mot de passe en clair envoyé par le front.
     * NON persisté en DB.
     */
    #[Groups(['user:write'])]
    #[Assert\NotBlank(groups: ['user:create'])]
    #[Assert\Length(min: 8, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.')]
    private ?string $plainPassword = null;

    #[Groups(['user:read', 'user:write'])]
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[Groups(['user:read', 'user:write'])]
    #[Assert\NotBlank]
    #[ORM\Column(length: 255)]
    private ?string $firstName = null;

    #[Groups(['user:read', 'user:write'])]
    #[Assert\NotBlank]
    #[ORM\Column(length: 255)]
    private ?string $lastName = null;

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

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * PasswordAuthenticatedUserInterface
     * -> retourne le mot de passe HASHÉ stocké
     */
    public function getPassword(): string
    {
        return (string) $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function eraseCredentials(): void
    {
        // Sécurité : on nettoie le plainPassword après usage
        $this->plainPassword = null;
    }
}
