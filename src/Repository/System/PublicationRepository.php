<?php

declare(strict_types=1);

namespace App\Repository\System;

use App\Entity\Identity\User;
use App\Entity\System\Publication;
use App\Entity\System\PublicationRecipient;
use App\Enum\PublicationStatus;
use App\Enum\PlatformRole;
use App\Repository\UuidParameterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Publication> */
final class PublicationRepository extends ServiceEntityRepository
{
    use UuidParameterTrait;
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Publication::class); }

    /** @return list<Publication> */
    public function findManagementList(): array
    {
        return $this->createQueryBuilder('p')->orderBy('p.createdAt', 'DESC')->getQuery()->getResult();
    }

    /** @return array<int, array{recipients: int, views: int, expired: int}> */
    public function getRecipientStats(\DateTimeImmutable $now): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(r.publication) AS publicationId')
            ->addSelect('COUNT(r.id) AS recipientCount')
            ->addSelect('SUM(CASE WHEN r.firstViewedAt IS NOT NULL THEN 1 ELSE 0 END) AS viewCount')
            ->addSelect('SUM(CASE WHEN r.withdrawnAt IS NULL AND ((r.firstViewedAt IS NULL AND r.availableAt <= :unreadCutoff) OR (r.firstViewedAt IS NOT NULL AND r.firstViewedAt <= :readCutoff) OR p.endsAt <= :now) THEN 1 ELSE 0 END) AS expiredCount')
            ->from(PublicationRecipient::class, 'r')->join('r.publication', 'p')->groupBy('r.publication')
            ->setParameter('unreadCutoff', $now->modify('-48 hours'))->setParameter('readCutoff', $now->modify('-24 hours'))->setParameter('now', $now)
            ->getQuery()->getArrayResult();
        $stats = [];
        foreach ($rows as $row) $stats[(int) $row['publicationId']] = ['recipients' => (int) $row['recipientCount'], 'views' => (int) $row['viewCount'], 'expired' => (int) $row['expiredCount']];
        return $stats;
    }

    /** @return list<PublicationRecipient> */
    public function findActiveForUser(User $user, \DateTimeImmutable $now): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('r', 'p')->from(PublicationRecipient::class, 'r')->join('r.publication', 'p')
            ->andWhere('r.user = :user')->andWhere('r.availableAt <= :now')->andWhere('r.withdrawnAt IS NULL')
            ->andWhere('p.status = :published')->andWhere('(p.endsAt IS NULL OR p.endsAt > :now)')
            ->andWhere('((r.firstViewedAt IS NULL AND r.availableAt > :unreadCutoff) OR (r.firstViewedAt IS NOT NULL AND r.firstViewedAt > :readCutoff))')
            ->setParameter('user', $user)->setParameter('now', $now)
            ->setParameter('unreadCutoff', $now->modify('-48 hours'))->setParameter('readCutoff', $now->modify('-24 hours'))
            ->setParameter('published', PublicationStatus::PUBLISHED)->orderBy('r.availableAt', 'DESC')->getQuery()->getResult();
    }

    public function findRecipient(User $user, string $uuid): ?PublicationRecipient
    {
        return $this->getEntityManager()->createQueryBuilder()->select('r', 'p')->from(PublicationRecipient::class, 'r')
            ->join('r.publication', 'p')->andWhere('r.user = :user')->andWhere('p.uuid = :uuid')
            ->setParameter('user', $user)->setParameter('uuid', $this->bindableUuid(\Symfony\Component\Uid\Uuid::fromString($uuid)))
            ->getQuery()->getOneOrNullResult();
    }

    /** @return list<User> */
    public function findRecipients(Publication $publication): array
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder()->select('DISTINCT u')->from(User::class, 'u')
            ->andWhere('u.isActive = true')->andWhere('u.deletedAt IS NULL');
        if ($publication->getAudience()->value === 'organization') {
            $qb->join('App\\Entity\\Identity\\OrganizationUser', 'ou', 'WITH', 'ou.user = u')
                ->andWhere('ou.organization = :organization')->setParameter('organization', $publication->getOrganization());
        } elseif ($publication->getAudience()->value === 'roles') {
            $roles = $publication->getAudienceRoles();
            $organizationRoles = array_values(array_intersect($roles, ['patron', 'admin_immobilier', 'admin_ville']));
            $hasSuperAdmin = in_array('super_admin', $roles, true);
            $conditions = [];
            if ($organizationRoles !== []) {
                // Une sous-requête évite les doublons pour les comptes multi-organisations
                // et permet de combiner des rôles métier avec SUPER_ADMIN sans
                // exclure les comptes plateforme qui n'ont aucune appartenance.
                $conditions[] = 'u.id IN (SELECT IDENTITY(ou.user) FROM App\\Entity\\Identity\\OrganizationUser ou JOIN ou.organization org WHERE ou.role IN (:roles) AND org.deletedAt IS NULL)';
                $qb->setParameter('roles', $organizationRoles);
            }
            if ($hasSuperAdmin) {
                $conditions[] = 'u.platformRole = :platformRole';
                $qb->setParameter('platformRole', PlatformRole::SUPER_ADMIN);
            }
            if ($conditions === []) return [];
            $qb->andWhere('('.implode(' OR ', $conditions).')');
            if ($organizationRoles === [] && !in_array('super_admin', $roles, true)) return [];
        }
        return $qb->getQuery()->getResult();
    }
}
