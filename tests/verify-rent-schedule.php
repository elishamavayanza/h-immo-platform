<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Entity\Identity\Organization;
use App\Entity\Rental\Lease;
use App\Enum\Currency;
use App\Enum\LeaseStatus;
use App\Enum\RentalPaymentMode;
use App\Repository\Identity\OrganizationRentalRuleVersionLookupInterface;
use App\Service\Financial\CurrencyAmountCalculator;
use App\Service\Rental\RentScheduleCalculator;
use App\Service\Rental\RentalRuleResolutionService;
use Symfony\Component\Validator\Validation;

$utc = new DateTimeZone('UTC');
$date = static fn (string $value): DateTimeImmutable => new DateTimeImmutable($value, $utc);
$organization = new Organization();
$versionRepository = new class implements OrganizationRentalRuleVersionLookupInterface {
    public function findApplicableForPeriod(Organization $organization, DateTimeImmutable $periodStart): ?App\Entity\Identity\OrganizationRentalRuleVersion { return null; }
    public function findAllForOrganization(Organization $organization): array { return []; }
};
$resolver = new RentalRuleResolutionService($versionRepository, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
$calculator = new RentScheduleCalculator(new CurrencyAmountCalculator(), $resolver);
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) {
        throw new RuntimeException('Échec : ' . $message);
    }
};
$makeLease = static function (string $start, ?string $end, string $monthly = '100.00', Currency $currency = Currency::USD) use ($organization, $date): Lease {
    return (new Lease())
        ->setOrganization($organization)
        ->setStartDate($date($start))
        ->setEndDate($end === null ? null : $date($end))
        ->setMonthlyRent($monthly)
        ->setCurrency($currency)
        ->setStatus(LeaseStatus::ACTIVE);
};

$fullOctober = $calculator->calculate($makeLease('2026-10-01', '2026-10-31'), $date('2026-10-31'));
$assert(count($fullOctober) === 1 && $fullOctober[0]->amount === '100.00', 'un mois complet conserve le loyer mensuel');
$assert($fullOctober[0]->coveredDays === 31 && $fullOctober[0]->daysInMonth === 31, 'un mois complet compte tous ses jours inclusivement');
$assert($fullOctober[0]->dueDate->format('Y-m-d') === '2026-10-05', 'un mois complet garde le jour d’exigibilité du 5');

$e2e = $calculator->calculate($makeLease('2026-10-20', '2027-10-19'), $date('2027-12-01'));
$assert(count($e2e) === 13, 'le bail E2E contient 13 périodes civiles inclusives');
$assert($e2e[0]->period->coveredStart->format('Y-m-d') === '2026-10-20' && $e2e[0]->period->coveredEnd->format('Y-m-d') === '2026-10-31', 'la première période inclut le 20 et le dernier jour du mois');
$assert($e2e[0]->coveredDays === 12 && $e2e[0]->daysInMonth === 31, 'octobre E2E couvre 12 jours sur 31');
$assert($e2e[0]->amount === '38.71', '100 USD proratisés sur 12/31 donnent 38,71 USD');
$assert($e2e[0]->dueDate->format('Y-m-d') === '2026-10-20', 'la première échéance n’est pas exigible avant le début du bail');
$assert($e2e[1]->amount === '100.00' && $e2e[1]->coveredDays === 30, 'les périodes intermédiaires restent au loyer mensuel entier');
$assert($e2e[12]->period->coveredStart->format('Y-m-d') === '2027-10-01' && $e2e[12]->period->coveredEnd->format('Y-m-d') === '2027-10-19', 'la dernière période inclut le 19 octobre');
$assert($e2e[12]->coveredDays === 19 && $e2e[12]->daysInMonth === 31, 'la dernière période couvre 19 jours sur 31');
$assert($e2e[12]->amount === '61.29' && $e2e[12]->dueDate->format('Y-m-d') === '2027-10-05', 'le dernier prorata vaut 61,29 USD et reste exigible le 5 octobre');
$middleDueDatesAreFifth = true;
foreach (array_slice($e2e, 1, 11) as $monthlyInstallment) {
    $middleDueDatesAreFifth = $middleDueDatesAreFifth && $monthlyInstallment->dueDate->format('d') === '05';
}
$assert($middleDueDatesAreFifth, 'les onze périodes mensuelles complètes sont exigibles le 5');
$sum = '0.00';
foreach ($e2e as $installment) {
    $sum = bcadd($sum, $installment->amount, 2);
    $assert($installment->dueDate >= $installment->period->coveredStart && $installment->dueDate <= $installment->period->coveredEnd, 'la date exigible reste dans la période couverte');
}
$assert($sum === '1200.00', 'le total proratisé du bail E2E vaut 1200,00 USD');
$differentDueDay = $makeLease('2026-10-20', '2027-10-19');
$differentDueDay->setDueDayOverrideMode(App\Enum\RentalRuleOverrideMode::OVERRIDE)->setDueDayOverrideValue(17);
$shiftedMonthly = $calculator->calculatePeriod($differentDueDay, $date('2026-11-01'));
$assert($shiftedMonthly->amount === $e2e[1]->amount && $shiftedMonthly->dueDate->format('Y-m-d') === '2026-11-17', 'modifier seulement le jour d’échéance change la date mais pas le montant mensuel');

$monthLengths = [
    ['2025-02-10', '2025-02-10', 28, '3.57'],
    ['2024-02-10', '2024-02-10', 29, '3.45'],
    ['2026-04-10', '2026-04-10', 30, '3.33'],
    ['2026-10-10', '2026-10-10', 31, '3.23'],
];
foreach ($monthLengths as [$start, $end, $days, $expected]) {
    $row = $calculator->calculate($makeLease($start, $end), $date($end))[0];
    $assert($row->coveredDays === 1 && $row->daysInMonth === $days, sprintf('le mois %s emploie son nombre réel de jours', substr($start, 0, 7)));
    $assert($row->amount === $expected, sprintf('le prorata du mois de %d jours vaut %s USD', $days, $expected));
}

$leapFebruary = $calculator->calculate($makeLease('2024-02-01', '2024-02-29'), $date('2024-02-01'))[0];
$assert($leapFebruary->coveredDays === 29 && $leapFebruary->amount === '100.00', 'février bissextile complet conserve le mois entier');
$oneMonth = $calculator->calculate($makeLease('2026-04-10', '2026-04-20', '300.00'), $date('2026-04-30'))[0];
$assert($oneMonth->coveredDays === 11 && $oneMonth->amount === '110.00', 'un bail limité à un mois facture 11 jours inclusifs sur 30');
$assert($oneMonth->dueDate->format('Y-m-d') === '2026-04-10', 'un bail court dont le cycle du 5 est passé devient exigible à son début');

$halfCent = $calculator->calculate($makeLease('2026-04-01', '2026-04-15', '0.01'), $date('2026-04-30'))[0];
$assert($halfCent->amount === '0.01', 'un demi-centime est arrondi au centime supérieur selon half-up');
$cdfFull = $calculator->calculate($makeLease('2026-04-01', '2026-04-30', '100.00', Currency::CDF), $date('2026-04-30'))[0];
$assert($cdfFull->amount === '100.00', 'un mois CDF complet ne change pas le montant contractuel');
try {
    $calculator->calculate($makeLease('2026-04-02', '2026-04-30', '100.00', Currency::CDF), $date('2026-04-30'));
    $assert(false, 'le prorata CDF doit être bloqué');
} catch (DomainException $exception) {
    $assert(str_contains($exception->getMessage(), 'prorata CDF est suspendu'), 'un prorata CDF reste bloqué avec un message explicite');
}

try {
    $calculator->calculate($makeLease('2026-10-20', '2026-10-19'), $date('2026-12-01'));
    $assert(false, 'un bail dont la fin précède le début doit être refusé, pas ignoré');
} catch (DomainException $exception) {
    $assert(str_contains($exception->getMessage(), 'date de fin effective précède'), 'un bail dont la fin précède le début est explicitement refusé');
}
$assert($calculator->calculate($makeLease('2026-11-01', null), $date('2026-10-31')) === [], 'aucune période ne dépasse le mois demandé');
try {
    $calculator->calculatePeriod($makeLease('2026-01-01', null), $date('2026-11-02'));
    $assert(false, 'une période manuelle qui ne commence pas le premier doit être refusée');
} catch (Symfony\Component\HttpKernel\Exception\BadRequestHttpException) {
    $assert(true, 'une période manuelle invalide est rejetée');
}
try {
    $calculator->calculatePeriod($makeLease('2026-05-02', '2026-10-19'), $date('2026-04-01'));
    $assert(false, 'une période antérieure au bail doit être refusée');
} catch (Symfony\Component\HttpKernel\Exception\BadRequestHttpException) {
    $assert(true, 'une période hors des limites du bail est rejetée');
}

$arrears = (new Lease())
    ->setOrganization($organization)
    ->setStartDate($date('2026-03-01'))
    ->setEndDate($date('2026-03-31'))
    ->setMonthlyRent('100.00')
    ->setCurrency(Currency::USD)
    ->setStatus(LeaseStatus::ACTIVE)
    ->setDueModeOverrideMode(App\Enum\RentalRuleOverrideMode::OVERRIDE)
    ->setDueModeOverrideValue(RentalPaymentMode::IN_ARREARS)
    ->setDueDayOverrideMode(App\Enum\RentalRuleOverrideMode::OVERRIDE)
    ->setDueDayOverrideValue(31);
$arrearsRow = $calculator->calculate($arrears, $date('2026-03-31'))[0];
$assert($arrearsRow->dueDate->format('Y-m-d') === '2026-03-31', 'le mode à terme échu vise le mois suivant puis reste borné à la période couverte');
$februaryDay31 = (new Lease())
    ->setOrganization($organization)
    ->setStartDate($date('2027-02-01'))
    ->setEndDate($date('2027-02-28'))
    ->setMonthlyRent('100.00')
    ->setCurrency(Currency::USD)
    ->setDueDayOverrideMode(App\Enum\RentalRuleOverrideMode::OVERRIDE)
    ->setDueDayOverrideValue(31);
$februaryRow = $calculator->calculate($februaryDay31, $date('2027-02-28'))[0];
$assert($februaryRow->dueDate->format('Y-m-d') === '2027-02-28', 'un jour 31 configuré est ramené au dernier jour du mois');

// Le même résultat typé alimente les deux chemins de persistance et son snapshot.
$rent = $e2e[0]->createRent();
$snapshot = $e2e[0]->createSnapshot($rent);
$assert($rent->getAmount() === $e2e[0]->amount && $rent->getDueDate() === $e2e[0]->dueDate, 'la création manuelle et la commande peuvent persister le même résultat calculé');
$assert($snapshot->getCoveredDays() === 12 && $snapshot->getCalculatorVersion() === RentScheduleCalculator::CALCULATOR_VERSION, 'le snapshot conserve le calcul et sa version');
$assert($snapshot->getRuleVersion() === null && $snapshot->getDueDaySource() === App\Enum\RentalRuleSource::SOFT_IMMO_DEFAULT, 'le snapshot reflète la provenance par défaut sans version fictive');

$commandSource = file_get_contents(dirname(__DIR__) . '/src/Command/Rental/GenerateRentsCommand.php');
$assert(str_contains($commandSource, 'scheduleCalculator->calculatePeriod('), 'la génération automatique utilise le calculateur partagé');
$assert(strpos($commandSource, 'findOneByLeaseAndPeriod($lease, $period)') < strpos($commandSource, 'calculatePeriod($lease, $period)'), 'la génération ignore les périodes déjà émises avant leur recalcul');
$assert(!str_contains($commandSource, 'new Payment('), 'la commande ne crée aucun paiement');
$assert(str_contains(file_get_contents(dirname(__DIR__) . '/src/Service/Rental/RentService.php'), 'scheduleCalculator->calculatePeriod('), 'la création manuelle utilise le même calculateur partagé');

echo sprintf("OK : %d contrôles du moteur partagé de calcul des échéances vérifiés.\n", $checks);
