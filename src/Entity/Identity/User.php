<?php

declare(strict_types=1);

namespace App\Entity\Identity;

use App\Entity\Shared\SoftDeletableEntity;
use App\Enum\PlatformRole;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * User
 *
 * Package  : Identity & Access
 * Table    : user
 *
 * Représente un utilisateur authentifiable de la plateforme Soft-IMMO
 * (agent, administrateur d'organisation, patron, super-admin...).
 * Un même User peut être rattaché à plusieurs Organizations via la
 * table de liaison OrganizationUser, et à plusieurs Cities via UserCity
 * lorsqu'il porte le rôle ADMIN_VILLE.
 *
 * `platformRole` est distinct de `OrganizationRole` : il ne concerne
 * que le rôle SUPER_ADMIN, global à la plateforme, indépendant de
 * toute Organization.
 *
 * L'entité implémente les interfaces du composant Security : elle est
 * donc directement exploitable par le `security` bundle (provider,
 * authentificateur, `TokenStorage`). Elle n'expose volontairement
 * aucun rôle d'Organization via `getRoles()` : les rôles métier sont
 * résolus par `SecurityService` au sein de l'Organization concernée,
 * ce qui évite qu'une appartenance à une seule Organization accorde
 * des droits dans toutes les autres.
 */
#[ORM\Entity]
#[ORM\Table(name: 'user')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
class User extends SoftDeletableEntity implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * Adresse e-mail de connexion de l'utilisateur.
     */
    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    private string $email;

    /**
     * Mot de passe haché de l'utilisateur.
     * Ne doit JAMAIS être exposé dans un DTO de réponse (cf. UserResponse).
     * Peut être null temporairement lors de la création via OrganizationService
     * (le PATRON définit son mot de passe via le flux "mot de passe oublié").
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $password = null;

    /**
     * Nom complet de l'utilisateur.
     */
    #[ORM\Column(type: Types::STRING, length: 200)]
    private string $fullName;

    /**
     * Numéro de téléphone de l'utilisateur.
     */
    #[ORM\Column(type: Types::STRING, length: 30, nullable: true)]
    private ?string $phone = null;

    /**
     * Référence vers le fichier de photo de profil.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $profilePhoto = null;

    /**
     * Rôle global de l'utilisateur sur la plateforme.
     */
    #[ORM\Column(type: Types::STRING, nullable: true, enumType: PlatformRole::class)]
    private ?PlatformRole $platformRole = null;

    /**
     * Indique si le compte utilisateur est actif.
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    /**
     * Date et heure de la dernière connexion.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    /**
     * Préférences utilisateur (JSON) : thème, langue, notifications, etc.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $settings = null;

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getProfilePhoto(): ?string
    {
        return $this->profilePhoto;
    }

    public function setProfilePhoto(?string $profilePhoto): static
    {
        $this->profilePhoto = $profilePhoto;

        return $this;
    }

    public function getPlatformRole(): ?PlatformRole
    {
        return $this->platformRole;
    }

    public function setPlatformRole(?PlatformRole $platformRole): static
    {
        $this->platformRole = $platformRole;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    /**
     * Préférences utilisateur (thème, langue, notifications, etc.).
     */
    public function getSettings(): ?array
    {
        return $this->settings;
    }

    public function setSettings(?array $settings): static
    {
        $this->settings = $settings;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Security\Core\User\UserInterface
    |--------------------------------------------------------------------------
    */

    /**
     * Symfony 7 exige de renommer `getUsername()` : on expose
     * l'adresse e-mail, qui est l'identifiant de connexion du projet.
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * Un compte ne peut être authentifié que s'il est actif et non
     * supprimé logiquement. Ces deux garde-fous sont appliqués par
     * l'authentificateur de l'API.
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        if ($this->platformRole === PlatformRole::SUPER_ADMIN) {
            $roles[] = 'ROLE_SUPER_ADMIN';
        }

        return $roles;
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): void
    {
        // Les rôles sont dérivés de `platformRole` (voir getRoles()).
        // Cette méthode existe uniquement pour satisfaire l'interface.
    }

    public function eraseCredentials(): void
    {
        // Aucun secret en mémoire sur l'entité : le mot de passe est
        // déjà stocké haché en base. Rien à effacer.
    }

    /*
    |--------------------------------------------------------------------------
    | Security\Core\User\PasswordAuthenticatedUserInterface
    |--------------------------------------------------------------------------
    */
}
