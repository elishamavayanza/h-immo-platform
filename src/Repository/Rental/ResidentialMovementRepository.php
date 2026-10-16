<?php

declare(strict_types=1);

namespace App\Repository\Rental;

use App\Entity\Rental\ResidentialMovement;
use App\Entity\Rental\Tenant;
use App\Repository\UuidParameterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<ResidentialMovement> */
final class ResidentialMovementRepository extends ServiceEntityRepository
{
    use UuidParameterTrait;
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResidentialMovement::class);
    }

    /** @return list<ResidentialMovement> */
    public function findHistoryForTenant(Tenant $tenant): array
    {
        $history = $this->createQueryBuilder('m')
            ->andWhere('m.tenant = :tenant')
            ->andWhere('m.organization = :organization')
            ->setParameter('tenant', $tenant)
            ->setParameter('organization', $tenant->getOrganization())
            ->getQuery()
            ->getResult();

        usort($history, static function (ResidentialMovement $left, ResidentialMovement $right): int {
            $leftDate = $left->getEffectiveDate() ?? $left->getPlannedDate() ?? $left->getCreatedAt();
            $rightDate = $right->getEffectiveDate() ?? $right->getPlannedDate() ?? $right->getCreatedAt();

            $dateOrder = $rightDate <=> $leftDate;
            if ($dateOrder !== 0) {
                return $dateOrder;
            }

            $createdOrder = $right->getCreatedAt() <=> $left->getCreatedAt();

            return $createdOrder !== 0 ? $createdOrder : ($right->getId() <=> $left->getId());
        });

        return $history;
    }

    public function findOneForTenant(Uuid $uuid, Tenant $tenant): ?ResidentialMovement
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.uuid = :uuid')->andWhere('m.tenant = :tenant')->andWhere('m.organization = :organization')
            ->setParameter('uuid', $this->bindableUuid($uuid))->setParameter('tenant', $tenant)
            ->setParameter('organization', $tenant->getOrganization())
            ->getQuery()->getOneOrNullResult();
    }

    /** @param list<Tenant> $tenants @return array<string, ResidentialMovement> indexée par UUID du locataire */
    public function findLatestForTenants(array $tenants): array
    {
        if ($tenants === []) {
            return [];
        }

        $movements = $this->createQueryBuilder('m')
            ->andWhere('m.tenant IN (:tenants)')
            ->andWhere('m.id = (SELECT MAX(m2.id) FROM App\\Entity\\Rental\\ResidentialMovement m2 WHERE m2.tenant = m.tenant AND m2.organization = m.organization)')
            ->setParameter('tenants', $tenants)
            ->getQuery()
            ->getResult();

        $latestByTenant = [];
        foreach ($movements as $movement) {
            if ($movement instanceof ResidentialMovement) {
                $latestByTenant[$movement->getTenant()->getUuid()->toRfc4122()] = $movement;
            }
        }

        return $latestByTenant;
    }
}
