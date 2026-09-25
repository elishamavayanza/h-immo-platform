<?php

declare(strict_types=1);

namespace App\Entity\Property;

use App\Entity\Shared\SoftDeletableEntity;
use App\Enum\Currency;
use App\Enum\UnitType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Unit
 *
 * Package  : Property Management
 * Table    : unit
 *
 * Unité locative individuelle (appartement, boutique, bureau...)
 * appartenant à un Building. C'est l'entité effectivement mise en
 * location via un Lease. `reference` est unique par Building.
 *
 * Note : nommée `Unit` dans le modèle UML ; en base la table est
 * nommée explicitement `unit` (mot potentiellement réservé selon le
 * SGBD — à échapper si nécessaire dans les migrations).
 */
#[ORM\Entity]
#[ORM\Table(name: 'unit')]
#[ORM\UniqueConstraint(name: 'uniq_unit_building_reference', columns: ['building_id', 'reference'])]
class Unit extends SoftDeletableEntity
{
    /**
     * Bâtiment auquel l'unité locative appartient.
     */
    #[ORM\ManyToOne(targetEntity: Building::class)]
    #[ORM\JoinColumn(name: 'building_id', referencedColumnName: 'id', nullable: false)]
    private Building $building;

    /**
     * Référence unique de l'unité dans le bâtiment.
     */
    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $reference;

    /**
     * Type de l'unité locative.
     */
    #[ORM\Column(type: Types::STRING, enumType: UnitType::class)]
    private UnitType $type;

    /**
     * Étage où se situe l'unité.
     */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $floor;

    /**
     * Surface de l'unité en mètres carrés.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private string $surface;

    /**
     * Nombre de chambres de l'unité.
     */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $bedrooms = null;

    /**
     * Nombre de pièces de l'unité.
     */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $rooms = null;

    /**
     * Nombre de salles de bain de l'unité.
     */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $bathrooms = null;

    /**
     * Loyer mensuel demandé pour l'unité.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $monthlyRent;

    /**
     * Devise utilisée pour le loyer mensuel.
     */
    #[ORM\Column(type: Types::STRING, enumType: Currency::class)]
    private Currency $currency;

    /**
     * Description complémentaire de l'unité.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    public function getBuilding(): Building
    {
        return $this->building;
    }

    public function setBuilding(Building $building): static
    {
        $this->building = $building;

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

    public function getType(): UnitType
    {
        return $this->type;
    }

    public function setType(UnitType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getFloor(): int
    {
        return $this->floor;
    }

    public function setFloor(int $floor): static
    {
        $this->floor = $floor;

        return $this;
    }

    public function getSurface(): string
    {
        return $this->surface;
    }

    public function setSurface(string $surface): static
    {
        $this->surface = $surface;

        return $this;
    }

    public function getBedrooms(): ?int
    {
        return $this->bedrooms;
    }

    public function setBedrooms(?int $bedrooms): static
    {
        $this->bedrooms = $bedrooms;

        return $this;
    }

    public function getRooms(): ?int
    {
        return $this->rooms;
    }

    public function setRooms(?int $rooms): static
    {
        $this->rooms = $rooms;

        return $this;
    }

    public function getBathrooms(): ?int
    {
        return $this->bathrooms;
    }

    public function setBathrooms(?int $bathrooms): static
    {
        $this->bathrooms = $bathrooms;

        return $this;
    }

    public function getMonthlyRent(): string
    {
        return $this->monthlyRent;
    }

    public function setMonthlyRent(string $monthlyRent): static
    {
        $this->monthlyRent = $monthlyRent;

        return $this;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): static
    {
        $this->currency = $currency;

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
