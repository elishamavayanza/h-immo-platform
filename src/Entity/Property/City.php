<?php

declare(strict_types=1);

namespace App\Entity\Property;

use App\Entity\Identity\Organization;
use App\Entity\Shared\SoftDeletableEntity;
use App\Enum\CityStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * City
 *
 * Package  : Property Management
 * Table    : city
 *
 * Ville dans laquelle une Organization gère un parc immobilier.
 * Racine du sous-arbre patrimonial : City -> Parcel -> Building -> Unit.
 * Le code est unique par Organization (et non globalement).
 */
#[ORM\Entity]
#[ORM\Table(name: 'city')]
#[ORM\UniqueConstraint(name: 'uniq_city_org_code', columns: ['organization_id', 'code'])]
class City extends SoftDeletableEntity
{
    /**
     * Organisation qui gère la ville.
     */
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false)]
    private Organization $organization;

    /**
     * Nom de la ville.
     */
    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $name;

    /**
     * Code unique de la ville dans l'organisation.
     */
    #[ORM\Column(type: Types::STRING, length: 30)]
    private string $code;

    /**
     * Province dans laquelle se situe la ville.
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $province = null;

    /**
     * Pays dans lequel se situe la ville.
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $country = null;

    /**
     * État actuel de la ville.
     */
    #[ORM\Column(type: Types::STRING, enumType: CityStatus::class)]
    private CityStatus $status = CityStatus::ACTIVE;

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function setOrganization(Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

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

    public function getProvince(): ?string
    {
        return $this->province;
    }

    public function setProvince(?string $province): static
    {
        $this->province = $province;

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

    public function getStatus(): CityStatus
    {
        return $this->status;
    }

    public function setStatus(CityStatus $status): static
    {
        $this->status = $status;

        return $this;
    }
}
