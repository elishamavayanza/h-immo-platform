<?php

declare(strict_types=1);

namespace App\Entity\Staff;

use App\Entity\Identity\Organization;
use App\Entity\Shared\SoftDeletableEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Worker
 *
 * Package  : Staff Management
 * Table    : worker
 *
 * Personne employée par une Organization pour la gestion de son
 * patrimoine : gérant, sentinelle, ménager, gardien ou agent technique.
 *
 * L'entité ne porte que l'identité de la personne. Le rôle exercé, la
 * rémunération et le lieu d'exercice sont portés par WorkerAssignment,
 * ce qui permet à une même personne d'être affectée à plusieurs sites
 * avec des fonctions différentes.
 *
 * Le rattachement à l'Organization est explicite, comme pour Tenant :
 * il rend l'isolation inter-entreprise vérifiable sans traverser la
 * chaîne du patrimoine.
 */
#[ORM\Entity]
#[ORM\Table(name: 'worker')]
#[ORM\UniqueConstraint(name: 'uniq_worker_org_national_id', columns: ['organization_id', 'national_id'])]
class Worker extends SoftDeletableEntity
{
    /**
     * Organization qui emploie le travailleur.
     */
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false)]
    private Organization $organization;

    /**
     * Nom complet du travailleur.
     */
    #[ORM\Column(type: Types::STRING, length: 200)]
    private string $fullName;

    /**
     * Numéro de téléphone du travailleur.
     */
    #[ORM\Column(type: Types::STRING, length: 30)]
    private string $phone;

    /**
     * Adresse électronique du travailleur.
     */
    #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
    private ?string $email = null;

    /**
     * Pièce d'identité ou numéro d'identification nationale.
     */
    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $nationalId = null;

    /**
     * Adresse de résidence du travailleur.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $address = null;

    /**
     * Notes complémentaires sur le travailleur.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function setOrganization(Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getNationalId(): ?string
    {
        return $this->nationalId;
    }

    public function setNationalId(?string $nationalId): static
    {
        $this->nationalId = $nationalId;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }
}
