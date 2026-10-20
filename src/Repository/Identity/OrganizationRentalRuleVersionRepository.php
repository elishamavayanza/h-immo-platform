<?php

declare(strict_types=1);

namespace App\Repository\Identity;

use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationRentalRuleVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<OrganizationRentalRuleVersion> */
final class OrganizationRentalRuleVersionRepository extends ServiceEntityRepository implements OrganizationRentalRuleVersionLookupInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganizationRentalRuleVersion::class);
    }

    public function findApplicableForPeriod(
        Organization $organization,
        \DateTimeImmutable $periodStart,
    ): ?OrganizationRentalRuleVersion {
        return $this->createQueryBuilder('version')
            ->andWhere('version.organization = :organization')
            ->andWhere('version.effectiveFrom <= :periodStart')
            ->setParameter('organization', $organization)
            ->setParameter('periodStart', $periodStart)
            ->orderBy('version.effectiveFrom', 'DESC')
            ->addOrderBy('version.versionNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<OrganizationRentalRuleVersion> */
    public function findAllForOrganization(Organization $organization): array
    {
        return $this->createQueryBuilder('version')
            ->andWhere('version.organization = :organization')
            ->setParameter('organization', $organization)
            ->orderBy('version.effectiveFrom', 'ASC')
            ->addOrderBy('version.versionNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUuidAndOrganization(string $uuid, Organization $organization): ?OrganizationRentalRuleVersion
    {
        return $this->createQueryBuilder('version')->andWhere('version.uuid = :uuid')->andWhere('version.organization = :organization')
            ->setParameter('uuid', Uuid::fromString($uuid), 'uuid')->setParameter('organization', $organization)->getQuery()->getOneOrNullResult();
    }
}
