<?php

declare(strict_types=1);

namespace App\Repository\Property;

use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Entity\Property\City;
use App\Entity\Property\CustomerInquiry;
use App\Entity\Property\CustomerInquiryNotification;
use App\Entity\Identity\OrganizationUser;
use App\Entity\Identity\UserCity;
use App\Enum\OrganizationRole;
use App\Repository\UuidParameterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** Requêtes des alertes personnelles de demandes client. */
final class CustomerInquiryNotificationRepository extends ServiceEntityRepository
{
    use UuidParameterTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerInquiryNotification::class);
    }

    /** @return list<User> */
    public function findActiveCityAdmins(Organization $organization, City $city): array
    {
        return $this->getEntityManager()->createQueryBuilder()->select('DISTINCT u')
            ->from(User::class, 'u')->join(UserCity::class, 'uc', 'WITH', 'uc.user = u')->join(OrganizationUser::class, 'ou', 'WITH', 'ou.user = u')
            ->andWhere('uc.city = :city')->andWhere('ou.organization = :organization')
            ->andWhere('ou.role = :role')->andWhere('u.isActive = true')->andWhere('u.deletedAt IS NULL')
            ->setParameter('city', $city)->setParameter('organization', $organization)
            ->setParameter('role', OrganizationRole::ADMIN_VILLE)->getQuery()->getResult();
    }

    /** @return array{items: list<CustomerInquiryNotification>, unread: int} */
    public function findInbox(User $recipient, Organization $organization, array $cities): array
    {
        if ($cities === []) return ['items' => [], 'unread' => 0];
        $qb = $this->createQueryBuilder('n')->join('n.inquiry', 'i')->join('i.listing', 'l')
            ->join('l.unit', 'unit')->join('unit.building', 'building')->join('building.parcel', 'parcel')->join('parcel.city', 'city')
            ->addSelect('i', 'l', 'unit', 'building', 'parcel', 'city')
            ->andWhere('n.recipient = :recipient')->andWhere('i.organization = :organization')
            ->andWhere('city.organization = :organization')->andWhere('city IN (:cities)')
            ->setParameter('recipient', $recipient)->setParameter('organization', $organization)->setParameter('cities', $cities)
            ->orderBy('n.createdAt', 'DESC')->setMaxResults(30);
        $items = $qb->getQuery()->getResult();
        $unread = (int) $this->createQueryBuilder('n')->select('COUNT(n.id)')->join('n.inquiry', 'i')->join('i.listing', 'l')
            ->join('l.unit', 'unit')->join('unit.building', 'building')->join('building.parcel', 'parcel')->join('parcel.city', 'city')
            ->andWhere('n.recipient = :recipient')->andWhere('n.readAt IS NULL')->andWhere('i.organization = :organization')
            ->andWhere('city.organization = :organization')->andWhere('city IN (:cities)')
            ->setParameter('recipient', $recipient)->setParameter('organization', $organization)->setParameter('cities', $cities)
            ->getQuery()->getSingleScalarResult();
        return ['items' => $items, 'unread' => $unread];
    }

    public function findOneForRecipient(Organization $organization, User $recipient, Uuid $uuid): ?CustomerInquiryNotification
    {
        return $this->createQueryBuilder('n')->join('n.inquiry', 'i')
            ->andWhere('n.uuid = :uuid')->andWhere('n.recipient = :recipient')->andWhere('i.organization = :organization')
            ->setParameter('uuid', $this->bindableUuid($uuid))->setParameter('recipient', $recipient)
            ->setParameter('organization', $organization)->getQuery()->getOneOrNullResult();
    }
}
