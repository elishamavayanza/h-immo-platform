<?php

declare(strict_types=1);

namespace App\Service\Rental;

use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationRentalRuleVersion;
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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Résout les règles applicables sans modifier bail, version, échéance ou paiement. */
final readonly class RentalRuleResolutionService
{
    public function __construct(
        private OrganizationRentalRuleVersionLookupInterface $versionRepository,
        private ValidatorInterface $validator,
    ) {
    }

    public function resolve(
        Lease $lease,
        RentalBillingPeriod $period,
        Currency $currency,
        ?Organization $contextOrganization = null,
    ): ResolvedRentalRules {
        $organization = $this->validateContext($lease, $period, $currency, $contextOrganization);
        $this->validateLeaseOverrides($lease);

        $version = $this->versionRepository->findApplicableForPeriod($organization, $period->periodStart);
        if ($version !== null) {
            $this->validateOrganizationVersion($version, $organization, $period->periodStart);
        }

        $organizationRules = $version === null ? null : $this->rulesFromVersion($version);

        return $this->buildResult(
            $lease,
            $period,
            $currency,
            $organizationRules,
            $version,
            null,
            false,
            false,
        );
    }

    /** Résout une règle candidate en mémoire pour préparer une simulation sans écriture. */
    public function simulate(
        Lease $lease,
        RentalBillingPeriod $period,
        Currency $currency,
        RentalRuleCandidate $candidate,
        ?Organization $contextOrganization = null,
    ): ResolvedRentalRules {
        $organization = $this->validateContext($lease, $period, $currency, $contextOrganization);
        $this->validateLeaseOverrides($lease);

        if (!$this->sameOrganization($candidate->organization, $organization)) {
            throw new AccessDeniedException('La règle candidate ne correspond pas à l’organisation du bail.');
        }

        $versions = $this->versionRepository->findAllForOrganization($organization);
        $latestApplicable = null;
        foreach ($versions as $version) {
            if (!$this->sameOrganization($version->getOrganization(), $organization)) {
                throw new AccessDeniedException('Une version retournée appartient à une autre organisation.');
            }
            if ($version->getEffectiveFrom() === null) {
                throw new BadRequestHttpException('Une version publiée n’a pas de date d’effet valide.');
            }
            if ($version->getEffectiveFrom()->format('Y-m-d') === $candidate->effectiveFrom->format('Y-m-d')) {
                throw new ConflictHttpException('Une version existe déjà à cette date d’effet.');
            }
            if ($version->getEffectiveFrom()->format('Y-m-d') <= $period->periodStart->format('Y-m-d')) {
                if ($latestApplicable === null
                    || $version->getEffectiveFrom()->format('Y-m-d') > $latestApplicable->getEffectiveFrom()?->format('Y-m-d')
                ) {
                    $latestApplicable = $version;
                }
            }
        }

        $candidateApplies = $candidate->effectiveFrom->format('Y-m-d') <= $period->periodStart->format('Y-m-d')
            && ($latestApplicable === null
                || $candidate->effectiveFrom->format('Y-m-d') > $latestApplicable->getEffectiveFrom()?->format('Y-m-d'));

        $version = $candidateApplies ? null : $latestApplicable;
        if ($version !== null) {
            $this->validateOrganizationVersion($version, $organization, $period->periodStart);
        }

        return $this->buildResult(
            $lease,
            $period,
            $currency,
            $candidateApplies ? $candidate->rules : ($version === null ? null : $this->rulesFromVersion($version)),
            $version,
            $candidate,
            true,
            $candidateApplies,
        );
    }

    private function validateContext(
        Lease $lease,
        RentalBillingPeriod $period,
        Currency $currency,
        ?Organization $contextOrganization,
    ): Organization {
        $organization = $lease->getOrganization();
        if ($contextOrganization !== null && !$this->sameOrganization($contextOrganization, $organization)) {
            throw new AccessDeniedException('Le bail ne correspond pas à l’organisation du contexte.');
        }

        if ($currency !== $lease->getCurrency()) {
            throw new BadRequestHttpException('La devise de résolution doit être celle prévue au bail.');
        }

        $leaseStart = $lease->getStartDate()->format('Y-m-d');
        $coveredStart = $period->coveredStart->format('Y-m-d');
        $coveredEnd = $period->coveredEnd->format('Y-m-d');
        $leaseEnd = $lease->getEndDate()?->format('Y-m-d');
        if ($coveredStart < $leaseStart || ($leaseEnd !== null && $coveredEnd > $leaseEnd)) {
            throw new BadRequestHttpException('La période couverte doit rester dans les dates effectives du bail.');
        }

        return $organization;
    }

    private function validateLeaseOverrides(Lease $lease): void
    {
        $violations = $this->validator->validate($lease);
        if (count($violations) > 0) {
            throw new ValidationFailedException($lease, $violations);
        }
    }

    private function validateOrganizationVersion(
        OrganizationRentalRuleVersion $version,
        Organization $organization,
        \DateTimeImmutable $periodStart,
    ): void {
        if (!$this->sameOrganization($version->getOrganization(), $organization)) {
            throw new AccessDeniedException('La version de règles ne correspond pas à l’organisation du bail.');
        }

        if ($version->getEffectiveFrom() === null
            || $version->getEffectiveFrom()->format('d') !== '01'
            || $version->getEffectiveFrom()->format('Y-m-d') > $periodStart->format('Y-m-d')
        ) {
            throw new BadRequestHttpException('La version sélectionnée n’est pas valide pour cette période facturée.');
        }

        $violations = $this->validator->validate($version);
        if (count($violations) > 0) {
            throw new ValidationFailedException($version, $violations);
        }
    }

    private function rulesFromVersion(OrganizationRentalRuleVersion $version): RentalRuleSet
    {
        return new RentalRuleSet(
            $version->getDueMode(),
            $version->getDueDay(),
            $version->getFirstPartialPolicy(),
            $version->getLastPartialPolicy(),
            $version->getProrationMethod(),
            $version->getRoundingRule(),
        );
    }

    private function buildResult(
        Lease $lease,
        RentalBillingPeriod $period,
        Currency $currency,
        ?RentalRuleSet $organizationRules,
        ?OrganizationRentalRuleVersion $version,
        ?RentalRuleCandidate $candidate,
        bool $simulation,
        bool $candidateApplied,
    ): ResolvedRentalRules {
        $defaults = new RentalRuleSet(
            RentalPaymentMode::IN_ADVANCE,
            5,
            PartialRentPolicy::CLAMP_REGULAR_DUE_DAY,
            PartialRentPolicy::CLAMP_REGULAR_DUE_DAY,
            RentProrationMethod::ACTUAL_CALENDAR_DAYS,
            RentRoundingRule::USD_HALF_UP_CENT,
        );
        $fallback = $organizationRules ?? $defaults;
        $fallbackSource = $candidateApplied
            ? RentalRuleSource::ORGANIZATION_CANDIDATE
            : ($organizationRules === null
            ? RentalRuleSource::SOFT_IMMO_DEFAULT
            : RentalRuleSource::ORGANIZATION_VERSION);

        [$dueMode, $dueModeSource] = $this->resolveValue(
            $lease->getDueModeOverrideMode(),
            $lease->getDueModeOverrideValue(),
            $fallback->dueMode,
            $fallbackSource,
            $defaults->dueMode,
        );
        [$dueDay, $dueDaySource] = $this->resolveValue(
            $lease->getDueDayOverrideMode(),
            $lease->getDueDayOverrideValue(),
            $fallback->dueDay,
            $fallbackSource,
            $defaults->dueDay,
        );
        [$firstPolicy, $firstSource] = $this->resolveValue(
            $lease->getFirstPartialOverrideMode(),
            $lease->getFirstPartialOverrideValue(),
            $fallback->firstPartialPolicy,
            $fallbackSource,
            $defaults->firstPartialPolicy,
        );
        [$lastPolicy, $lastSource] = $this->resolveValue(
            $lease->getLastPartialOverrideMode(),
            $lease->getLastPartialOverrideValue(),
            $fallback->lastPartialPolicy,
            $fallbackSource,
            $defaults->lastPartialPolicy,
        );
        [$proration, $prorationSource] = $this->resolveValue(
            $lease->getProrationOverrideMode(),
            $lease->getProrationOverrideValue(),
            $fallback->prorationMethod,
            $fallbackSource,
            $defaults->prorationMethod,
        );
        [$rounding, $roundingSource] = $this->resolveValue(
            $lease->getRoundingOverrideMode(),
            $lease->getRoundingOverrideValue(),
            $fallback->roundingRule,
            $fallbackSource,
            $defaults->roundingRule,
        );

        $usesOrganizationVersion = in_array(RentalRuleSource::ORGANIZATION_VERSION, [
            $dueModeSource,
            $dueDaySource,
            $firstSource,
            $lastSource,
            $prorationSource,
            $roundingSource,
        ], true);
        $effectiveVersion = $usesOrganizationVersion ? $version : null;

        return new ResolvedRentalRules(
            $lease->getOrganization()->getUuid(),
            $lease->getUuid(),
            $period,
            $currency,
            $dueMode,
            $dueModeSource,
            $dueDay,
            $dueDaySource,
            $firstPolicy,
            $firstSource,
            $lastPolicy,
            $lastSource,
            $proration,
            $prorationSource,
            $rounding,
            $roundingSource,
            $effectiveVersion?->getId(),
            $effectiveVersion?->getUuid(),
            $effectiveVersion?->getVersionNumber(),
            $effectiveVersion?->getEffectiveFrom(),
            $effectiveVersion,
            $candidate,
            $simulation,
            $candidateApplied,
        );
    }

    /** @return array{0: mixed, 1: RentalRuleSource} */
    private function resolveValue(
        RentalRuleOverrideMode $mode,
        mixed $override,
        mixed $organizationValue,
        RentalRuleSource $organizationSource,
        mixed $defaultValue,
    ): array {
        if ($mode === RentalRuleOverrideMode::OVERRIDE) {
            return [$override, RentalRuleSource::LEASE_OVERRIDE];
        }

        if (in_array($organizationSource, [RentalRuleSource::ORGANIZATION_VERSION, RentalRuleSource::ORGANIZATION_CANDIDATE], true)) {
            return [$organizationValue, $organizationSource];
        }

        return [$defaultValue, RentalRuleSource::SOFT_IMMO_DEFAULT];
    }

    private function sameOrganization(?Organization $left, Organization $right): bool
    {
        if ($left === null) {
            return false;
        }

        if ($left->getId() !== null && $right->getId() !== null) {
            return $left->getId() === $right->getId();
        }

        return $left->getUuid()->equals($right->getUuid());
    }
}
