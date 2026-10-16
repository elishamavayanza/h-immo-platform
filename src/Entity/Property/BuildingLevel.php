<?php

declare(strict_types=1);

namespace App\Entity\Property;

use App\Entity\Shared\SoftDeletableEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Niveau descriptif d'un bâtiment, indépendant de ses unités locatives. */
#[ORM\Entity]
#[ORM\Table(name: 'building_level')]
#[ORM\UniqueConstraint(name: 'uniq_building_level_reference', columns: ['building_id', 'reference'])]
#[ORM\Index(name: 'idx_building_level_order', columns: ['building_id', 'display_order'])]
class BuildingLevel extends SoftDeletableEntity
{
    #[ORM\ManyToOne(targetEntity: Building::class)]
    #[ORM\JoinColumn(name: 'building_id', referencedColumnName: 'id', nullable: false)]
    private Building $building;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $reference;

    #[ORM\Column(type: Types::STRING, length: 120)]
    private string $label;

    #[ORM\Column(name: 'display_order', type: Types::INTEGER)]
    private int $displayOrder;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    public function getBuilding(): Building { return $this->building; }
    public function setBuilding(Building $building): static { $this->building = $building; return $this; }
    public function getReference(): string { return $this->reference; }
    public function setReference(string $reference): static { $this->reference = $reference; return $this; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): static { $this->label = $label; return $this; }
    public function getDisplayOrder(): int { return $this->displayOrder; }
    public function setDisplayOrder(int $displayOrder): static { $this->displayOrder = $displayOrder; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
}
