<?php

declare(strict_types=1);

namespace App\Service\Financial;

use App\Enum\Currency;
use DomainException;

/** Calculs de montants sans flottant avec une convention explicite pour USD. */
final class CurrencyAmountCalculator
{
    public function prorateUsd(string $monthlyAmount, int $coveredDays, int $daysInMonth): string
    {
        if ($coveredDays < 1 || $daysInMonth < 1 || $coveredDays > $daysInMonth) {
            throw new DomainException('Le nombre de jours couverts doit être compris entre 1 et le nombre de jours du mois.');
        }

        $monthlyCents = $this->toCents($monthlyAmount);
        $numerator = bcmul($monthlyCents, (string) $coveredDays, 0);

        return $this->formatCents($this->roundRatio($numerator, (string) $daysInMonth));
    }

    /** Arrondi commercial half-up au centime pour les montants USD. */
    public function roundUsd(string $amount): string
    {
        $scaledAmount = bcmul($amount, '10000000000', 0);

        return $this->formatCents($this->roundRatio($scaledAmount, '100000000'));
    }

    /** Multiplie un montant par un taux, en arrondissant USD half-up. */
    public function multiplyByRate(string $amount, string $rate, Currency $targetCurrency): string
    {
        if ($targetCurrency !== Currency::USD) {
            // La convention CDF n’est pas fixée : conserver le calcul existant
            // jusqu’à validation, sans lui appliquer l’arrondi USD.
            return bcmul($amount, $rate, 2);
        }

        $amountCents = $this->toCents($amount);
        $rateUnits = bcmul($rate, '100000000', 0);
        $numerator = bcmul($amountCents, $rateUnits, 0);

        return $this->formatCents($this->roundRatio($numerator, '100000000'));
    }

    /** Divise par un taux, en arrondissant USD half-up. */
    public function divideByRate(string $amount, string $rate, Currency $targetCurrency): string
    {
        if ($targetCurrency !== Currency::USD) {
            return bcdiv($amount, $rate, 2);
        }

        $amountCents = $this->toCents($amount);
        $rateUnits = bcmul($rate, '100000000', 0);
        if (bccomp($rateUnits, '0', 0) <= 0) {
            throw new DomainException('Le taux doit être strictement positif.');
        }
        $numerator = bcmul($amountCents, '100000000', 0);

        return $this->formatCents($this->roundRatio($numerator, $rateUnits));
    }

    private function toCents(string $amount): string
    {
        return bcmul($amount, '100', 0);
    }

    private function roundRatio(string $numerator, string $denominator): string
    {
        if (bccomp($denominator, '0', 0) <= 0) {
            throw new DomainException('Le dénominateur doit être strictement positif.');
        }

        $negative = bccomp($numerator, '0', 0) < 0;
        $absoluteNumerator = $negative ? bcmul($numerator, '-1', 0) : $numerator;
        $cents = bcdiv($absoluteNumerator, $denominator, 0);
        $remainder = bcmod($absoluteNumerator, $denominator);
        if (bccomp(bcmul($remainder, '2', 0), $denominator, 0) >= 0) {
            $cents = bcadd($cents, '1', 0);
        }

        return $negative && bccomp($cents, '0', 0) !== 0 ? bcmul($cents, '-1', 0) : $cents;
    }

    private function formatCents(string $cents): string
    {
        $negative = bccomp($cents, '0', 0) < 0;
        $absolute = $negative ? bcmul($cents, '-1', 0) : $cents;
        $formatted = bcdiv($absolute, '100', 2);

        return $negative ? '-' . $formatted : $formatted;
    }
}
