<?php

declare(strict_types=1);

namespace App\Repository\Staff;

use App\Entity\Property\City;
use App\Entity\Staff\Worker;
use App\Entity\Staff\WorkerAssignment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * WorkerRepository
 *
 * Package : Staff Management
 */
final class WorkerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Worker::class);
    }

    public function save(Worker $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Worker $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUuid(Uuid $uuid): ?Worker
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.uuid = :uuid')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByOrganization(Uuid $organizationId): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.organization = :org')
            ->setParameter('org', $organizationId)
            ->getQuery()
            ->getResult();
    }

    public function findByCity(City $city): array
    {
        return $this->createQueryBuilder('w')
            ->innerJoin('w.assignments', 'wa')
            ->andWhere('wa.city = :city')
            ->andWhere('wa.endDate IS NULL OR wa.endDate >= :now')
            ->setParameter('city', $city)
            ->setParameter('now', new \DateTimeImmutable())
            ->distinct()
            ->getQuery()
            ->getResult();
    }
}