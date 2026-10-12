<?php

declare(strict_types=1);

namespace App\Repository\Financial;

use App\Entity\Financial\ExchangeRate;
use App\Enum\Currency;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * ExchangeRateRepository
 *
 * Package : Financial
 *
 * Repository pour la gestion des taux de change historiques.
 */
final class ExchangeRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExchangeRate::class);
    }

    public function save(ExchangeRate $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve le taux de change applicable pour une paire de devises à une date donnée.
     *
     * Cherche le taux dont la période [effectiveFrom, effectiveTo[ contient la date.
     * Si plusieurs taux correspondent (ne devrait pas arriver avec la contrainte unique),
     * retourne le plus récent (effectiveFrom le plus grand).
     */
    public function findRateForDate(Currency $baseCurrency, Currency $quoteCurrency, \DateTimeImmutable $date): ?ExchangeRate
    {
        $qb = $this->createQueryBuilder('er')
            ->andWhere('er.baseCurrency = :base')
            ->andWhere('er.quoteCurrency = :quote')
            ->andWhere('er.effectiveFrom <= :date')
            ->andWhere('(er.effectiveTo IS NULL OR er.effectiveTo > :date)')
            ->setParameter('base', $baseCurrency)
            ->setParameter('quote', $quoteCurrency)
            ->setParameter('date', $date)
            ->orderBy('er.effectiveFrom', 'DESC')
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Trouve le taux de change inverse (quote -> base) pour une date donnée.
     */
    public function findInverseRateForDate(Currency $baseCurrency, Currency $quoteCurrency, \DateTimeImmutable $date): ?ExchangeRate
    {
        return $this->findRateForDate($quoteCurrency, $baseCurrency, $date);
    }

    /**
     * Trouve le dernier taux connu pour une paire de devises (peu importe la date).
     */
    public function findLatestRate(Currency $baseCurrency, Currency $quoteCurrency): ?ExchangeRate
    {
        $qb = $this->createQueryBuilder('er')
            ->andWhere('er.baseCurrency = :base')
            ->andWhere('er.quoteCurrency = :quote')
            ->orderBy('er.effectiveFrom', 'DESC')
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findCurrentForScope(Currency $baseCurrency, Currency $quoteCurrency, string $scopeKey): ?ExchangeRate
    {
        return $this->createQueryBuilder('er')
            ->andWhere('er.scopeKey = :scope')
            ->andWhere('er.baseCurrency = :base')
            ->andWhere('er.quoteCurrency = :quote')
            ->andWhere('er.effectiveTo IS NULL')
            ->setParameter('scope', $scopeKey)
            ->setParameter('base', $baseCurrency)
            ->setParameter('quote', $quoteCurrency)
            ->orderBy('er.effectiveFrom', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Taux applicable avec repli ville → organisation → plateforme. */
    public function findApplicableRate(
        Currency $baseCurrency,
        Currency $quoteCurrency,
        \DateTimeImmutable $date,
        ?int $organizationId,
        ?int $cityId,
        bool $allowCityRate,
    ): ?ExchangeRate {
        if ($allowCityRate && $cityId !== null) {
            $rate = $this->findRateAtScope($baseCurrency, $quoteCurrency, $date, $organizationId, $cityId);
            if ($rate !== null) return $rate;
        }

        if ($organizationId !== null) {
            $rate = $this->findRateAtScope($baseCurrency, $quoteCurrency, $date, $organizationId, null);
            if ($rate !== null) return $rate;
        }

        return $this->findRateAtScope($baseCurrency, $quoteCurrency, $date, null, null);
    }

    /** Taux applicable dans un périmètre précis, sans repli implicite. */
    public function findRateInScope(Currency $baseCurrency, Currency $quoteCurrency, \DateTimeImmutable $date, ?int $organizationId, ?int $cityId): ?ExchangeRate
    {
        return $this->findRateAtScope($baseCurrency, $quoteCurrency, $date, $organizationId, $cityId);
    }

    private function findRateAtScope(
        Currency $baseCurrency,
        Currency $quoteCurrency,
        \DateTimeImmutable $date,
        ?int $organizationId,
        ?int $cityId,
    ): ?ExchangeRate {
        $qb = $this->createQueryBuilder('er')
            ->andWhere('er.baseCurrency = :base')
            ->andWhere('er.quoteCurrency = :quote')
            ->andWhere('er.effectiveFrom <= :date')
            ->andWhere('(er.effectiveTo IS NULL OR er.effectiveTo > :date)')
            ->setParameter('base', $baseCurrency)
            ->setParameter('quote', $quoteCurrency)
            ->setParameter('date', $date)
            ->orderBy('er.effectiveFrom', 'DESC')
            ->setMaxResults(1);

        $organizationId === null
            ? $qb->andWhere('er.organization IS NULL')
            : $qb->andWhere('IDENTITY(er.organization) = :organizationId')->setParameter('organizationId', $organizationId);
        $cityId === null
            ? $qb->andWhere('er.city IS NULL')
            : $qb->andWhere('IDENTITY(er.city) = :cityId')->setParameter('cityId', $cityId);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findOneByUuid(Uuid $uuid): ?ExchangeRate
    {
        return $this->createQueryBuilder('er')
            ->andWhere('er.uuid = :uuid')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
