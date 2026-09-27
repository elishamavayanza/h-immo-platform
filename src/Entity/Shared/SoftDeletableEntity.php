<?php

declare(strict_types=1);

namespace App\Entity\Shared;

use Doctrine\ORM\Mapping as ORM;

/**
 * SoftDeletableEntity
 *
 * Classe mère abstraite ajoutant la suppression logique (soft delete)
 * au-dessus de TimestampedEntity. Une entité est considérée comme
 * supprimée lorsque `deletedAt` n'est pas nul.
 *
 * Le filtrage automatique des lignes supprimées (ex. via un Doctrine
 * Filter `softdeleteable`) est une préoccupation d'infrastructure et
 * n'est donc pas codé ici, conformément à la règle « pas de fonction
 * dans les entités ».
 */
#[ORM\MappedSuperclass]
abstract class SoftDeletableEntity extends TimestampedEntity
{
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    /**
     * Date et heure de suppression logique de l'entité, ou null si elle est active.
     */
    protected ?\DateTimeImmutable $deletedAt = null;

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    /**
     * Marque l'entité comme supprimée logiquement.
     *
     * Raccourci symétrique de `setDeletedAt(new \DateTimeImmutable())`,
     * factorisé ici pour que les services n'aient pas à répéter l'horloge
     * système à chaque suppression. Il ne s'agit pas de logique métier :
     * la décision de supprimer (et les contrôles d'accès qui la
     * précèdent) restent dans les services.
     */
    public function softDelete(): static
    {
        $this->deletedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Annule une suppression logique (restaure la ligne).
     */
    public function restore(): static
    {
        $this->deletedAt = null;

        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }
}
