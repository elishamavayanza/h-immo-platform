<?php

declare(strict_types=1);

namespace App\Repository\Property;

use App\Entity\Property\Building;
use App\Entity\Property\City;
use App\Entity\Property\Parcel;
use App\Repository\PaginatedResultTrait;
use App\Repository\UuidParameterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * BuildingRepository
 *
 * Package : Property Management
 * Entité  : App\Entity\Property\Building
 *
 * Le bâtiment hérite de son périmètre via `parcel -> city`. Le
 * chaînage est résolu en JOIN plutôt que par un appel PHP par ville,
 * afin de rester en une seule requête.
 *
 * @extends ServiceEntityRepository<Building>
 */
class BuildingRepository extends ServiceEntityRepository
{
    use PaginatedResultTrait;
    use UuidParameterTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Building::class);
    }

    public function save(Building $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Building $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUuid(Uuid $uuid): ?Building
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.uuid = :uuid')
            ->andWhere('b.deletedAt IS NULL')
            ->setParameter('uuid', $this->bindableUuid($uuid))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche un bâtiment par sa référence, au sein d'une parcelle.
     */
    public function findOneByParcelAndReference(Parcel $parcel, string $reference): ?Building
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.parcel = :parcel')
            ->andWhere('b.reference = :reference')
            ->andWhere('b.deletedAt IS NULL')
            ->setParameter('parcel', $parcel)
            ->setParameter('reference', $reference)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste tous les bâtiments d'une parcelle.
     *
     * @return Building[]
     */
    public function findByParcel(Parcel $parcel): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.parcel = :parcel')
            ->andWhere('b.deletedAt IS NULL')
            ->setParameter('parcel', $parcel)
            ->orderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste paginée des bâtiments accessibles.
     *
     * @param list<City>  $cities villes autorisées pour le lecteur
     * @param Parcel|null $parcel parcelle parente (optionnelle, résolue et
     *                            autorisée par le service ; sinon `null`)
     * @return array{items: list<Building>, total: int}
     */
    public function findPaginatedAccessible(
        array $cities,
        int $page,
        int $limit,
        ?string $search = null,
        ?Parcel $parcel = null
    ): array {
        if ($cities === []) {
            return ['items' => [], 'total' => 0];
        }

        $qb = $this->createQueryBuilder('b')
            ->innerJoin('b.parcel', 'p')
            ->andWhere('b.deletedAt IS NULL')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.city IN (:cities)')
            ->setParameter('cities', $cities)
            ->orderBy('b.name', 'ASC');

        if ($parcel !== null) {
            $qb->andWhere('b.parcel = :parcel')
                ->setParameter('parcel', $parcel);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('b.name LIKE :search OR b.reference LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $this->fetchPaginated($qb, $page, $limit);
    }
}
