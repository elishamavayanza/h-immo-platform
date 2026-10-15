<?php

declare(strict_types=1);

namespace App\Repository\Expense;

use App\Entity\Expense\CashOpeningBalance;
use App\Entity\Identity\Organization;
use App\Entity\Property\City;
use App\Enum\Currency;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

final class CashOpeningBalanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashOpeningBalance::class);
    }

    public function findForCity(City $city, Currency $currency): ?CashOpeningBalance
    {
        return $this->findOneBy(['city' => $city, 'currency' => $currency]);
    }

    public function cashBalance(Organization $organization, City $city, Currency $currency, \DateTimeImmutable $from, string $opening): string
    {
        $sql = <<<'SQL'
SELECT COALESCE(SUM(m.amount), 0.00)
FROM (
    SELECT CAST(:opening AS DECIMAL(12, 2)) AS amount
    UNION ALL
    SELECT p.amount AS amount
    FROM payment p
    INNER JOIN rent r ON r.id = p.rent_id
    INNER JOIN lease l ON l.id = r.lease_id
    INNER JOIN unit u ON u.id = l.unit_id
    INNER JOIN building b ON b.id = u.building_id
    INNER JOIN parcel pa ON pa.id = b.parcel_id
    WHERE l.organization_id = :organization_payment AND pa.city_id = :city_payment AND p.currency = :currency_payment AND p.method = 'cash' AND p.payment_date >= :from_payment
    UNION ALL
    SELECT -e.amount AS amount
    FROM expense e
    WHERE e.organization_id = :organization_expense AND e.city_id = :city_expense AND e.currency = :currency_expense AND e.method = 'cash' AND e.expense_date >= :from_expense
) m
SQL;
        $value = $this->getEntityManager()->getConnection()->fetchOne($sql, [
            'organization_payment' => $organization->getId(),
            'city_payment' => $city->getId(),
            'currency_payment' => $currency->value,
            'from_payment' => $from->format('Y-m-d'),
            'organization_expense' => $organization->getId(),
            'city_expense' => $city->getId(),
            'currency_expense' => $currency->value,
            'from_expense' => $from->format('Y-m-d'),
            'opening' => $opening,
        ], [
            'organization_payment' => Types::INTEGER,
            'city_payment' => Types::INTEGER,
            'currency_payment' => Types::STRING,
            'from_payment' => Types::STRING,
            'organization_expense' => Types::INTEGER,
            'city_expense' => Types::INTEGER,
            'currency_expense' => Types::STRING,
            'from_expense' => Types::STRING,
            'opening' => Types::DECIMAL,
        ]);

        return is_string($value) ? $value : (string) $value;
    }
}
