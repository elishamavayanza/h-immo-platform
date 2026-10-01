<?php

declare(strict_types=1);

namespace App\Repository\Identity;

use App\Entity\Identity\Organization;
use App\Enum\OrganizationStatus;
use App\Repository\PaginatedResultTrait;
use App\Repository\UuidParameterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * OrganizationRepository
 *
 * Package : Identity & Access
 * Entité  : App\Entity\Identity\Organization
 *
 * Requêtes d'accès aux organisations clientes (tenants) de la
 * plateforme Soft-IMMO.
 *
 * @extends ServiceEntityRepository<Organization>
 */
class OrganizationRepository extends ServiceEntityRepository
{
    use PaginatedResultTrait;
    use UuidParameterTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organization::class);
    }

    public function save(Organization $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Organization $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Recherche une Organization par son slug de vitrine publique.
     *
     * Le slug est normalisé en minuscules à l'écriture (voir
     * `Organization::setSlug()`) comme ici : sans cette normalisation
 * symétrique, `Immo-Plus` et `immo-plus` désigneraient deux pages pour la
     * même entreprise alors que la contrainte UNIQUE n'en interdisait qu'une.
     */
    public function findOneBySlug(string $slug): ?Organization
    {
        $normalized = mb_strtolower(trim($slug));

        if ($normalized === '') {
            return null;
        }

        return $this->createQueryBuilder('o')
            ->andWhere('o.slug = :slug')
            ->andWhere('o.deletedAt IS NULL')
            ->setParameter('slug', $normalized)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Un autre Organization possède-t-il déjà ce slug ?
     *
     * Contrôle d'exclusion : la publication d'une vitrine doit pouvoir changer
     * le slug d'une Organization existante, donc rejeter un slug déjà pris par
     * elle-même serait un faux positif.
     */
    public function isSlugTakenByAnother(string $slug, Organization $except): bool
    {
        $normalized = mb_strtolower(trim($slug));

        if ($normalized === '') {
            return false;
        }

        $count = $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.slug = :slug')
            ->andWhere('o.id <> :id')
            ->andWhere('o.deletedAt IS NULL')
            ->setParameter('slug', $normalized)
            ->setParameter('id', $except->getId())
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    /**
     * Recherche une Organization par son code unique.
     */
    public function findOneByCode(string $code): ?Organization
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.code = :code')
            ->andWhere('o.deletedAt IS NULL')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByUuid(Uuid $uuid): ?Organization
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.uuid = :uuid')
            ->andWhere('o.deletedAt IS NULL')
            ->setParameter('uuid', $this->bindableUuid($uuid))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste paginée des Organizations.
     *
     * ⚠ Cette requête est GLOBALE : elle ne filtre sur aucun périmètre
     * utilisateur. Elle ne doit être appelée que par un service ayant
     * déjà vérifié, via `SecurityService`, que l'appelant est SUPER_ADMIN
     * (administration de la plateforme) ou membre des Organizations
     * retournées. Le filtrage par liste d'identifiants est appliqué par
     * `findPaginatedByUuids()` dans le cas d'un utilisateur non
     * SUPER_ADMIN.
     *
     * @return array{items: list<Organization>, total: int}
     */
    public function findPaginated(int $page, int $limit, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.deletedAt IS NULL')
            ->orderBy('o.name', 'ASC');

        if ($search !== null && $search !== '') {
            $qb->andWhere('o.name LIKE :search OR o.code LIKE :search OR o.email LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $this->fetchPaginated($qb, $page, $limit);
    }

    /**
     * Variante bornée : ne retourne que les Organizations dont l'UUID
     * figure dans la liste fournie. C'est la porte d'entrée à utiliser
     * pour un utilisateur non SUPER_ADMIN, afin d'éviter de charger en
     * mémoire puis de filtrer en PHP l'ensemble de la table.
     *
     * @param list<string> $uuids
     * @return array{items: list<Organization>, total: int}
     */
    public function findPaginatedByUuids(array $uuids, int $page, int $limit, ?string $search = null): array
    {
        if ($uuids === []) {
            return ['items' => [], 'total' => 0];
        }

        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.deletedAt IS NULL')
            ->andWhere('o.uuid IN (:uuids)')
            ->setParameter('uuids', $this->bindableUuids($uuids), ArrayParameterType::BINARY)
            ->orderBy('o.name', 'ASC');

        if ($search !== null && $search !== '') {
            $qb->andWhere('o.name LIKE :search OR o.code LIKE :search OR o.email LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $this->fetchPaginated($qb, $page, $limit);
    }

    /**
     * Liste toutes les organisations actives.
     *
     * @return Organization[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.status = :status')
            ->andWhere('o.deletedAt IS NULL')
            ->setParameter('status', OrganizationStatus::ACTIVE)
            ->orderBy('o.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
