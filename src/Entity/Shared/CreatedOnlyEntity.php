<?php

declare(strict_types=1);

namespace App\Entity\Shared;

use Doctrine\ORM\Mapping as ORM;

/**
 * CreatedOnlyEntity
 *
 * Classe mère abstraite pour les entités immuables une fois créées
 * (journalisation, tables de liaison pures) : uniquement `id`, `uuid`
 * et `createdAt`. Pas de `updatedAt` ni de `deletedAt`, ces objets
 * n'étant jamais modifiés ni supprimés logiquement.
 */
#[ORM\MappedSuperclass]
abstract class CreatedOnlyEntity extends BaseEntity
{
    #[ORM\Column(type: 'datetime_immutable')]
    /**
     * Date et heure de création de l'entité.
     */
    protected \DateTimeImmutable $createdAt;

    public function __construct()
    {
        parent::__construct();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
