<?php

declare(strict_types=1);

namespace App\Entity\System;

use App\Entity\Identity\Organization;
use App\Entity\Shared\CreatedOnlyEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Marqueur explicite d'origine pour un jeu généré par les commandes démo. */
#[ORM\Entity]
#[ORM\Table(name: 'demo_dataset')]
#[ORM\UniqueConstraint(name: 'uniq_demo_dataset_seed_scenario', columns: ['seed', 'scenario'])]
#[ORM\UniqueConstraint(name: 'uniq_demo_dataset_organization', columns: ['organization_id'])]
class DemoDataset extends CreatedOnlyEntity
{
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Organization $organization;

    #[ORM\Column(type: Types::STRING, length: 10)]
    private string $seed;

    #[ORM\Column(type: Types::STRING, length: 16)]
    private string $scenario;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $generatorVersion = '1';

    public function getOrganization(): Organization { return $this->organization; }
    public function setOrganization(Organization $organization): static { $this->organization = $organization; return $this; }
    public function getSeed(): string { return $this->seed; }
    public function setSeed(string $seed): static { $this->seed = $seed; return $this; }
    public function getScenario(): string { return $this->scenario; }
    public function setScenario(string $scenario): static { $this->scenario = $scenario; return $this; }
    public function getGeneratorVersion(): string { return $this->generatorVersion; }
    public function setGeneratorVersion(string $version): static { $this->generatorVersion = $version; return $this; }
}
