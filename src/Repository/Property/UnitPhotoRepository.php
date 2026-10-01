<?php

declare(strict_types=1);

namespace App\Repository\Property;

use App\Entity\Property\Unit;
use App\Entity\Property\UnitPhoto;
use App\Repository\UuidParameterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * UnitPhotoRepository
 *
 * @extends ServiceEntityRepository<UnitPhoto>
 */
class UnitPhotoRepository extends ServiceEntityRepository
{
    use UuidParameterTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UnitPhoto::class);
    }

    public function save(UnitPhoto $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Suppression physique assumée : une photo retirée de la galerie n'a pas à
     * être conservée. Le fichier est supprimé par le service appelant.
     */
    public function remove(UnitPhoto $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Galerie d'une unité, dans l'ordre d'affichage.
     *
     * `position` puis `id` : le second critère départage deux photos de même
     * rang, ce qui peut arriver si deux uploads simultanés lisent le même
     * `MAX(position)`. Sans lui, l'ordre deviendrait imprévisible d'une
     * lecture à l'autre.
     *
     * @return list<UnitPhoto>
     */
    public function findForUnit(Unit $unit): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.unit = :unit')
            ->setParameter('unit', $unit)
            ->orderBy('p.position', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Une photo appartient-elle bien à cette unité ?
     *
     * Contrôle d'appartenance explicite avant toute suppression : l'UUID seul
     * ne prouve rien, et les routes de suppression portent un UUID de photo
     * que le client contrôle.
     */
    public function findOneByUnitAndUuid(Unit $unit, Uuid $uuid): ?UnitPhoto
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.unit = :unit')
            ->andWhere('p.uuid = :uuid')
            ->setParameter('unit', $unit)
            ->setParameter('uuid', $this->bindableUuid($uuid))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countForUnit(Unit $unit): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.unit = :unit')
            ->setParameter('unit', $unit)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Rang le plus élevé de la galerie, ou -1 si elle est vide.
     */
    public function maxPositionForUnit(Unit $unit): int
    {
        $max = $this->createQueryBuilder('p')
            ->select('MAX(p.position)')
            ->andWhere('p.unit = :unit')
            ->setParameter('unit', $unit)
            ->getQuery()
            ->getSingleScalarResult();

        return $max === null ? -1 : (int) $max;
    }
}