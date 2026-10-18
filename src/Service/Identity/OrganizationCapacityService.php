<?php

declare(strict_types=1);

namespace App\Service\Identity;

use App\Entity\Identity\Organization;
use App\Repository\Identity\OrganizationRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Calcule l’usage d’une organisation et explique les refus de dépassement. */
final readonly class OrganizationCapacityService
{
    public function __construct(
        private OrganizationRepository $organizationRepository,
        private EntityManagerInterface $em,
    ) {
    }

    /** @return array{cities:int,parcels:int,buildings:int,units:int,members:int} */
    public function usage(Organization $organization): array
    {
        return [
            'cities' => $this->organizationRepository->countCapacityUsage($organization, 'cities'),
            'parcels' => $this->organizationRepository->countCapacityUsage($organization, 'parcels'),
            'buildings' => $this->organizationRepository->countCapacityUsage($organization, 'buildings'),
            'units' => $this->organizationRepository->countCapacityUsage($organization, 'units'),
            'members' => $this->organizationRepository->countCapacityUsage($organization, 'members'),
        ];
    }

    public function limitMessage(Organization $organization, string $resource): ?string
    {
        $limits = [
            'cities' => ['max' => $organization->getMaxCities(), 'label' => 'villes'],
            'parcels' => ['max' => $organization->getMaxParcels(), 'label' => 'parcelles'],
            'buildings' => ['max' => $organization->getMaxBuildings(), 'label' => 'bâtiments'],
            'units' => ['max' => $organization->getMaxUnits(), 'label' => 'unités'],
            'members' => ['max' => $organization->getMaxMembers(), 'label' => 'membres actifs'],
        ];
        $config = $limits[$resource] ?? null;

        if ($config === null || $config['max'] === null) {
            return null;
        }

        $used = $this->organizationRepository->countCapacityUsage($organization, $resource);
        if ($used < $config['max']) {
            return null;
        }

        return sprintf(
            'La limite de %d %s de cette organisation est atteinte. Contactez le SUPER_ADMIN pour demander une augmentation.',
            $config['max'],
            $config['label'],
        );
    }

    /**
     * Verrouille l’organisation et sérialise le comptage avec l’insertion.
     * Les créateurs concurrents d’une même ressource ne peuvent donc pas
     * dépasser le plafond en lisant simultanément la même dernière place.
     *
     * @template T
     * @param callable():T $operation
     * @return array{message:?string,status:int,result:?T}
     */
    public function executeWithinLimit(Organization $organization, string $resource, callable $operation): array
    {
        $lockName = sprintf('h-immo-cap:%d:%s', $organization->getId(), $resource);
        $connection = $this->em->getConnection();
        $acquired = (int) $connection->fetchOne('SELECT GET_LOCK(?, 10)', [$lockName]);
        if ($acquired !== 1) {
            return ['message' => 'La capacité est en cours de vérification. Réessayez dans un instant.', 'status' => 503, 'result' => null];
        }

        try {
            $message = $this->limitMessage($organization, $resource);
            if ($message !== null) {
                return ['message' => $message, 'status' => 409, 'result' => null];
            }

            return ['message' => null, 'status' => 201, 'result' => $operation()];
        } finally {
            $connection->fetchOne('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }
}
