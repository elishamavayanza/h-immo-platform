<?php

declare(strict_types=1);

namespace App\Entity\Shared;

use Doctrine\ORM\Mapping as ORM;

/**
 * TimestampedEntity
 *
 * Classe mère abstraite ajoutant le suivi temporel standard (création /
 * dernière modification) au-dessus de BaseEntity.
 *
 * Choix de conception :
 *   - `createdAt` est initialisé une seule fois dans le constructeur.
 *   - `updatedAt` est volontairement laissé sans logique d'auto-mise à jour
 *     à l'intérieur de l'entité (pas de #[ORM\PreUpdate] ici) afin de
 *     respecter la règle « aucune fonction métier/technique dans les
 *     entités ». La mise à jour automatique de `updatedAt` doit être
 *     déléguée à un Doctrine EventSubscriber/Listener externe
 *     (ex. App\EventListener\TimestampListener écoutant preUpdate).
 */
#[ORM\MappedSuperclass]
abstract class TimestampedEntity extends BaseEntity
{
    #[ORM\Column(type: 'datetime_immutable')]
    /**
     * Date et heure de création de l'entité.
     */
    protected \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    /**
     * Date et heure de la dernière modification de l'entité.
     */
    protected \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        parent::__construct();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
