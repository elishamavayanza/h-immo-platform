<?php

declare(strict_types=1);

namespace App\Repository\Property;

use App\Entity\Identity\Organization;
use App\Entity\Property\City;
use App\Entity\Property\MaintenanceWorkOrder;
use App\Enum\MaintenanceStatus;
use App\Repository\UuidParameterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<MaintenanceWorkOrder> */
final class MaintenanceWorkOrderRepository extends ServiceEntityRepository
{
    use UuidParameterTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MaintenanceWorkOrder::class);
    }

    public function findOneByUuid(Uuid $uuid): ?MaintenanceWorkOrder
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.uuid = :uuid')->andWhere('m.deletedAt IS NULL')
            ->setParameter('uuid', $this->bindableUuid($uuid))
            ->getQuery()->getOneOrNullResult();
    }

    /** @return list<MaintenanceWorkOrder> */
    public function findForCity(Organization $organization, City $city, ?MaintenanceStatus $status, int $limit = 100): array
    {
        $query = $this->createQueryBuilder('m')
            ->andWhere('m.organization = :organization')->andWhere('m.city = :city')->andWhere('m.deletedAt IS NULL')
            ->setParameter('organization', $organization)->setParameter('city', $city)
            ->orderBy('m.createdAt', 'DESC')->setMaxResults(max(1, min($limit, 200)));
        if ($status !== null) {
            $query->andWhere('m.status = :status')->setParameter('status', $status);
        }

        return $query->getQuery()->getResult();
    }
}
