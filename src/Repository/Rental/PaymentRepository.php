<?php

declare(strict_types=1);

namespace App\Repository\Rental;

use App\Entity\Rental\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * PaymentRepository
 *
 * Package : Rental Management
 * Entité  : App\Entity\Rental\Payment
 *
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function save(Payment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Payment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUuid(Uuid $uuid): ?Payment
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.uuid = :uuid')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste tous les paiements enregistrés pour une échéance (Rent)
     * donnée, du plus récent au plus ancien.
     *
     * @return Payment[]
     */
    public function findByRent(int $rentId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.rent = :rentId')
            ->setParameter('rentId', $rentId)
            ->orderBy('p.paymentDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le montant total déjà payé pour une échéance donnée
     * (somme simple ; la comparaison avec le montant dû relève
     * du service métier, pas du repository).
     */
    public function sumAmountByRent(int $rentId): string
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount) AS total')
            ->andWhere('p.rent = :rentId')
            ->setParameter('rentId', $rentId)
            ->getQuery()
            ->getSingleScalarResult();

        return (string) ($result ?? '0');
    }
}
