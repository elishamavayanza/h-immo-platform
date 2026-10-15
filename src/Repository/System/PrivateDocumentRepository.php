<?php

declare(strict_types=1);

namespace App\Repository\System;

use App\Entity\Identity\Organization;
use App\Entity\Property\City;
use App\Entity\System\PrivateDocument;
use App\Enum\DocumentEntityType;
use App\Repository\UuidParameterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<PrivateDocument> */
final class PrivateDocumentRepository extends ServiceEntityRepository
{
    use UuidParameterTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrivateDocument::class);
    }

    /** @return list<PrivateDocument> */
    public function findVisible(Organization $organization, ?City $city, ?DocumentEntityType $type, ?string $relatedUuid): array
    {
        $query = $this->createQueryBuilder('d')
            ->andWhere('d.organization = :organization')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('organization', $organization)
            ->orderBy('d.createdAt', 'DESC');

        if ($city !== null) {
            $query->andWhere('d.city = :city')->setParameter('city', $city);
        }
        if ($type !== null) {
            $query->andWhere('d.relatedType = :type')->setParameter('type', $type);
        }
        if ($relatedUuid !== null) {
            $query->andWhere('d.relatedUuid = :relatedUuid')->setParameter('relatedUuid', $relatedUuid);
        }

        return $query->setMaxResults(200)->getQuery()->getResult();
    }

    public function findOneByPublicUuid(Uuid $uuid): ?PrivateDocument
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.uuid = :uuid')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('uuid', $this->bindableUuid($uuid))
            ->getQuery()->getOneOrNullResult();
    }

    /** @return list<PrivateDocument> */
    public function findOlderThan(\DateTimeImmutable $cutoff, int $limit = 200): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.createdAt < :cutoff')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('cutoff', $cutoff)
            ->orderBy('d.createdAt', 'ASC')
            ->setMaxResults(max(1, min(500, $limit)))
            ->getQuery()
            ->getResult();
    }
}
