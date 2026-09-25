<?php

declare(strict_types=1);

namespace App\Entity\Rental;

use App\Entity\Shared\TimestampedEntity;
use App\Enum\Currency;
use App\Enum\RentStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Rent
 *
 * Package  : Rental Management
 * Table    : rent
 *
 * Échéance de loyer mensuelle générée pour un Lease donné. Le couple
 * (lease, period) est unique : une seule échéance par bail et par
 * mois. Un Rent peut recevoir plusieurs Payment (paiements partiels).
 */
#[ORM\Entity]
#[ORM\Table(name: 'rent')]
#[ORM\UniqueConstraint(name: 'uniq_rent_lease_period', columns: ['lease_id', 'period'])]
class Rent extends TimestampedEntity
{
    /**
     * Contrat de bail auquel l'échéance est rattachée.
     */
    #[ORM\ManyToOne(targetEntity: Lease::class)]
    #[ORM\JoinColumn(name: 'lease_id', referencedColumnName: 'id', nullable: false)]
    private Lease $lease;

    /**
     * Période de l'échéance de loyer.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $period;

    /**
     * Date limite de paiement du loyer.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $dueDate;

    /**
     * Montant du loyer à payer.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $amount;

    /**
     * Devise utilisée pour le loyer.
     */
    #[ORM\Column(type: Types::STRING, enumType: Currency::class)]
    private Currency $currency;

    /**
     * État actuel de l'échéance de loyer.
     */
    #[ORM\Column(type: Types::STRING, enumType: RentStatus::class)]
    private RentStatus $status = RentStatus::PENDING;

    public function getLease(): Lease
    {
        return $this->lease;
    }

    public function setLease(Lease $lease): static
    {
        $this->lease = $lease;

        return $this;
    }

    public function getPeriod(): \DateTimeImmutable
    {
        return $this->period;
    }

    public function setPeriod(\DateTimeImmutable $period): static
    {
        $this->period = $period;

        return $this;
    }

    public function getDueDate(): \DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getStatus(): RentStatus
    {
        return $this->status;
    }

    public function setStatus(RentStatus $status): static
    {
        $this->status = $status;

        return $this;
    }
}
