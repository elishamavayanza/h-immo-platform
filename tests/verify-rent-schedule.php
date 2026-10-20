<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Enum\Currency;
use App\Service\Financial\CurrencyAmountCalculator;
use App\Service\Rental\RentScheduleCalculator;

$calculator = new RentScheduleCalculator(new CurrencyAmountCalculator());
$utc = new DateTimeZone('UTC');
$date = static fn (string $value): DateTimeImmutable => new DateTimeImmutable($value, $utc);
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) {
        throw new RuntimeException('Échec : ' . $message);
    }
};
$calculate = static fn (string $start, ?string $end, string $until, string $monthly = '100.00', Currency $currency = Currency::USD): array =>
    $calculator->calculate($date($start), $end === null ? null : $date($end), $date($until), $monthly, $currency);

$fullOctober = $calculate('2026-10-01', '2026-10-31', '2026-10-31');
$assert(count($fullOctober) === 1 && $fullOctober[0]['amount'] === '100.00', 'un mois complet conserve le loyer mensuel');
$assert($fullOctober[0]['coveredDays'] === 31 && $fullOctober[0]['daysInMonth'] === 31, 'un mois complet compte tous ses jours inclusivement');
$assert($fullOctober[0]['dueDate']->format('Y-m-d') === '2026-10-05', 'un mois complet garde le jour d’exigibilité du 5');

$e2e = $calculate('2026-10-20', '2027-10-19', '2027-12-01');
$assert(count($e2e) === 13, 'le bail E2E contient 13 périodes civiles inclusives');
$assert($e2e[0]['coveredStart']->format('Y-m-d') === '2026-10-20' && $e2e[0]['coveredEnd']->format('Y-m-d') === '2026-10-31', 'la première période inclut le 20 et le dernier jour du mois');
$assert($e2e[0]['coveredDays'] === 12 && $e2e[0]['daysInMonth'] === 31, 'octobre E2E couvre 12 jours sur 31');
$assert($e2e[0]['amount'] === '38.71', '100 USD proratisés sur 12/31 donnent 38,71 USD');
$assert($e2e[0]['dueDate']->format('Y-m-d') === '2026-10-20', 'la première échéance n’est pas exigible avant le début du bail');
$assert($e2e[1]['amount'] === '100.00' && $e2e[1]['coveredDays'] === 30, 'les périodes intermédiaires restent au loyer mensuel entier');
$assert($e2e[12]['coveredStart']->format('Y-m-d') === '2027-10-01' && $e2e[12]['coveredEnd']->format('Y-m-d') === '2027-10-19', 'la dernière période inclut le 19 octobre');
$assert($e2e[12]['coveredDays'] === 19 && $e2e[12]['daysInMonth'] === 31, 'la dernière période couvre 19 jours sur 31');
$assert($e2e[12]['amount'] === '61.29' && $e2e[12]['dueDate']->format('Y-m-d') === '2027-10-05', 'le dernier prorata vaut 61,29 USD et reste exigible dans le bail');
$sum = '0.00';
foreach ($e2e as $period) {
    $sum = bcadd($sum, $period['amount'], 2);
    $assert($period['dueDate'] >= $period['coveredStart'] && $period['dueDate'] <= $period['coveredEnd'], 'la date exigible reste dans la période contractuelle');
}
$assert($sum === '1200.00', 'le total proratisé du bail E2E vaut 1200,00 USD');

$monthLengths = [
    ['2025-02-10', '2025-02-10', 28, '3.57'],
    ['2024-02-10', '2024-02-10', 29, '3.45'],
    ['2026-04-10', '2026-04-10', 30, '3.33'],
    ['2026-10-10', '2026-10-10', 31, '3.23'],
];
foreach ($monthLengths as [$start, $end, $days, $expected]) {
    $row = $calculate($start, $end, $end, '100.00')[0];
    $assert($row['coveredDays'] === 1 && $row['daysInMonth'] === $days, sprintf('le mois %s emploie son nombre réel de jours', substr($start, 0, 7)));
    $assert($row['amount'] === $expected, sprintf('le prorata du mois de %d jours vaut %s USD', $days, $expected));
}

$leapFebruary = $calculate('2024-02-01', '2024-02-29', '2024-02-01')[0];
$assert($leapFebruary['coveredDays'] === 29 && $leapFebruary['amount'] === '100.00', 'février bissextile complet conserve le mois entier');
$oneMonth = $calculate('2026-04-10', '2026-04-20', '2026-04-30', '300.00')[0];
$assert($oneMonth['coveredDays'] === 11 && $oneMonth['amount'] === '110.00', 'un bail limité à un mois facture 11 jours inclusifs sur 30');
$assert($oneMonth['dueDate']->format('Y-m-d') === '2026-04-10', 'un bail court dont le cycle du 5 est passé devient exigible à son début');

$halfCent = $calculate('2026-04-01', '2026-04-15', '2026-04-30', '0.01')[0];
$assert($halfCent['amount'] === '0.01', 'un demi-centime est arrondi au centime supérieur selon half-up');

$cdfFull = $calculate('2026-04-01', '2026-04-30', '2026-04-30', '100.00', Currency::CDF)[0];
$assert($cdfFull['amount'] === '100.00', 'un mois CDF complet ne change pas le montant contractuel');
$cdfPartialBlocked = false;
try {
    $calculate('2026-04-02', '2026-04-30', '2026-04-30', '100.00', Currency::CDF);
} catch (DomainException $exception) {
    $cdfPartialBlocked = str_contains($exception->getMessage(), 'prorata CDF est suspendu');
}
$assert($cdfPartialBlocked, 'un prorata CDF est bloqué tant que sa précision et son arrondi ne sont pas décidés');

$assert($calculate('2026-10-20', '2026-10-19', '2026-10-31') === [], 'un bail dont la fin précède le début ne produit aucune période');
$assert($calculate('2026-11-01', null, '2026-10-31') === [], 'aucune période ne dépasse le mois demandé');

echo sprintf("OK : %d contrôles de prorata et de calendrier des échéances vérifiés.\n", $checks);
