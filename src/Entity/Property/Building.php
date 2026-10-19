<?php

declare(strict_types=1);

namespace App\Entity\Property;

use App\Entity\Shared\SoftDeletableEntity;
use App\Enum\BuildingType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Building
 *
 * Package  : Property Management
 * Table    : building
 *
 * Bâtiment physique construit sur une Parcel et regroupant un ou
 * plusieurs Unit locatifs. `reference` est unique par Parcel.
 */
#[ORM\Entity]
#[ORM\Table(name: 'building')]
#[ORM\UniqueConstraint(name: 'uniq_building_parcel_reference', columns: ['parcel_id', 'reference'])]
class Building extends SoftDeletableEntity
{
    /**
     * Parcelle sur laquelle le bâtiment est construit.
     */
    #[ORM\ManyToOne(targetEntity: Parcel::class)]
    #[ORM\JoinColumn(name: 'parcel_id', referencedColumnName: 'id', nullable: false)]
    private Parcel $parcel;

    /**
     * Référence unique du bâtiment dans la parcelle.
     */
    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $reference;

    /**
     * Nom du bâtiment.
     */
    #[ORM\Column(type: Types::STRING, length: 150)]
    private string $name;

    /**
     * Type de bâtiment.
     */
    #[ORM\Column(type: Types::STRING, enumType: BuildingType::class)]
    private BuildingType $type;

    /**
     * Nombre d'étages au-dessus du rez-de-chaussée ; 0 décrit un plain-pied.
     */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $numberOfFloors = null;

    /**
     * Description complémentaire du bâtiment.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Photos du bâtiment (noms de fichiers stockés sous buildings/{uuid}/).
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $photos = null;

    public function getParcel(): Parcel
    {
        return $this->parcel;
    }

    public function setParcel(Parcel $parcel): static
    {
        $this->parcel = $parcel;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getType(): BuildingType
    {
        return $this->type;
    }

    public function setType(BuildingType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getNumberOfFloors(): ?int
    {
        return $this->numberOfFloors;
    }

    public function setNumberOfFloors(?int $numberOfFloors): static
    {
        $this->numberOfFloors = $numberOfFloors;

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

    public function getPhotos(): array
    {
        return $this->photos ?? [];
    }

    public function setPhotos(?array $photos): static
    {
        $this->photos = $photos;

        return $this;
    }

    public function addPhoto(string $filename): static
    {
        $photos = $this->getPhotos();
        $photos[] = $filename;
        $this->photos = $photos;

        return $this;
    }

    public function removePhoto(string $filename): bool
    {
        $photos = $this->getPhotos();
        $key = array_search($filename, $photos, true);
        if ($key === false) {
            return false;
        }
        unset($photos[$key]);
        $this->photos = array_values($photos);

        return true;
    }
}
