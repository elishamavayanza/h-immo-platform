<?php

declare(strict_types=1);

namespace App\Entity\Identity;

use App\Entity\Shared\SoftDeletableEntity;
use App\Enum\OrganizationStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Organization
 *
 * Package  : Identity & Access
 * Table    : organization
 *
 * Entité racine de l'isolation multi-entreprise (multi-tenant) de
 * Soft-IMMO. Toute donnée métier (City, Parcel, Building, Unit,
 * Tenant, Lease, Rent, Payment, AuditLog) est rattachée, directement
 * ou indirectement, à une Organization, et aucune requête applicative
 * ne doit permettre de franchir cette frontière.
 */
#[ORM\Entity]
#[ORM\Table(name: 'organization')]
#[ORM\UniqueConstraint(name: 'uniq_organization_code', columns: ['code'])]
class Organization extends SoftDeletableEntity
{

    /**
     * Nom officiel de l'organisation.
     */
    #[ORM\Column(type: Types::STRING, length: 150)]
    private string $name;

    /**
     * Code unique utilisé pour identifier l'organisation.
     */
    #[ORM\Column(type: Types::STRING, length: 30, unique: true)]
    private string $code;

    /**
     * Référence vers le fichier du logo de l'organisation.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $logo = null;

    /**
     * Adresse e-mail officielle de l'organisation.
     */
    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $email;

    /**
     * Numéro de téléphone principal de l'organisation.
     */
    #[ORM\Column(type: Types::STRING, length: 30)]
    private string $phone;

    /**
     * Adresse physique de l'organisation.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $address = null;

    /**
     * Ville où se situe le siège de l'organisation.
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $city = null;

    /**
     * Pays où se situe le siège de l'organisation.
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $country = null;

    /**
     * État actuel de l'organisation dans la plateforme.
     */
    #[ORM\Column(type: Types::STRING, enumType: OrganizationStatus::class)]
    private OrganizationStatus $status = OrganizationStatus::ACTIVE;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

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

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getStatus(): OrganizationStatus
    {
        return $this->status;
    }

    public function setStatus(OrganizationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }
}
