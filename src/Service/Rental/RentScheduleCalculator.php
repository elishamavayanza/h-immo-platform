<?php

declare(strict_types=1);

namespace App\Service\Rental;

use App\Enum\Currency;
use App\Service\Financial\CurrencyAmountCalculator;
use DomainException;

/** Calcule le calendrier, les jours couverts et le montant de chaque loyer. */
final class RentScheduleCalculator
{
    public function __construct(private CurrencyAmountCalculator $amountCalculator)
    {
    }

    /**
     * @return list<array{
     *     period: \DateTimeImmutable,
     *     coveredStart: \DateTimeImmutable,
     *     coveredEnd: \DateTimeImmutable,
     *     coveredDays: int,
     *     daysInMonth: int,
     *     amount: string,
     *     dueDate: \DateTimeImmutable
     * }>
     */
    public function calculate(
        \DateTimeImmutable $leaseStart,
        ?\DateTimeImmutable $leaseEnd,
        \DateTimeImmutable $requestedEnd,
        string $monthlyRent,
        Currency $currency,
    ): array {
        if ($leaseEnd !== null && $leaseStart > $leaseEnd) {
            return [];
        }

        // `requestedEnd` désigne un mois inclusif (souvent normalisé au
        // premier jour), pas une date limite au jour près.
        $period = $leaseStart->modify('first day of this month');
        $lastPeriod = $requestedEnd->modify('first day of this month');
        if ($leaseEnd !== null) {
            $leaseEndPeriod = $leaseEnd->modify('first day of this month');
            if ($leaseEndPeriod < $lastPeriod) {
                $lastPeriod = $leaseEndPeriod;
            }
        }

        if ($period > $lastPeriod) {
            return [];
        }

        $schedule = [];
        while ($period <= $lastPeriod) {
            $monthEnd = $period->modify('last day of this month');
            $coveredStart = $period > $leaseStart ? $period : $leaseStart;
            $coveredEnd = $leaseEnd !== null && $leaseEnd < $monthEnd ? $leaseEnd : $monthEnd;
            $coveredDays = (int) $coveredStart->diff($coveredEnd)->days + 1;
            $daysInMonth = (int) $period->format('t');

            $dueDate = $period->modify('+4 days');
            if ($dueDate < $coveredStart) {
                $dueDate = $coveredStart;
            } elseif ($dueDate > $coveredEnd) {
                // Une échéance doit rester payable dans la période couverte.
                $dueDate = $coveredEnd;
            }

            $schedule[] = [
                'period' => $period,
                'coveredStart' => $coveredStart,
                'coveredEnd' => $coveredEnd,
                'coveredDays' => $coveredDays,
                'daysInMonth' => $daysInMonth,
                'amount' => $this->proratedAmount($monthlyRent, $currency, $coveredDays, $daysInMonth),
                'dueDate' => $dueDate,
            ];
            $period = $period->modify('+1 month');
        }

        return $schedule;
    }

    private function proratedAmount(string $monthlyRent, Currency $currency, int $coveredDays, int $daysInMonth): string
    {
        if ($coveredDays === $daysInMonth) {
            return $monthlyRent;
        }

        if ($currency !== Currency::USD) {
            throw new DomainException(
                'Le prorata CDF est suspendu : sa précision et sa règle d’arrondi doivent être définies avant toute génération.',
            );
        }

        return $this->amountCalculator->prorateUsd($monthlyRent, $coveredDays, $daysInMonth);
    }
}
