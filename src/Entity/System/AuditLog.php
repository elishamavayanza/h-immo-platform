<?php

declare(strict_types=1);

namespace App\Entity\System;

use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Entity\Shared\CreatedOnlyEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * AuditLog
 *
 * Package  : System & Audit
 * Table    : audit_log
 *
 * Journal d'audit générique : trace toute action significative
 * (create/update/delete) effectuée sur une entité métier quelconque,
 * identifiée par `entityType` (nom de la classe/table) et `entityId`.
 * `organization` et `user` sont nullables pour couvrir les actions
 * techniques (tâches planifiées, actions du SUPER_ADMIN hors
 * Organization). `oldValues`/`newValues` stockent un instantané JSON
 * de l'état avant/après. Entité en écriture seule (CreatedOnlyEntity) :
 * un log ne doit jamais être modifié ni supprimé.
 */
#[ORM\Entity]
#[ORM\Table(name: 'audit_log')]
class AuditLog extends CreatedOnlyEntity
{
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: true)]
    /**
     * Organisation concernée par l'action auditée, ou null pour une action globale.
     */
    private ?Organization $organization = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    /**
     * Utilisateur ayant effectué l'action, ou null pour une action technique.
     */
    private ?User $user = null;

    #[ORM\Column(type: Types::STRING, length: 60)]
    /**
     * Action effectuée sur l'entité.
     */
    private string $action;

    #[ORM\Column(type: Types::STRING, length: 60)]
    /**
     * Type de l'entité concernée par l'action.
     */
    private string $entityType;

    #[ORM\Column(type: Types::BIGINT)]
    /**
     * Identifiant interne de l'entité concernée par l'action.
     */
    private int $entityId;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    /**
     * Valeurs de l'entité avant l'action, au format JSON.
     */
    private ?array $oldValues = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    /**
     * Valeurs de l'entité après l'action, au format JSON.
     */
    private ?array $newValues = null;

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): static
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getOldValues(): ?array
    {
        return $this->oldValues;
    }

    public function setOldValues(?array $oldValues): static
    {
        $this->oldValues = $oldValues;

        return $this;
    }

    public function getNewValues(): ?array
    {
        return $this->newValues;
    }

    public function setNewValues(?array $newValues): static
    {
        $this->newValues = $newValues;

        return $this;
    }
}
