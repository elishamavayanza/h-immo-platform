<?php

declare(strict_types=1);

namespace App\Repository\Identity;

use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationRentalRuleDraft;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<OrganizationRentalRuleDraft> */
final class OrganizationRentalRuleDraftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, OrganizationRentalRuleDraft::class); }

    public function findOneByUuidAndOrganization(Uuid $uuid, Organization $organization): ?OrganizationRentalRuleDraft
    {
        return $this->createQueryBuilder('d')->andWhere('d.uuid = :uuid')->andWhere('d.organization = :organization')
            ->setParameter('uuid', $uuid, 'uuid')->setParameter('organization', $organization)->getQuery()->getOneOrNullResult();
    }

    /** @return list<OrganizationRentalRuleDraft> */
    public function findOpenForOrganization(Organization $organization): array
    {
        return $this->createQueryBuilder('d')->andWhere('d.organization = :organization')->andWhere('d.publishedVersion IS NULL')
            ->setParameter('organization', $organization)->orderBy('d.createdAt', 'DESC')->getQuery()->getResult();
    }
}
