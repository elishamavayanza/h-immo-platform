<?php

declare(strict_types=1);

namespace App\Entity\Rental;

use App\Entity\Identity\Organization;
use App\Entity\Shared\SoftDeletableEntity;
use App\Enum\TenantType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tenant
 *
 * Package  : Rental Management
 * Table    : tenant
 *
 * Locataire (personne physique ou morale) rattaché directement à une
 * Organization. Un même Tenant peut signer plusieurs Lease au fil du
 * temps, éventuellement sur des Unit différentes.
 */
#[ORM\Entity]
#[ORM\Table(name: 'tenant')]
class Tenant extends SoftDeletableEntity
{
    /**
     * Organisation à laquelle le locataire est rattaché.
     */
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false)]
    private Organization $organization;

    /**
     * Type de locataire.
     */
    #[ORM\Column(type: Types::STRING, enumType: TenantType::class)]
    private TenantType $type;

    /**
     * Nom complet du locataire personne physique.
     */
    #[ORM\Column(type: Types::STRING, length: 200, nullable: true)]
    private ?string $fullName = null;

    /**
     * Nom de l'entreprise locataire.
     */
    #[ORM\Column(type: Types::STRING, length: 150, nullable: true)]
    private ?string $companyName = null;

    /**
     * Numéro de téléphone du locataire.
     */
    #[ORM\Column(type: Types::STRING, length: 30)]
    private string $phone;

    /**
     * Adresse e-mail du locataire.
     */
    #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
    private ?string $email = null;

    /**
     * Adresse du locataire.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $address = null;

    /**
     * Notes complémentaires sur le locataire.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function setOrganization(Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    public function getType(): TenantType
    {
        return $this->type;
    }

    public function setType(TenantType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(?string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): static
    {
        $this->companyName = $companyName;

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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }
}
