<?php

declare(strict_types=1);

namespace App\Entity\Property;

use App\Entity\Shared\SoftDeletableEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Parcel
 *
 * Package  : Property Management
 * Table    : parcel
 *
 * Parcelle foncière (terrain cadastré) rattachée à une City et pouvant
 * porter un ou plusieurs Building. `reference` est unique par City.
 */
#[ORM\Entity]
#[ORM\Table(name: 'parcel')]
#[ORM\UniqueConstraint(name: 'uniq_parcel_city_reference', columns: ['city_id', 'reference'])]
class Parcel extends SoftDeletableEntity
{
    /**
     * Ville à laquelle la parcelle est rattachée.
     */
    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn(name: 'city_id', referencedColumnName: 'id', nullable: false)]
    private City $city;

    /**
     * Référence unique de la parcelle dans la ville.
     */
    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $reference;

    /**
     * Numéro officiel du titre foncier.
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $titleNumber = null;

    /**
     * Nom attribué à la parcelle.
     */
    #[ORM\Column(type: Types::STRING, length: 150)]
    private string $name;

    /**
     * Adresse physique de la parcelle.
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $address;

    /**
     * Quartier où se situe la parcelle.
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $quarter = null;

    /**
     * Superficie de la parcelle.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $area;

    /**
     * Latitude géographique de la parcelle.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    /**
     * Longitude géographique de la parcelle.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $longitude = null;

    /**
     * Description complémentaire de la parcelle.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    public function getCity(): City
    {
        return $this->city;
    }

    public function setCity(City $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getTitleNumber(): ?string
    {
        return $this->titleNumber;
    }

    public function setTitleNumber(?string $titleNumber): static
    {
        $this->titleNumber = $titleNumber;

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

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getQuarter(): ?string
    {
        return $this->quarter;
    }

    public function setQuarter(?string $quarter): static
    {
        $this->quarter = $quarter;

        return $this;
    }

    public function getArea(): string
    {
        return $this->area;
    }

    public function setArea(string $area): static
    {
        $this->area = $area;

        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }
}
