<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationRentalRuleVersion;
use App\Entity\Identity\User;
use App\Entity\Rental\Lease;
use App\Enum\Currency;
use App\Enum\PartialRentPolicy;
use App\Enum\RentalPaymentMode;
use App\Enum\RentalRuleOverrideMode;
use App\Enum\RentalRuleSource;
use App\Enum\RentProrationMethod;
use App\Enum\RentRoundingRule;
use App\Exception\AccessDeniedException;
use App\Repository\Identity\OrganizationRentalRuleVersionLookupInterface;
use App\Service\Financial\CurrencyAmountCalculator;
use App\Service\Rental\RentalBillingPeriod;
use App\Service\Rental\RentalRuleCandidate;
use App\Service\Rental\RentalRuleResolutionService;
use App\Service\Rental\RentalRuleSet;
use App\Service\Rental\RentScheduleCalculator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validation;

$date = static fn (string $value): DateTimeImmutable => new DateTimeImmutable($value, new DateTimeZone('UTC'));
$organization = static fn (): Organization => new Organization();
$makeVersion = static function (
    Organization $organization,
    string $effectiveFrom,
    int $number,
    int $dueDay,
    RentalPaymentMode $mode = RentalPaymentMode::IN_ADVANCE,
): OrganizationRentalRuleVersion {
    return (new OrganizationRentalRuleVersion())
        ->setOrganization($organization)
        ->setVersionNumber($number)
        ->setEffectiveFrom(new DateTimeImmutable($effectiveFrom, new DateTimeZone('UTC')))
        ->setDueMode($mode)
        ->setDueDay($dueDay)
        ->setFirstPartialPolicy(PartialRentPolicy::CLAMP_REGULAR_DUE_DAY)
        ->setLastPartialPolicy(PartialRentPolicy::CLAMP_REGULAR_DUE_DAY)
        ->setProrationMethod(RentProrationMethod::ACTUAL_CALENDAR_DAYS)
        ->setRoundingRule(RentRoundingRule::USD_HALF_UP_CENT)
        ->setPublishedBy(new User())
        ->setPublicationReason('Version de test');
};
$makeLease = static function (Organization $organization, string $start = '2026-01-01', ?string $end = '2028-12-31'): Lease {
    return (new Lease())
        ->setOrganization($organization)
        ->setStartDate(new DateTimeImmutable($start, new DateTimeZone('UTC')))
        ->setEndDate($end === null ? null : new DateTimeImmutable($end, new DateTimeZone('UTC')))
    ->setMonthlyRent('100.00')
        ->setCurrency(Currency::USD);
};
$period = static fn (string $month, string $from, string $to): RentalBillingPeriod => new RentalBillingPeriod(
    new DateTimeImmutable($month . '-01', new DateTimeZone('UTC')),
    new DateTimeImmutable($from, new DateTimeZone('UTC')),
    new DateTimeImmutable($to, new DateTimeZone('UTC')),
);

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) {
        throw new RuntimeException('Échec : ' . $message);
    }
};
$expect = static function (callable $operation, string $exceptionClass) use ($assert): void {
    try {
        $operation();
    } catch (Throwable $exception) {
        $assert($exception instanceof $exceptionClass, 'exception attendue ' . $exceptionClass . ', reçue ' . $exception::class);
        return;
    }

    $assert(false, 'l’exception attendue ' . $exceptionClass . ' a été levée');
};

$versions = [];
$repository = new class($versions) implements OrganizationRentalRuleVersionLookupInterface {
    /** @var list<OrganizationRentalRuleVersion> */
    public array $versions;
    public int $readCount = 0;
    public bool $ignoreOrganizationFilter = false;

    /** @param list<OrganizationRentalRuleVersion> $versions */
    public function __construct(array $versions) { $this->versions = $versions; }

    public function findApplicableForPeriod(Organization $organization, DateTimeImmutable $periodStart): ?OrganizationRentalRuleVersion
    {
        ++$this->readCount;
        $matches = array_filter($this->versions, fn (OrganizationRentalRuleVersion $version): bool =>
            ($this->ignoreOrganizationFilter || $version->getOrganization()?->getUuid()->equals($organization->getUuid()) === true)
            && $version->getEffectiveFrom()?->format('Y-m-d') <= $periodStart->format('Y-m-d'));
        usort($matches, static fn (OrganizationRentalRuleVersion $left, OrganizationRentalRuleVersion $right): int =>
            $right->getEffectiveFrom() <=> $left->getEffectiveFrom());

        return $matches[0] ?? null;
    }

    public function findAllForOrganization(Organization $organization): array
    {
        ++$this->readCount;
        $matches = array_values(array_filter($this->versions, static fn (OrganizationRentalRuleVersion $version): bool =>
            $version->getOrganization()?->getUuid()->equals($organization->getUuid()) === true));
        usort($matches, static fn (OrganizationRentalRuleVersion $left, OrganizationRentalRuleVersion $right): int =>
            $left->getEffectiveFrom() <=> $right->getEffectiveFrom());

        return $matches;
    }
};
$resolver = new RentalRuleResolutionService($repository, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
$organizationA = $organization();
$organizationB = $organization();
$lease = $makeLease($organizationA);
$january = $period('2027-01', '2027-01-01', '2027-01-31');

// 1, 7, 8, 9, 13: defaults have explicit per-field provenance; free text is ignored.
$lease->setTerms('À terme échu le 20; texte informatif seulement.');
$resolvedDefault = $resolver->resolve($lease, $january, Currency::USD, $organizationA);
$assert($resolvedDefault->dueMode === RentalPaymentMode::IN_ADVANCE && $resolvedDefault->dueModeSource === RentalRuleSource::SOFT_IMMO_DEFAULT, 'sans règle, le mode par défaut est anticipé et sa source est explicite');
$assert($resolvedDefault->dueDay === 5 && $resolvedDefault->dueDaySource === RentalRuleSource::SOFT_IMMO_DEFAULT, 'sans règle, le jour par défaut est le 5');
$assert($resolvedDefault->firstPartialPolicy === PartialRentPolicy::CLAMP_REGULAR_DUE_DAY && $resolvedDefault->firstPartialPolicySource === RentalRuleSource::SOFT_IMMO_DEFAULT, 'la règle du premier mois partiel provient du défaut documenté');
$assert($resolvedDefault->lastPartialPolicy === PartialRentPolicy::CLAMP_REGULAR_DUE_DAY && $resolvedDefault->lastPartialPolicySource === RentalRuleSource::SOFT_IMMO_DEFAULT, 'la règle du dernier mois partiel provient du défaut documenté');
$assert($resolvedDefault->organizationVersionId === null && $resolvedDefault->organizationVersionUuid === null, 'aucune version d’organisation fictive n’est produite');
$assert($resolvedDefault->organizationUuid->equals($organizationA->getUuid()) && $resolvedDefault->leaseUuid->equals($lease->getUuid()), 'le résultat identifie le bail et l’organisation');
$serviceSource = file_get_contents(dirname(__DIR__) . '/src/Service/Rental/RentalRuleResolutionService.php');
$assert(!str_contains($serviceSource, 'getTerms'), 'le service n’interprète jamais les conditions libres du bail');
$assert(!str_contains($serviceSource, '->persist(') && !str_contains($serviceSource, '->flush('), 'la résolution et la simulation ne contiennent aucune écriture de persistance');

// 2–4: sélection exclusive de versions publiées applicables au premier jour facturé.
$versionA = $makeVersion($organizationA, '2026-09-01', 1, 8);
$versionB = $makeVersion($organizationA, '2027-01-01', 2, 12, RentalPaymentMode::IN_ARREARS);
$versionFuture = $makeVersion($organizationA, '2027-03-01', 3, 21);
$repository->versions = [$versionA, $versionB, $versionFuture];
$resolvedJanuary = $resolver->resolve($lease, $january, Currency::USD);
$assert($resolvedJanuary->dueDay === 12 && $resolvedJanuary->dueDaySource === RentalRuleSource::ORGANIZATION_VERSION, 'la version effective le premier jour de janvier est sélectionnée');
$assert($resolvedJanuary->dueMode === RentalPaymentMode::IN_ARREARS && $resolvedJanuary->dueModeSource === RentalRuleSource::ORGANIZATION_VERSION, 'un paramètre non surchargé reprend la version de l’organisation');
$assert($resolvedJanuary->organizationVersionNumber === 2 && $resolvedJanuary->organizationVersionEffectiveFrom?->format('Y-m-d') === '2027-01-01', 'la version sélectionnée est identifiable et datée');
$resolvedDecember = $resolver->resolve($lease, $period('2026-12', '2026-12-01', '2026-12-31'), Currency::USD);
$assert($resolvedDecember->dueDay === 8 && $resolvedDecember->organizationVersionNumber === 1, 'une version future ne s’applique pas à une période antérieure');
$resolvedMarch = $resolver->resolve($lease, $period('2027-03', '2027-03-01', '2027-03-31'), Currency::USD);
$assert($resolvedMarch->dueDay === 21 && $resolvedMarch->organizationVersionNumber === 3, 'parmi plusieurs versions la plus récente applicable gagne');

// 5–8: chaque paramètre est résolu séparément et une surcharge est prioritaire.
$lease
    ->setDueModeOverrideMode(RentalRuleOverrideMode::OVERRIDE)
    ->setDueModeOverrideValue(RentalPaymentMode::IN_ADVANCE)
    ->setDueDayOverrideMode(RentalRuleOverrideMode::INHERIT)
    ->setDueDayOverrideValue(null);
$resolvedMixed = $resolver->resolve($lease, $january, Currency::USD);
$assert($resolvedMixed->dueMode === RentalPaymentMode::IN_ADVANCE && $resolvedMixed->dueModeSource === RentalRuleSource::LEASE_OVERRIDE, 'la surcharge explicite du mode prime sur la version');
$assert($resolvedMixed->dueDay === 12 && $resolvedMixed->dueDaySource === RentalRuleSource::ORGANIZATION_VERSION, 'le jour hérité conserve sa source organisationnelle indépendamment du mode');
$assert($resolvedMixed->roundingRule === RentRoundingRule::USD_HALF_UP_CENT && $resolvedMixed->roundingRuleSource === RentalRuleSource::ORGANIZATION_VERSION, 'la source de l’arrondi est résolue indépendamment');
$lease
    ->setDueDayOverrideMode(RentalRuleOverrideMode::OVERRIDE)
    ->setDueDayOverrideValue(9)
    ->setFirstPartialOverrideMode(RentalRuleOverrideMode::OVERRIDE)
    ->setFirstPartialOverrideValue(PartialRentPolicy::COVERAGE_START)
    ->setLastPartialOverrideMode(RentalRuleOverrideMode::OVERRIDE)
    ->setLastPartialOverrideValue(PartialRentPolicy::COVERAGE_END)
    ->setProrationOverrideMode(RentalRuleOverrideMode::OVERRIDE)
    ->setProrationOverrideValue(RentProrationMethod::ACTUAL_CALENDAR_DAYS)
    ->setRoundingOverrideMode(RentalRuleOverrideMode::OVERRIDE)
    ->setRoundingOverrideValue(RentRoundingRule::USD_HALF_UP_CENT);
$resolvedAllOverrides = $resolver->resolve($lease, $january, Currency::USD);
$assert($resolvedAllOverrides->organizationVersion === null && $resolvedAllOverrides->organizationVersionId === null, 'une version entièrement masquée par les surcharges ne devient pas une fausse provenance de snapshot');

// 10–11: période invalide et contexte/version d’une autre organisation refusés.
$expect(static fn () => new RentalBillingPeriod($date('2027-01-02'), $date('2027-01-02'), $date('2027-01-31')), InvalidArgumentException::class);
$expect(static fn () => new RentalBillingPeriod($date('2027-01-01'), $date('2027-01-25'), $date('2027-01-10')), InvalidArgumentException::class);
$expect(static fn () => $resolver->resolve($lease, $period('2025-12', '2025-12-01', '2025-12-31'), Currency::USD), BadRequestHttpException::class);
$expect(static fn () => $resolver->resolve($lease, $january, Currency::USD, $organizationB), AccessDeniedException::class);
$lease->setDueDayOverrideMode(RentalRuleOverrideMode::OVERRIDE)->setDueDayOverrideValue(null);
$expect(static fn () => $resolver->resolve($lease, $january, Currency::USD), ValidationFailedException::class);
$lease->setDueDayOverrideMode(RentalRuleOverrideMode::INHERIT)->setDueDayOverrideValue(null);
$repository->versions = [$makeVersion($organizationB, '2027-01-01', 1, 11)];
$repository->ignoreOrganizationFilter = true;
$expect(static fn () => $resolver->resolve($lease, $january, Currency::USD), AccessDeniedException::class);
$repository->ignoreOrganizationFilter = false;
$expect(static fn () => $resolver->resolve($lease, $january, Currency::CDF), BadRequestHttpException::class);

// 12: la table représente uniquement des versions publiées; aucune propriété de brouillon n’existe.
$assert(!property_exists(OrganizationRentalRuleVersion::class, 'status') && !method_exists(OrganizationRentalRuleVersion::class, 'getStatus'), 'le modèle n’expose pas d’état brouillon susceptible d’être résolu');

// 14: le scénario candidat est lu en mémoire; aucun objet métier n’est persisté ou modifié.
$repository->versions = [$versionA, $versionB, $versionFuture];
$beforeCount = count($repository->versions);
$candidate = new RentalRuleCandidate(
    $organizationA,
    $date('2027-04-01'),
    new RentalRuleSet(RentalPaymentMode::IN_ADVANCE, 17, PartialRentPolicy::COVERAGE_START, PartialRentPolicy::COVERAGE_END, RentProrationMethod::ACTUAL_CALENDAR_DAYS, RentRoundingRule::USD_HALF_UP_CENT),
);
$lease->setDueDayOverrideMode(RentalRuleOverrideMode::INHERIT)->setDueDayOverrideValue(null);
$april = $period('2027-04', '2027-04-01', '2027-04-30');
$simulated = $resolver->simulate($lease, $april, Currency::USD, $candidate, $organizationA);
$assert($simulated->simulation && $simulated->candidateApplied && $simulated->dueDay === 17, 'la simulation applique la candidate à partir de sa date d’effet');
$assert($simulated->dueDaySource === RentalRuleSource::ORGANIZATION_CANDIDATE && $simulated->organizationVersionId === null && $simulated->simulatedCandidate === $candidate, 'la candidate est signalée sans inventer une version persistée');
$assert(count($repository->versions) === $beforeCount && $repository->readCount > 0, 'la simulation ne fait que lire les versions et n’en ajoute aucune');
$expect(static fn () => $resolver->simulate($lease, $april, Currency::USD, new RentalRuleCandidate($organizationB, $date('2027-04-01'), $candidate->rules)), AccessDeniedException::class);
$expect(static fn () => $resolver->simulate($lease, $april, Currency::USD, new RentalRuleCandidate($organizationA, $date('2027-01-01'), $candidate->rules)), ConflictHttpException::class);
$candidateCalculator = new RentScheduleCalculator(new CurrencyAmountCalculator(), $resolver);
$candidateInstallment = $candidateCalculator->calculatePeriod($lease, $date('2027-04-01'), $candidate);
$assert($candidateInstallment->dueDate->format('Y-m-d') === '2027-04-17' && $candidateInstallment->rules->candidateApplied, 'le calculateur applique la candidate sans modifier le montant ni persister de mouvement');

// 15–16: la résolution ne modifie pas le calcul, et le prorata CDF reste bloqué en aval.
$cdfLease = $makeLease($organizationA);
$cdfLease->setCurrency(Currency::CDF)->setStartDate($date('2027-05-02'));
$cdfPeriod = $period('2027-05', '2027-05-02', '2027-05-31');
$cdfRules = $resolver->resolve($cdfLease, $cdfPeriod, Currency::CDF);
$assert($cdfRules->currency === Currency::CDF, 'la devise est reportée dans le résultat sans conversion implicite');
$calculator = new RentScheduleCalculator(new CurrencyAmountCalculator(), $resolver);
$expect(static fn () => $calculator->calculate($cdfLease, $date('2027-05-31')), DomainException::class);
$versionedLease = $makeLease($organizationA, '2027-01-01', '2027-01-31');
$versionedInstallment = $calculator->calculatePeriod($versionedLease, $date('2027-01-01'));
$versionedRent = $versionedInstallment->createRent();
$versionedSnapshot = $versionedInstallment->createSnapshot($versionedRent);
$assert($versionedRent->getRuleVersion() === $versionB, 'une échéance conserve la version d’organisation réellement résolue');
$assert($versionedSnapshot->getRuleVersion() === $versionB && $versionedSnapshot->getDueDay() === 12, 'le snapshot associe les valeurs résolues à la même version publiée');

$repositorySource = file_get_contents(dirname(__DIR__) . '/src/Repository/Identity/OrganizationRentalRuleVersionRepository.php');
$assert(str_contains($repositorySource, 'version.organization = :organization') && str_contains($repositorySource, 'version.effectiveFrom <= :periodStart') && str_contains($repositorySource, "orderBy('version.effectiveFrom', 'DESC')"), 'la requête réelle borne le tenant et choisit la date d’effet la plus récente');

echo sprintf("OK : %d contrôles de résolution locative sans écriture en base.\n", $checks);
