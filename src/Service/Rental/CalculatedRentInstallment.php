<?php

declare(strict_types=1);

namespace App\Service\Rental;

use App\Entity\Rental\Lease;
use App\Entity\Rental\Rent;
use App\Entity\Rental\RentRuleSnapshot;
use App\Enum\Currency;

/** Résultat complet et immuable du calcul d’une échéance et de sa provenance. */
final readonly class CalculatedRentInstallment
{
    public function __construct(
        public Lease $lease,
        public RentalBillingPeriod $period,
        public int $coveredDays,
        public int $daysInMonth,
        public string $amount,
        public Currency $currency,
        public \DateTimeImmutable $dueDate,
        public ResolvedRentalRules $rules,
    ) {
    }

    public function createRent(): Rent
    {
        return (new Rent())
            ->setLease($this->lease)
            ->setRuleVersion($this->rules->organizationVersion)
            ->setPeriod($this->period->periodStart)
            ->setDueDate($this->dueDate)
            ->setAmount($this->amount)
            ->setCurrency($this->currency);
    }

    public function createSnapshot(Rent $rent): RentRuleSnapshot
    {
        return (new RentRuleSnapshot())
            ->setRent($rent)
            ->setOrganization($this->lease->getOrganization())
            ->setRuleVersion($this->rules->organizationVersion)
            ->setCalculatorVersion(RentScheduleCalculator::CALCULATOR_VERSION)
            ->setCurrency($this->currency)
            ->setDueModeSource($this->rules->dueModeSource)
            ->setDueMode($this->rules->dueMode)
            ->setDueDaySource($this->rules->dueDaySource)
            ->setDueDay($this->rules->dueDay)
            ->setFirstPartialSource($this->rules->firstPartialPolicySource)
            ->setFirstPartialPolicy($this->rules->firstPartialPolicy)
            ->setLastPartialSource($this->rules->lastPartialPolicySource)
            ->setLastPartialPolicy($this->rules->lastPartialPolicy)
            ->setProrationSource($this->rules->prorationMethodSource)
            ->setProrationMethod($this->rules->prorationMethod)
            ->setRoundingSource($this->rules->roundingRuleSource)
            ->setRoundingRule($this->rules->roundingRule)
            ->setBaseMonthlyRent($this->lease->getMonthlyRent())
            ->setCoveredStart($this->period->coveredStart)
            ->setCoveredEnd($this->period->coveredEnd)
            ->setCoveredDays($this->coveredDays)
            ->setDaysInMonth($this->daysInMonth);
    }
}
