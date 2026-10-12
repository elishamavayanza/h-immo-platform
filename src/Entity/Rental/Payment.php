<?php

declare(strict_types=1);

namespace App\Entity\Rental;

use App\Entity\Identity\User;
use App\Entity\Shared\TimestampedEntity;
use App\Enum\Currency;
use App\Enum\PaymentMethod;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Payment
 *
 * Package  : Rental Management
 * Table    : payment
 *
 * Paiement effectif reçu en règlement (total ou partiel) d'un Rent.
 * `createdBy` conserve l'utilisateur (agent) ayant encaissé/enregistré
 * le paiement, à des fins de traçabilité.
 */
#[ORM\Entity]
#[ORM\Table(name: 'payment')]
class Payment extends TimestampedEntity
{
    /**
     * Échéance de loyer réglée par le paiement.
     */
    #[ORM\ManyToOne(targetEntity: Rent::class)]
    #[ORM\JoinColumn(name: 'rent_id', referencedColumnName: 'id', nullable: false)]
    private Rent $rent;

    /**
     * Utilisateur ayant enregistré le paiement.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: false)]
    private User $createdBy;

    /**
     * Montant effectivement payé.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $amount;

    /**
     * Devise utilisée pour le paiement.
     */
    #[ORM\Column(type: Types::STRING, enumType: Currency::class)]
    private Currency $currency;

    /**
     * Taux de change utilisé (1 devise_originale = X devise_paiement).
     * Null si paiement dans la même devise que l'échéance.
     * Figé au moment de l'enregistrement pour traçabilité historique.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 8, nullable: true)]
    private ?string $exchangeRate = null;

    /**
     * Montant original dans la devise d'origine (si conversion effectuée).
     * Null si pas de conversion (même devise que l'échéance).
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $originalAmount = null;

    /**
     * Devise d'origine du paiement (si conversion effectuée).
     * Null si pas de conversion.
     */
    #[ORM\Column(type: Types::STRING, enumType: Currency::class, nullable: true)]
    private ?Currency $originalCurrency = null;

    /**
     * Date à laquelle le paiement a été effectué.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $paymentDate;

    /**
     * Mode de paiement utilisé.
     */
    #[ORM\Column(type: Types::STRING, enumType: PaymentMethod::class)]
    private PaymentMethod $method;

    /**
     * Référence externe du paiement.
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $reference = null;

    /**
     * Numéro du reçu associé au paiement.
     */
    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $receiptNumber = null;

    /**
     * Notes complémentaires sur le paiement.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /**
     * Écriture annulée par ce paiement, lorsqu'il s'agit d'une contre-écriture.
     *
     * Une contre-écriture porte un montant NÉGATIF égal à l'opposé de
     * l'original ; tous les agrégats (`SUM(amount)`) restent donc justes sans
     * avoir à filtrer les annulations. La relation est UNIQUE : une écriture ne
     * peut être annulée qu'une seule fois, y compris sous annulations
     * concurrentes (la contrainte d'unicité fait foi).
     */
    #[ORM\OneToOne(targetEntity: self::class, inversedBy: 'reversal')]
    #[ORM\JoinColumn(name: 'reversal_of_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?self $reversalOf = null;

    /**
     * Contre-écriture qui annule ce paiement, s'il a été annulé.
     *
     * Côté inverse de `reversalOf`, non propriétaire de la relation : il ne
     * sert qu'à savoir si un paiement est déjà annulé (`isReversed()`) sans
     * requête supplémentaire explicite.
     */
    #[ORM\OneToOne(targetEntity: self::class, mappedBy: 'reversalOf')]
    private ?self $reversal = null;

    public function getRent(): Rent
    {
        return $this->rent;
    }

    public function setRent(Rent $rent): static
    {
        $this->rent = $rent;

        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;

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

    public function getExchangeRate(): ?string
    {
        return $this->exchangeRate;
    }

    public function setExchangeRate(?string $exchangeRate): static
    {
        $this->exchangeRate = $exchangeRate;

        return $this;
    }

    public function getOriginalAmount(): ?string
    {
        return $this->originalAmount;
    }

    public function setOriginalAmount(?string $originalAmount): static
    {
        $this->originalAmount = $originalAmount;

        return $this;
    }

    public function getOriginalCurrency(): ?Currency
    {
        return $this->originalCurrency;
    }

    public function setOriginalCurrency(?Currency $originalCurrency): static
    {
        $this->originalCurrency = $originalCurrency;

        return $this;
    }

    public function getPaymentDate(): \DateTimeImmutable
    {
        return $this->paymentDate;
    }

    public function setPaymentDate(\DateTimeImmutable $paymentDate): static
    {
        $this->paymentDate = $paymentDate;

        return $this;
    }

    public function getMethod(): PaymentMethod
    {
        return $this->method;
    }

    public function setMethod(PaymentMethod $method): static
    {
        $this->method = $method;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getReceiptNumber(): ?string
    {
        return $this->receiptNumber;
    }

    public function setReceiptNumber(?string $receiptNumber): static
    {
        $this->receiptNumber = $receiptNumber;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    /**
     * Écriture d'origine annulée par ce paiement (null si ce n'est pas une
     * contre-écriture).
     */
    public function getReversalOf(): ?self
    {
        return $this->reversalOf;
    }

    public function setReversalOf(?self $reversalOf): static
    {
        $this->reversalOf = $reversalOf;

        return $this;
    }

    /**
     * Contre-écriture qui annule ce paiement (null s'il n'est pas annulé).
     */
    public function getReversal(): ?self
    {
        return $this->reversal;
    }

    /**
     * Ce paiement est-il une contre-écriture (annulation d'un autre paiement) ?
     */
    public function isReversal(): bool
    {
        return $this->reversalOf !== null;
    }

    /**
     * Ce paiement a-t-il déjà été annulé par une contre-écriture ?
     */
    public function isReversed(): bool
    {
        return $this->reversal !== null;
    }
}
