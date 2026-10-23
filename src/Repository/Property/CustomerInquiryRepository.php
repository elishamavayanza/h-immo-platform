<?php

declare(strict_types=1);

namespace App\Repository\Property;

use App\Entity\Identity\Organization;
use App\Entity\Property\CustomerInquiry;
use App\Entity\Property\CustomerInquiryHistory;
use App\Enum\CustomerInquiryStatus;
use App\Enum\CustomerInquiryType;
use App\Repository\PaginatedResultTrait;
use App\Repository\UuidParameterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** Requêtes des demandes client, toujours bornées par l'organisation. */
final class CustomerInquiryRepository extends ServiceEntityRepository
{
    use PaginatedResultTrait;
    use UuidParameterTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerInquiry::class);
    }

    public function findOneByOrganizationAndKey(Organization $organization, string $keyHash): ?CustomerInquiry
    {
        return $this->createQueryBuilder('i')
            ->innerJoin('i.listing', 'listing')->innerJoin('listing.unit', 'unit')
            ->innerJoin('unit.building', 'building')->innerJoin('building.parcel', 'parcel')->innerJoin('parcel.city', 'city')
            ->andWhere('i.organization = :organization')->andWhere('i.idempotencyKeyHash = :keyHash')
            ->andWhere('city.organization = :organization')
            ->setParameter('organization', $organization)->setParameter('keyHash', $keyHash)
            ->getQuery()->getOneOrNullResult();
    }

    public function findOneByOrganizationAndUuid(Organization $organization, Uuid $uuid): ?CustomerInquiry
    {
        return $this->createQueryBuilder('i')->addSelect('listing', 'unit', 'building', 'parcel', 'city', 'assignee')
            ->innerJoin('i.listing', 'listing')->innerJoin('listing.unit', 'unit')
            ->innerJoin('unit.building', 'building')->innerJoin('building.parcel', 'parcel')->innerJoin('parcel.city', 'city')
            ->leftJoin('i.assignedTo', 'assignee')
            ->andWhere('i.organization = :organization')->andWhere('i.uuid = :uuid')
            ->andWhere('city.organization = :organization')
            ->setParameter('organization', $organization)->setParameter('uuid', $this->bindableUuid($uuid))
            ->getQuery()->getOneOrNullResult();
    }

    public function lock(CustomerInquiry $inquiry): CustomerInquiry
    {
        return $this->getEntityManager()->find(CustomerInquiry::class, $inquiry->getId(), LockMode::PESSIMISTIC_WRITE)
            ?? throw new \LogicException('La demande à verrouiller n’existe plus.');
    }

    /** @return array{items: list<CustomerInquiry>, total: int} */
    public function findForOrganization(Organization $organization, ?CustomerInquiryStatus $status, ?CustomerInquiryType $type, int $page, int $limit): array
    {
        $query = $this->createQueryBuilder('i')->addSelect('listing', 'unit', 'building', 'parcel', 'city', 'assignee')
            ->innerJoin('i.listing', 'listing')->innerJoin('listing.unit', 'unit')
            ->innerJoin('unit.building', 'building')->innerJoin('building.parcel', 'parcel')->innerJoin('parcel.city', 'city')
            ->leftJoin('i.assignedTo', 'assignee')
            ->andWhere('i.organization = :organization')->andWhere('city.organization = :organization')
            ->setParameter('organization', $organization)
            ->orderBy('i.createdAt', 'DESC')->addOrderBy('i.id', 'DESC');

        if ($status !== null) {
            $query->andWhere('i.status = :status')->setParameter('status', $status);
        }
        if ($type !== null) {
            $query->andWhere('i.type = :type')->setParameter('type', $type);
        }

        return $this->fetchPaginated($query, $page, $limit);
    }

    /** @return list<CustomerInquiryHistory> */
    public function findHistory(CustomerInquiry $inquiry): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('h', 'actor')->from(CustomerInquiryHistory::class, 'h')
            ->leftJoin('h.actor', 'actor')->andWhere('h.inquiry = :inquiry')->andWhere('h.organization = :organization')
            ->setParameter('inquiry', $inquiry)->setParameter('organization', $inquiry->getOrganization())
            ->orderBy('h.createdAt', 'ASC')->addOrderBy('h.id', 'ASC')
            ->getQuery()->getResult();
    }
}
