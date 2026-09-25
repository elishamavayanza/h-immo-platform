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
}
