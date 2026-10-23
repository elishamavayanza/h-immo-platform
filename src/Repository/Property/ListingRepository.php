<?php

declare(strict_types=1);

namespace App\Repository\Property;

use App\Entity\Identity\Organization;
use App\Entity\Property\City;
use App\Entity\Property\Listing;
use App\Entity\Property\Unit;
use App\Enum\ListingStatus;
use App\Repository\PaginatedResultTrait;
use App\Repository\UuidParameterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** Requêtes métier de gestion et de diffusion des annonces. */
final class ListingRepository extends ServiceEntityRepository
{
    use UuidParameterTrait;
    use PaginatedResultTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Listing::class);
    }

    public function findOneByUuid(Uuid $uuid): ?Listing
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.uuid = :uuid')
            ->setParameter('uuid', $this->bindableUuid($uuid))
            ->getQuery()->getOneOrNullResult();
    }

    public function findOneByUnit(Unit $unit): ?Listing
    {
        return $this->createQueryBuilder('l')->andWhere('l.unit = :unit')
            ->setParameter('unit', $unit)
            ->getQuery()->getOneOrNullResult();
    }

    /** @param list<City> $cities @return array{items: list<Listing>, total: int} */
    public function findForManagement(Organization $organization, array $cities, ?ListingStatus $status, int $page, int $limit): array
    {
        if ($cities === []) return ['items' => [], 'total' => 0];

        $qb = $this->createQueryBuilder('l')
            ->innerJoin('l.unit', 'u')->innerJoin('u.building', 'b')
            ->innerJoin('b.parcel', 'p')->innerJoin('p.city', 'c')
            ->andWhere('u.deletedAt IS NULL')
            ->andWhere('b.deletedAt IS NULL')->andWhere('p.deletedAt IS NULL')
            ->andWhere('c.deletedAt IS NULL')->andWhere('c.organization = :organization')
            ->andWhere('c IN (:cities)')->setParameter('organization', $organization)
            ->setParameter('cities', $cities)->orderBy('l.updatedAt', 'DESC')->addOrderBy('l.id', 'DESC');

        if ($status !== null) $qb->andWhere('l.status = :status')->setParameter('status', $status);

        return $this->fetchPaginated($qb, $page, $limit);
    }

    /** @return list<array{uuid: string, action: string, oldStatus: ?string, newStatus: string, reason: ?string, oldValues: ?array, newValues: ?array, actor: string, createdAt: string}> */
    public function findHistory(Listing $listing): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('h')->from(\App\Entity\Property\ListingHistory::class, 'h')
            ->innerJoin('h.actor', 'actor')->andWhere('h.listing = :listing')
            ->setParameter('listing', $listing)->orderBy('h.createdAt', 'ASC')->addOrderBy('h.id', 'ASC')
            ->getQuery()->getResult();

        return array_map(static fn ($row): array => [
            'uuid' => (string) $row->getUuid(), 'action' => $row->getAction(),
            'oldStatus' => $row->getOldStatus(), 'newStatus' => $row->getNewStatus(),
            'reason' => $row->getReason(), 'oldValues' => $row->getOldValues(),
            'newValues' => $row->getNewValues(), 'actor' => $row->getActor()->getFullName(),
            'createdAt' => $row->getCreatedAt()->format(DATE_ATOM),
        ], $rows);
    }
}
