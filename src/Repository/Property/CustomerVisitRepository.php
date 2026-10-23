<?php

declare(strict_types=1);

namespace App\Repository\Property;

use App\Entity\Identity\Organization;
use App\Entity\Property\CustomerInquiry;
use App\Entity\Property\CustomerVisit;
use App\Enum\CustomerVisitStatus;
use App\Repository\UuidParameterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** Rendez-vous commerciaux, bornés simultanément à l'organisation et à la demande. */
final class CustomerVisitRepository extends ServiceEntityRepository
{
    use UuidParameterTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerVisit::class);
    }

    /** @return list<CustomerVisit> */
    public function findForInquiry(Organization $organization, CustomerInquiry $inquiry): array
    {
        return $this->createQueryBuilder('v')->addSelect('actor')
            ->innerJoin('v.createdBy', 'actor')
            ->andWhere('v.organization = :organization')->andWhere('v.inquiry = :inquiry')
            ->setParameter('organization', $organization)->setParameter('inquiry', $inquiry)
            ->orderBy('v.scheduledAt', 'ASC')->addOrderBy('v.id', 'ASC')
            ->getQuery()->getResult();
    }

    public function findOneForInquiry(Organization $organization, CustomerInquiry $inquiry, Uuid $uuid): ?CustomerVisit
    {
        return $this->createQueryBuilder('v')->andWhere('v.organization = :organization')
            ->andWhere('v.inquiry = :inquiry')->andWhere('v.uuid = :uuid')
            ->setParameter('organization', $organization)->setParameter('inquiry', $inquiry)
            ->setParameter('uuid', $this->bindableUuid($uuid))
            ->getQuery()->getOneOrNullResult();
    }

    public function lock(CustomerVisit $visit): CustomerVisit
    {
        return $this->getEntityManager()->find(CustomerVisit::class, $visit->getId(), LockMode::PESSIMISTIC_WRITE)
            ?? throw new \LogicException('Le rendez-vous à verrouiller n’existe plus.');
    }

    public function hasOpenVisits(CustomerInquiry $inquiry): bool
    {
        return (int) $this->createQueryBuilder('v')->select('COUNT(v.id)')
            ->andWhere('v.inquiry = :inquiry')->andWhere('v.status IN (:statuses)')
            ->setParameter('inquiry', $inquiry)
            ->setParameter('statuses', [CustomerVisitStatus::PROPOSED, CustomerVisitStatus::CONFIRMED, CustomerVisitStatus::RESCHEDULED])
            ->getQuery()->getSingleScalarResult() > 0;
    }
}
