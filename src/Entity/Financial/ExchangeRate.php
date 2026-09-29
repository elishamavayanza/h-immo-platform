<?php

declare(strict_types=1);

namespace App\Entity\Financial;

use App\Entity\Identity\User;
use App\Entity\Shared\CreatedOnlyEntity;
use App\Enum\Currency;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * ExchangeRate
 *
 * Package : Financial
 * Table    : exchange_rate
 *
 * Taux de change historique entre deux devises.
 * Une entrée est immuable : le taux est figé à sa date d'effet.
 * Un changement de taux crée une nouvelle entrée (nouvelle période).
 * Cela garantit que les anciennes transactions conservent leur taux d'origine.
 */
#[ORM\Entity]
#[ORM\Table(name: 'exchange_rate')]
#[ORM\UniqueConstraint(name: 'uniq_exchange_rate_pair_from', columns: ['base_currency', 'quote_currency', 'effective_from'])]
#[ORM\Index(name: 'idx_exchange_rate_lookup', columns: ['base_currency', 'quote_currency', 'effective_from'])]
final class ExchangeRate extends CreatedOnlyEntity
{
    /**
     * Devise de base (ex: USD).
     */
    #[ORM\Column(type: Types::STRING, enumType: Currency::class)]
    private Currency $baseCurrency;

    /**
     * Devise cotée (ex: CDF).
     */
    #[ORM\Column(type: Types::STRING, enumType: Currency::class)]
    private Currency $quoteCurrency;

    /**
     * Taux de change : 1 baseCurrency = X quoteCurrency.
     * Stocké comme DECIMAL(18, 8) pour la précision (ex: 2900.00000000).
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 8)]
    private string $rate;

    /**
     * Date/heure d'effet du taux (inclusif).
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $effectiveFrom;

    /**
     * Date/heure de fin d'effet du taux (exclusif), null = taux actuel.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $effectiveTo = null;

    /**
     * Utilisateur ayant créé/validé ce taux.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: false)]
    private User $createdBy;

    public function __construct(
        Currency $baseCurrency,
        Currency $quoteCurrency,
        string $rate,
        \DateTimeImmutable $effectiveFrom,
        User $createdBy,
        ?\DateTimeImmutable $effectiveTo = null
    ) {
        if ($baseCurrency === $quoteCurrency) {
            throw new \InvalidArgumentException('La devise de base et la devise cotée doivent être différentes.');
        }
        if (bccomp($rate, '0', 8) <= 0) {
            throw new \InvalidArgumentException('Le taux de change doit être strictement positif.');
        }

        $this->baseCurrency = $baseCurrency;
        $this->quoteCurrency = $quoteCurrency;
        $this->rate = $rate;
        $this->effectiveFrom = $effectiveFrom;
        $this->effectiveTo = $effectiveTo;
        $this->createdBy = $createdBy;
    }

    public function getBaseCurrency(): Currency
    {
        return $this->baseCurrency;
    }

    public function getQuoteCurrency(): Currency
    {
        return $this->quoteCurrency;
    }

    public function getRate(): string
    {
        return $this->rate;
    }

    public function getEffectiveFrom(): \DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    public function getEffectiveTo(): ?\DateTimeImmutable
    {
        return $this->effectiveTo;
    }

    public function setEffectiveTo(\DateTimeImmutable $effectiveTo): self
    {
        $this->effectiveTo = $effectiveTo;
        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    /**
     * Vérifie si ce taux est valide pour une date donnée.
     */
    public function isValidFor(\DateTimeImmutable $date): bool
    {
        if ($date < $this->effectiveFrom) {
            return false;
        }
        if ($this->effectiveTo !== null && $date >= $this->effectiveTo) {
            return false;
        }
        return true;
    }

    /**
     * Convertit un montant de la devise de base vers la devise cotée.
     * $amount doit être dans la devise de base (baseCurrency).
     */
    public function convertBaseToQuote(string $amount): string
    {
        return bcmul($amount, $this->rate, 2);
    }

    /**
     * Convertit un montant de la devise cotée vers la devise de base.
     * $amount doit être dans la devise cotée (quoteCurrency).
     */
    public function convertQuoteToBase(string $amount): string
    {
        return bcdiv($amount, $this->rate, 2);
    }
}