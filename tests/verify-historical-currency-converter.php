<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Entity\Financial\ExchangeRate;
use App\Entity\Identity\User;
use App\Enum\Currency;
use App\Service\Financial\CurrencyAmountCalculator;
use App\Service\Financial\HistoricalCurrencyConverter;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

$amountCalculator = new CurrencyAmountCalculator();
$converter = new HistoricalCurrencyConverter($amountCalculator);
$user = new User();
$utc = new DateTimeZone('UTC');
$date = static fn (string $value): DateTimeImmutable => new DateTimeImmutable($value, $utc);
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) {
        throw new RuntimeException('Échec : ' . $message);
    }
};

$historicalRate = new ExchangeRate(
    Currency::USD,
    Currency::CDF,
    '2900.00000000',
    $date('2026-09-01'),
    $user,
    $date('2026-10-01'),
);
$assert($historicalRate->isValidFor($date('2026-09-01')), 'la date de début du taux est incluse');
$assert($historicalRate->isValidFor($date('2026-09-30')), 'un taux antérieur reste valide jusqu’à sa date de fin exclusive');
$assert(!$historicalRate->isValidFor($date('2026-10-01')), 'la date de fin du taux est exclusive');
$assert($converter->convert('1.25', Currency::USD, Currency::CDF, $date('2026-09-30'), $historicalRate, null) === '3625.00', 'le taux historique direct est appliqué à sa période');

$inverseRate = new ExchangeRate(
    Currency::CDF,
    Currency::USD,
    '0.00050000',
    $date('2026-09-01'),
    $user,
);
$assert($converter->convert('500.00', Currency::USD, Currency::CDF, $date('2026-10-20'), null, $inverseRate) === '1000000.00', 'un taux inverse applicable permet la conversion dans le sens opposé');

$staleRate = new ExchangeRate(
    Currency::USD,
    Currency::CDF,
    '3000.00000000',
    $date('2026-08-01'),
    $user,
    $date('2026-09-01'),
);
$assert(!$staleRate->isValidFor($date('2026-10-20')), 'un taux expiré ne s’applique pas à une date ultérieure');
$expiredRateRejected = false;
try {
    $converter->convert('10.00', Currency::USD, Currency::CDF, $date('2026-10-20'), $staleRate, null);
} catch (UnprocessableEntityHttpException) {
    $expiredRateRejected = true;
}
$assert($expiredRateRejected, 'un taux expiré ne permet pas une conversion historique');

$latestApplicableRate = new ExchangeRate(
    Currency::USD,
    Currency::CDF,
    '3000.00000000',
    $date('2026-10-01'),
    $user,
);
$assert($converter->convert('1.00', Currency::USD, Currency::CDF, $date('2026-10-20'), $latestApplicableRate, null) === '3000.00', 'un taux ouvert depuis une date antérieure reste applicable');

$truncatingRate = new ExchangeRate(Currency::USD, Currency::CDF, '1.50000000', $date('2026-01-01'), $user);
$assert($converter->convert('1.01', Currency::USD, Currency::CDF, $date('2026-10-20'), $truncatingRate, null) === '1.51', 'la conversion conserve la précision actuelle à deux décimales (troncature BCMath)');
$assert($converter->convert('10.00', Currency::USD, Currency::USD, $date('2026-10-20'), null, null) === '10.00', 'une devise identique ne demande pas de taux');
$halfUpConversion = new ExchangeRate(Currency::USD, Currency::CDF, '2.00000000', $date('2026-01-01'), $user);
$assert($converter->convert('1.01', Currency::CDF, Currency::USD, $date('2026-10-20'), null, $halfUpConversion) === '0.51', 'une conversion historique vers USD utilise l’arrondi half-up');
$assert($amountCalculator->multiplyByRate('1.01', '1.50000000', Currency::USD) === '1.52', 'une conversion USD arrondit le demi-centime au supérieur');
$assert($amountCalculator->multiplyByRate('-1.01', '1.50000000', Currency::USD) === '-1.52', 'une contre-écriture USD arrondit symétriquement loin de zéro');
$assert($amountCalculator->multiplyByRate('1.01', '1.50000000', Currency::CDF) === '1.51', 'la convention CDF existante est conservée sans lui imposer l’arrondi USD');
$assert($amountCalculator->roundUsd('38.7096774193') === '38.71', 'le montant USD est arrondi au centime depuis une précision intermédiaire');

$missingRateRejected = false;
try {
    $converter->convert('10.00', Currency::USD, Currency::CDF, $date('2026-10-20'), null, null);
} catch (UnprocessableEntityHttpException $exception) {
    $missingRateRejected = str_contains($exception->getMessage(), 'aucun taux historique applicable');
}
$assert($missingRateRejected, 'une conversion sans taux historique direct ou inverse est refusée explicitement');

echo sprintf("OK : %d contrôles de conversion historique vérifiés.\n", $checks);
