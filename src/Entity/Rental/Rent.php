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
     * Taux de change utilisé si l'échéance a été convertie depuis une devise de référence.
     * Null si pas de conversion (échéance dans la devise du bail).
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 8, nullable: true)]
    private ?string $exchangeRate = null;

    /**
     * Montant original dans la devise de référence (si conversion effectuée).
     * Null si pas de conversion.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $originalAmount = null;

    /**
     * Devise de référence originale (si conversion effectuée).
     * Null si pas de conversion.
     */
    #[ORM\Column(type: Types::STRING, enumType: Currency::class, nullable: true)]
    private ?Currency $originalCurrency = null;

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

    public function getStatus(): RentStatus
    {
        return $this->status;
    }

    public function setStatus(RentStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Recalcule le statut à partir des paiements reçus.
     *
     * Le statut n'est jamais posé par le client : il est le résultat d'un
     * calcul. Laissé à la saisie, il se contredit — une échéance soldée
     * pouvait redevenir « pending » parce qu'un PATCH avait renvoyé la
     * valeur par défaut du DTO, et une échéance pouvait être déclarée
     * « payée » sans qu'aucun paiement n'ait été enregistré.
     *
     * Cette méthode ne gère QUE le statut dérivé des paiements :
     * PAID / PARTIALLY_PAID / PENDING.
     *
     * Le statut OVERDUE (impayé) est un état DÉRIVÉ À LA LECTURE :
     * il dépend de la date courante (dueDate < aujourd'hui) ET du
     * montant payé (< montant dû). Il n'est JAMAIS persisté en base
     * et se calcule à la volée via `isOverdue()` / `getComputedStatus()`.
     *
     * Un loyer soldé reste PAID même après sa date d'échéance : exiger
     * l'inverse ferait réapparaître des impayés sur des baux réglés.
     *
     * @param string $paidAmount total encaissé, au format décimal
     */
    public function syncStatus(string $paidAmount): RentStatus
    {
        // Utilisation de bcmath pour précision décimale exacte (pas de float)
        $paid = $paidAmount;
        $due = $this->amount;
        // 0.005 : deux centimes d'arrondi ne doivent pas faire conclure
        // qu'un solde de 499,995 sur 500 est partiel.
        $settled = bccomp($paid, $due, 2) === 0 || bccomp(bcadd($paid, '0.005', 2), $due, 2) >= 0;

        if ($settled) {
            $this->status = RentStatus::PAID;
        } elseif (bccomp($paid, '0.00', 2) > 0) {
            $this->status = RentStatus::PARTIALLY_PAID;
        } else {
            $this->status = RentStatus::PENDING;
        }

        return $this->status;
    }

    /**
     * Détermine si l'échéance est en retard (impayée) à la date donnée.
     *
     * Une échéance est en retard SI ET SEULEMENT SI :
     * - Sa date d'exigibilité (dueDate) est strictement antérieure à aujourd'hui
     * - ET le montant total payé est STRICTEMENT INFÉRIEUR au montant dû
     *
     * Cette méthode ne modifie PAS l'entité : elle calcule l'état à la volée
     * pour l'affichage / les rapports. Le statut persistant (status) ne
     * contient JAMAIS OVERDUE.
     *
     * @param string|null $paidAmount total encaissé (si null, sera récupéré via le repository si nécessaire)
     */
    public function isOverdue(?string $paidAmount = null, ?\DateTimeImmutable $today = null): bool
    {
        $today ??= new \DateTimeImmutable('today');

        // Si l'échéance est dans le futur ou aujourd'hui, pas en retard
        if ($this->dueDate >= $today) {
            return false;
        }

        // Si déjà soldée (PAID), jamais en retard
        if ($this->status === \App\Enum\RentStatus::PAID) {
            return false;
        }

        // Si montant payé non fourni, on considère qu'il faut le récupérer
        // Pour l'usage en entité pure, on suppose que l'appelant passe le montant
        if ($paidAmount === null) {
            // Sans montant, on ne peut pas trancher : on considère comme impayé
            // si le statut persistant n'est pas PAID
            return $this->status !== \App\Enum\RentStatus::PAID;
        }

        // Utilisation de bcmath pour précision décimale exacte (pas de float)
        // Impayé si payé < dû (avec tolérance d'arrondi de 0.005)
        return bccomp(bcadd($paidAmount, '0.005', 2), $this->amount, 2) < 0;
    }

    /**
     * Retourne le statut "affiché" (computed) incluant OVERDUE calculé à la volée.
     *
     * Utilisé pour l'affichage dans les DTOs de réponse et les rapports.
     * Ne modifie PAS le statut persisté.
     */
    public function getComputedStatus(?string $paidAmount = null, ?\DateTimeImmutable $today = null): \App\Enum\RentStatus
    {
        if ($this->status === \App\Enum\RentStatus::PAID) {
            return \App\Enum\RentStatus::PAID;
        }

        if ($this->isOverdue($paidAmount, $today)) {
            return \App\Enum\RentStatus::OVERDUE;
        }

        return $this->status;
    }
}
