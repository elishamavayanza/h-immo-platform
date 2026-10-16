<?php

declare(strict_types=1);

namespace App\Entity\Property;

use App\Entity\Shared\SoftDeletableEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Point d’accès physique, indépendant des unités et partageable entre plusieurs espaces. */
#[ORM\Entity]
#[ORM\Table(name: 'access_point')]
#[ORM\UniqueConstraint(name: 'uniq_access_point_parcel_reference', columns: ['parcel_id', 'reference'])]
class AccessPoint extends SoftDeletableEntity
{
    #[ORM\ManyToOne(targetEntity: Parcel::class)]
    #[ORM\JoinColumn(name: 'parcel_id', referencedColumnName: 'id', nullable: false)]
    private Parcel $parcel;

    #[ORM\ManyToOne(targetEntity: Building::class)]
    #[ORM\JoinColumn(name: 'building_id', referencedColumnName: 'id', nullable: true)]
    private ?Building $building = null;

    /** @var Collection<int, Unit> Espaces desservis; vide pour une entrée de bâtiment ou de parcelle. */
    #[ORM\ManyToMany(targetEntity: Unit::class)]
    #[ORM\JoinTable(name: 'access_point_unit', joinColumns: [new ORM\JoinColumn(name: 'access_point_id', referencedColumnName: 'id', onDelete: 'CASCADE')], inverseJoinColumns: [new ORM\JoinColumn(name: 'unit_id', referencedColumnName: 'id', onDelete: 'RESTRICT')])]
    private Collection $units;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $reference;

    #[ORM\Column(type: Types::STRING, length: 120)]
    private string $label;

    #[ORM\Column(type: Types::STRING, length: 30)]
    private string $type;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $accessMode = 'shared';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $locationDescription = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $enabled = true;

    public function __construct()
    {
        parent::__construct();
        $this->units = new ArrayCollection();
    }

    public function getParcel(): Parcel { return $this->parcel; }
    public function setParcel(Parcel $parcel): static { $this->parcel = $parcel; return $this; }
    public function getBuilding(): ?Building { return $this->building; }
    public function setBuilding(?Building $building): static { $this->building = $building; return $this; }
    /** @return Collection<int, Unit> */ public function getUnits(): Collection { return $this->units; }
    public function setUnits(iterable $units): static { $this->units->clear(); foreach ($units as $unit) $this->units->add($unit); return $this; }
    public function getReference(): string { return $this->reference; }
    public function setReference(string $value): static { $this->reference = $value; return $this; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $value): static { $this->label = $value; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $value): static { $this->type = $value; return $this; }
    public function getAccessMode(): string { return $this->accessMode; }
    public function setAccessMode(string $value): static { $this->accessMode = $value; return $this; }
    public function getLocationDescription(): ?string { return $this->locationDescription; }
    public function setLocationDescription(?string $value): static { $this->locationDescription = $value; return $this; }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $value): static { $this->enabled = $value; return $this; }
}
