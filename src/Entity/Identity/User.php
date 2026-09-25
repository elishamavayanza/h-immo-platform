<?php

declare(strict_types=1);

namespace App\Entity\Identity;

use App\Entity\Shared\SoftDeletableEntity;
use App\Enum\PlatformRole;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

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
 */
#[ORM\Entity]
#[ORM\Table(name: 'user')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
class User extends SoftDeletableEntity
{
    /**
     * Adresse e-mail de connexion de l'utilisateur.
     */
    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    private string $email;

    /**
     * Mot de passe haché de l'utilisateur.
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $password;

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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
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
}
