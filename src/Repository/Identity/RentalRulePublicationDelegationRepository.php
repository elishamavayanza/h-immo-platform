<?php

declare(strict_types=1);

namespace App\Repository\Identity;

use App\Entity\Identity\Organization;
use App\Entity\Identity\RentalRulePublicationDelegation;
use App\Entity\Identity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RentalRulePublicationDelegation> */
final class RentalRulePublicationDelegationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RentalRulePublicationDelegation::class);
    }

    public function hasValidPublicationDelegation(Organization $organization, User $user, \DateTimeImmutable $now): bool
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.organization = :organization')
            ->andWhere('d.user = :user')
            ->andWhere('d.permission = :permission')
            ->andWhere('d.revokedAt IS NULL')
            ->andWhere('(d.expiresAt IS NULL OR d.expiresAt > :now)')
            ->setParameter('organization', $organization)
            ->setParameter('user', $user)
            ->setParameter('permission', 'rental_rule_publish')
            ->setParameter('now', $now)
            ->getQuery()->getSingleScalarResult() > 0;
    }

    /** @return list<RentalRulePublicationDelegation> */
    public function findForOrganization(Organization $organization): array
    {
        return $this->createQueryBuilder('d')->andWhere('d.organization = :organization')->setParameter('organization', $organization)
            ->orderBy('d.createdAt', 'DESC')->getQuery()->getResult();
    }
}
