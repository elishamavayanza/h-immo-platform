<?php

declare(strict_types=1);

namespace App\Service\Financial;

use App\Entity\Financial\ExchangeRate;
use App\Enum\Currency;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Applique un taux historique déjà résolu dans son périmètre métier. */
final class HistoricalCurrencyConverter
{
    public function __construct(private CurrencyAmountCalculator $amountCalculator)
    {
    }

    public function convert(
        string $amount,
        Currency $fromCurrency,
        Currency $toCurrency,
        \DateTimeImmutable $date,
        ?ExchangeRate $directRate,
        ?ExchangeRate $inverseRate,
    ): string {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        if ($directRate !== null
            && $directRate->getBaseCurrency() === $fromCurrency
            && $directRate->getQuoteCurrency() === $toCurrency
            && $directRate->isValidFor($date)
        ) {
            return $this->amountCalculator->multiplyByRate($amount, $directRate->getRate(), $toCurrency);
        }

        if ($inverseRate !== null
            && $inverseRate->getBaseCurrency() === $toCurrency
            && $inverseRate->getQuoteCurrency() === $fromCurrency
            && $inverseRate->isValidFor($date)
        ) {
            return $this->amountCalculator->divideByRate($amount, $inverseRate->getRate(), $toCurrency);
        }

        throw new UnprocessableEntityHttpException(sprintf(
            'Le rapport ne peut pas convertir %s vers %s au %s : aucun taux historique applicable n’est enregistré.',
            $fromCurrency->value,
            $toCurrency->value,
            $date->format('Y-m-d'),
        ));
    }
}
