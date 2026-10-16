<?php

declare(strict_types=1);

namespace App\Service\System;

use App\Enum\OrganizationRole;
use App\Enum\PlatformRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Les rôles de Soft-IMMO sont des enums applicatifs, pas des lignes de
 * référence en base. L'initialisation vérifie le mapping et la connexion sans
 * inventer de données métier ni toucher aux préférences déjà enregistrées.
 */
final readonly class SystemInitializationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {
    }

    /** @return array{environment:string,database:string,entities:int,platformRoles:list<string>,organizationRoles:list<string>} */
    public function inspect(): array
    {
        $connection = $this->entityManager->getConnection();
        $database = (string) $connection->fetchOne('SELECT DATABASE()');
        if ($database === '') {
            throw new \RuntimeException('La connexion Doctrine ne sélectionne aucune base.');
        }

        return [
            'environment' => $this->environment,
            'database' => $database,
            'entities' => count($this->entityManager->getMetadataFactory()->getAllMetadata()),
            'platformRoles' => array_map(static fn (PlatformRole $role): string => $role->value, PlatformRole::cases()),
            'organizationRoles' => array_map(static fn (OrganizationRole $role): string => $role->value, OrganizationRole::cases()),
        ];
    }
}
