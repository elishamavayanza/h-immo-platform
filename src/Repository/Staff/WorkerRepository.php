<?php

declare(strict_types=1);

namespace App\Repository\Staff;

use App\Entity\Property\City;
use App\Entity\Staff\Worker;
use App\Entity\Staff\WorkerAssignment;
use App\Service\System\DateTimeService;
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
    public function __construct(ManagerRegistry $registry, private readonly DateTimeService $dateTime)
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

    public function findByOrganization(\App\Entity\Identity\Organization $organization): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.organization = :org')
            ->setParameter('org', $organization)
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
            ->setParameter('now', $this->dateTime->now())
            ->distinct()
            ->getQuery()
            ->getResult();
    }

    public function findOneByNationalIdAndOrganization(string $nationalId, \App\Entity\Identity\Organization $organization): ?Worker
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.nationalId = :nid')
            ->andWhere('w.organization = :org')
            ->setParameter('nid', $nationalId)
            ->setParameter('org', $organization)
            ->getQuery()
            ->getOneOrNullResult();
    }
}