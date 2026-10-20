<?php

declare(strict_types=1);

namespace App\Service\Rental;

use App\Entity\Rental\Lease;
use App\Enum\Currency;
use App\Enum\PartialRentPolicy;
use App\Enum\RentalPaymentMode;
use App\Enum\RentProrationMethod;
use App\Enum\RentRoundingRule;
use App\Service\Financial\CurrencyAmountCalculator;
use DomainException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/** Unique moteur de calcul des échéances utilisé par la génération et la saisie manuelle. */
final readonly class RentScheduleCalculator
{
    public const CALCULATOR_VERSION = 'rental-rules-v1';

    public function __construct(
        private CurrencyAmountCalculator $amountCalculator,
        private RentalRuleResolutionService $ruleResolver,
    ) {
    }

    /** @return list<CalculatedRentInstallment> */
    public function calculate(Lease $lease, \DateTimeImmutable $requestedEnd, ?RentalRuleCandidate $candidate = null): array
    {
        $installments = [];
        foreach ($this->periodsForLease($lease, $requestedEnd) as $period) {
            $installments[] = $this->calculatePeriod($lease, $period, $candidate);
        }

        return $installments;
    }

    /** @return list<\DateTimeImmutable> */
    public function periodsForLease(Lease $lease, \DateTimeImmutable $requestedEnd): array
    {
        $firstMonth = $lease->getStartDate()->modify('first day of this month');
        $lastMonth = $requestedEnd->modify('first day of this month');
        $effectiveEnd = $this->effectiveEnd($lease);
        if ($effectiveEnd !== null && $effectiveEnd < $lease->getStartDate()) {
            throw new DomainException('Le bail est incohérent : sa date de fin effective précède sa date de début.');
        }

        if ($effectiveEnd !== null && $effectiveEnd->modify('first day of this month') < $lastMonth) {
            $lastMonth = $effectiveEnd->modify('first day of this month');
        }

        if ($firstMonth > $lastMonth) {
            return [];
        }

        $periods = [];
        for ($month = $firstMonth; $month <= $lastMonth; $month = $month->modify('+1 month')) {
            $periods[] = $month;
        }

        return $periods;
    }

    public function calculatePeriod(Lease $lease, \DateTimeImmutable $period, ?RentalRuleCandidate $candidate = null): CalculatedRentInstallment
    {
        if ($period->format('d') !== '01') {
            throw new BadRequestHttpException('La période d’une échéance doit être le premier jour du mois.');
        }

        $leaseFirstMonth = $lease->getStartDate()->modify('first day of this month');
        $effectiveEnd = $this->effectiveEnd($lease);
        if ($period < $leaseFirstMonth
            || ($effectiveEnd !== null && $period > $effectiveEnd->modify('first day of this month'))
        ) {
            throw new BadRequestHttpException('La période demandée se trouve hors des dates effectives du bail.');
        }

        $monthEnd = $period->modify('last day of this month');
        $coveredStart = $period > $lease->getStartDate() ? $period : $lease->getStartDate();
        $coveredEnd = $effectiveEnd !== null && $effectiveEnd < $monthEnd ? $effectiveEnd : $monthEnd;
        if ($coveredStart > $coveredEnd) {
            throw new BadRequestHttpException('La période demandée se trouve hors des dates effectives du bail.');
        }

        $billingPeriod = new RentalBillingPeriod($period, $coveredStart, $coveredEnd);
        $rules = $candidate === null
            ? $this->ruleResolver->resolve($lease, $billingPeriod, $lease->getCurrency(), $lease->getOrganization())
            : $this->ruleResolver->simulate($lease, $billingPeriod, $lease->getCurrency(), $candidate, $lease->getOrganization());
        $coveredDays = (int) $coveredStart->diff($coveredEnd)->days + 1;
        $daysInMonth = (int) $period->format('t');
        $isPartial = $coveredDays !== $daysInMonth;
        $amount = $this->calculateAmount($lease, $rules, $coveredDays, $daysInMonth, $isPartial);
        $dueDate = $this->calculateDueDate($period, $coveredStart, $coveredEnd, $rules, $isPartial, $lease);

        return new CalculatedRentInstallment(
            $lease,
            $billingPeriod,
            $coveredDays,
            $daysInMonth,
            $amount,
            $lease->getCurrency(),
            $dueDate,
            $rules,
        );
    }

    private function calculateAmount(
        Lease $lease,
        ResolvedRentalRules $rules,
        int $coveredDays,
        int $daysInMonth,
        bool $isPartial,
    ): string {
        if (!$isPartial) {
            return $lease->getMonthlyRent();
        }

        if ($rules->currency === Currency::CDF) {
            throw new DomainException('Le prorata CDF est suspendu : sa précision, son arrondi et la gestion des écarts doivent être validés avant toute génération.');
        }
        if ($rules->prorationMethod !== RentProrationMethod::ACTUAL_CALENDAR_DAYS
            || $rules->roundingRule !== RentRoundingRule::USD_HALF_UP_CENT
        ) {
            throw new DomainException('La règle de prorata ou d’arrondi résolue n’est pas prise en charge par le calculateur.');
        }

        return $this->amountCalculator->prorateUsd($lease->getMonthlyRent(), $coveredDays, $daysInMonth);
    }

    private function calculateDueDate(
        \DateTimeImmutable $period,
        \DateTimeImmutable $coveredStart,
        \DateTimeImmutable $coveredEnd,
        ResolvedRentalRules $rules,
        bool $isPartial,
        Lease $lease,
    ): \DateTimeImmutable {
        if ($isPartial) {
            $firstMonth = $period->format('Y-m') === $lease->getStartDate()->format('Y-m');
            $effectiveEnd = $this->effectiveEnd($lease);
            $lastMonth = $effectiveEnd !== null && $period->format('Y-m') === $effectiveEnd->format('Y-m');
            if ($firstMonth && $lastMonth && $rules->firstPartialPolicy !== $rules->lastPartialPolicy) {
                throw new DomainException('Les règles du premier et du dernier mois partiel divergent pour un bail d’un seul mois.');
            }

            $policy = $firstMonth ? $rules->firstPartialPolicy : $rules->lastPartialPolicy;
            if ($policy === PartialRentPolicy::COVERAGE_START) {
                return $coveredStart;
            }
            if ($policy === PartialRentPolicy::COVERAGE_END) {
                return $coveredEnd;
            }
        }

        $dueMonth = $rules->dueMode === RentalPaymentMode::IN_ARREARS
            ? $period->modify('first day of next month')
            : $period;
        $day = min($rules->dueDay, (int) $dueMonth->format('t'));
        $dueDate = $dueMonth->setDate((int) $dueMonth->format('Y'), (int) $dueMonth->format('m'), $day);

        if ($dueDate < $coveredStart) {
            return $coveredStart;
        }
        if ($dueDate > $coveredEnd) {
            return $coveredEnd;
        }

        return $dueDate;
    }

    private function effectiveEnd(Lease $lease): ?\DateTimeImmutable
    {
        $end = $lease->getEndDate();
        $termination = $lease->getTerminationDate();
        if ($termination !== null && ($end === null || $termination < $end)) {
            return $termination;
        }

        return $end;
    }
}
