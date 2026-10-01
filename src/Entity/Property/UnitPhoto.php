<?php

declare(strict_types=1);

namespace App\Entity\Property;

use App\Entity\Shared\TimestampedEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * UnitPhoto
 *
 * Package  : Property Management
 * Table    : unit_photo
 *
 * Galerie de photos d'une unité publiée sur la vitrine publique.
 *
 * Une fiche de location présentable exige presque toujours plusieurs photos
 * (pièces, façade, emplacement). D'où une table plutôt qu'une colonne unique :
 * `Organization.logo` et `User.profilePhoto` ne portent qu'une vignette
 * d'identité, ce qui ne suffit pas pour un bien mis en location.
 *
 * `position` ordonne la galerie et vaut 0 pour la photo de couverture.
 * L'ordre est stocké plutôt que déduit de `createdAt` : réordonner la galerie
 * ne doit pas dépendre de l'horloge ni ressembler à une création.
 *
 * Elle étend `TimestampedEntity` et non `CreatedOnlyEntity` : une photo peut
 * être retirée de la galerie, donc la ligne est supprimée physiquement
 * (suppression physique assumée ici, contrairement aux entités métier). Le
 * `ON DELETE CASCADE` couvre le cas de la suppression physique du bâtiment
 * parent ; il ne se déclenche pas sur un soft delete de `Unit`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'unit_photo')]
#[ORM\Index(name: 'idx_unit_photo_unit_position', columns: ['unit_id', 'position'])]
#[ORM\UniqueConstraint(name: 'uniq_unit_photo_uuid', columns: ['uuid'])]
class UnitPhoto extends TimestampedEntity
{
    #[ORM\ManyToOne(targetEntity: Unit::class)]
    #[ORM\JoinColumn(
        name: 'unit_id',
        referencedColumnName: 'id',
        nullable: false,
        onDelete: 'CASCADE'
    )]
    private Unit $unit;

    /**
     * Chemin relatif du fichier sous `public/uploads`, jamais absolu : un
     * chemin stocké en base ne doit pas dépendre du préfixe d'installation.
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $path;

    /**
     * Rang dans la galerie, 0 pour la photo de couverture.
     */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $position = 0;

    public function __construct(Unit $unit, string $path, int $position = 0)
    {
        parent::__construct();

        $this->unit = $unit;
        $this->path = $path;
        $this->position = $position;
    }

    public function getUnit(): Unit
    {
        return $this->unit;
    }

    public function setUnit(Unit $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}