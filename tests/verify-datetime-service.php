<?php

declare(strict_types=1);

/*
 * Vérifie le service dates/heures centralisé (`DateTimeService`).
 *
 * Le service est sans dépendance : il est instancié directement, sans
 * conteneur ni base de données.
 *
 * Ce qui est prouvé ici :
 *  - `now()`, `today()`, `startOfCurrentYear()`, `endOfCurrentYear()` sont
 *    épinglés en UTC (le fuseau résultant est UTC quel que soit
 *    `date.timezone` du serveur) ;
 *  - `today()` est bien à minuit et au jour courant ;
 *  - les bornes d'année tombent le 1er janvier / 31 décembre de l'année
 *    courante (la fin d'année à minuit — sémantique du littéral PHP
 *    `'last day of December this year'`, reproduite à l'identique) ;
 *  - `parseDate()` accepte une date valide à minuit, refuse strictement une
 *    entrée débordée (2026-02-30) ou malformée, jamais d'exception ;
 *  - `parseDateTime()` lit un RFC3339 (avec fuseau) et l'épingle en UTC ;
 *  - `toTimezone()` change le fuseau sans changer l'instant absolu
 *    (Kinshasa = UTC+1) ;
 *  - `format()` sans fuseau reproduit exactement l'ancienne sortie
 *    `$date->format($format)` (aucun changement de comportement API), et
 *    avec `PRESENTATION_TIMEZONE` décale l'heure affichée.
 *
 * Usage : php tests/verify-datetime-service.php
 */

use App\Service\System\DateTimeService;

require dirname(__DIR__) . '/vendor/autoload.php';

$failures = 0;

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function check(bool $condition, string $label, mixed $expected = null, mixed $actual = null): void
{
    global $failures;

    if ($condition) {
        echo "  OK  {$label}\n";

        return;
    }

    ++$failures;
    echo "FAIL  {$label}";
    if (null !== $expected && null !== $actual) {
        printf(" (attendu: %s, obtenu: %s)", var_export($expected, true), var_export($actual, true));
    }
    echo "\n";
}

$service = new DateTimeService();

echo "DateTimeService — horloge et fuseau\n";

$now = $service->now();
check('UTC' === $now->getTimezone()->getName(), 'now() est épinglé en UTC', 'UTC', $now->getTimezone()->getName());
check($now->getTimestamp() === (int) (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->getTimestamp(), 'now() correspond à "maintenant"');

$today = $service->today();
check('UTC' === $today->getTimezone()->getName(), 'today() est épinglé en UTC', 'UTC', $today->getTimezone()->getName());
check('00:00:00' === $today->format('H:i:s'), 'today() est à minuit', '00:00:00', $today->format('H:i:s'));
check($today->format('Y-m-d') === (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d'), 'today() est au jour courant');

echo "DateTimeService — bornes d'année\n";

$start = $service->startOfCurrentYear();
check('UTC' === $start->getTimezone()->getName(), 'startOfCurrentYear() est en UTC', 'UTC', $start->getTimezone()->getName());
check('01-01' === $start->format('m-d') && '00:00:00' === $start->format('H:i:s'), 'startOfCurrentYear() = 1er janvier, minuit', '01-01 00:00:00', $start->format('m-d H:i:s'));
check($start->format('Y') === $today->format('Y'), 'startOfCurrentYear() est dans l\'année courante', $today->format('Y'), $start->format('Y'));

$end = $service->endOfCurrentYear();
check('UTC' === $end->getTimezone()->getName(), 'endOfCurrentYear() est en UTC', 'UTC', $end->getTimezone()->getName());
check('12-31' === $end->format('m-d') && '00:00:00' === $end->format('H:i:s'), 'endOfCurrentYear() = 31 décembre à minuit (sémantique du littéral PHP)', '12-31 00:00:00', $end->format('m-d H:i:s'));

echo "DateTimeService — parsing strict\n";

$date = $service->parseDate('2026-03-05');
check(null !== $date && '2026-03-05' === $date->format('Y-m-d') && '00:00:00' === $date->format('H:i:s'), 'parseDate() accepte une date valide à minuit', '2026-03-05 00:00:00', null !== $date ? $date->format('Y-m-d H:i:s') : null);
check('UTC' === $date?->getTimezone()->getName(), 'parseDate() épinglé en UTC', 'UTC', $date?->getTimezone()->getName());
check(null === $service->parseDate('pas-une-date'), 'parseDate() refuse une entrée malformée (null, pas d\'exception)');
check(null === $service->parseDate('2026-02-30'), 'parseDate() refuse une date inexistante (débordement)');
check(null === $service->parseDate('2026-03-05T10:00:00+00:00'), 'parseDate() refuse un jalon horaire');
check(null === $service->parseDate('2026-3-5'), 'parseDate() refuse une date non paddée');

$stamp = $service->parseDateTime('2026-03-05T10:30:00+00:00');
check(
    null !== $stamp && '10:30:00' === $stamp->format('H:i:s') && 'UTC' === $stamp->getTimezone()->getName(),
    'parseDateTime() lit un RFC3339 et l\'épingle en UTC',
);
$stampWithOffset = $service->parseDateTime('2026-03-05T11:30:00+01:00');
check(
    null !== $stampWithOffset && '10:30:00' === $stampWithOffset->format('H:i:s'),
    'parseDateTime() convertit un fuseau +01:00 vers UTC',
);
$stampSpace = $service->parseDateTime('2026-03-05 10:30:00');
check(
    null !== $stampSpace && '2026-03-05 10:30:00' === $stampSpace->format('Y-m-d H:i:s'),
    'parseDateTime() accepte le séparateur espace',
);
check(null === $service->parseDateTime('10:30:00'), 'parseDateTime() refuse une entrée incomplète');

echo "DateTimeService — présentation\n";

check('Africa/Kinshasa' === DateTimeService::PRESENTATION_TIMEZONE, 'PRESENTATION_TIMEZONE = Africa/Kinshasa (UTC+1)', 'Africa/Kinshasa', DateTimeService::PRESENTATION_TIMEZONE);

$kinshasa = $service->toTimezone($stamp);
check('Africa/Kinshasa' === $kinshasa->getTimezone()->getName(), 'toTimezone() change le fuseau', 'Africa/Kinshasa', $kinshasa->getTimezone()->getName());
check($kinshasa->getTimestamp() === $stamp->getTimestamp(), 'toTimezone() ne change pas l\'instant absolu');
check('11:30' === $kinshasa->format('H:i'), 'Kinshasa affiche une heure d\'avance sur UTC', '11:30', $kinshasa->format('H:i'));

$expectedFormat = (new \DateTimeImmutable('2020-06-15 08:45:00', new \DateTimeZone('UTC')))->format('Y-m-d H:i');
$viaService = $service->format(new \DateTimeImmutable('2020-06-15 08:45:00', new \DateTimeZone('UTC')), 'Y-m-d H:i');
check($viaService === $expectedFormat, 'format() sans fuseau = sortie inchangée (aucune régression API)', $expectedFormat, $viaService);

check(
    $service->format($stamp, 'Y-m-d H:i', DateTimeService::PRESENTATION_TIMEZONE) === '2026-03-05 11:30',
    'format() avec PRESENTATION_TIMEZONE décale l\'heure affichée',
    '2026-03-05 11:30',
    $service->format($stamp, 'Y-m-d H:i', DateTimeService::PRESENTATION_TIMEZONE),
);

if (0 === $failures) {
    echo "\nDateTimeService : tous les contrôles passent.\n";

    exit(0);
}

echo "\nDateTimeService : {$failures} contrôle(s) en échec.\n";

exit(1);