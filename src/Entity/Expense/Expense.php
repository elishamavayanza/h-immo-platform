<?php

declare(strict_types=1);

namespace App\Entity\Expense;

use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Entity\Property\Building;
use App\Entity\Property\City;
use App\Entity\Property\Parcel;
use App\Entity\Property\Unit;
use App\Entity\Shared\TimestampedEntity;
use App\Entity\Staff\Worker;
use App\Enum\Currency;
use App\Enum\ExpenseCategory;
use App\Enum\PaymentMethod;
use App\Repository\Expense\ExpenseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Expense
 *
 * Package  : Expense Management
 * Table    : expense
 *
 * Dépense engagée dans le patrimoine : salaire, taxe, maintenance, charge
 * courante, frais de gestion, etc.
 *
 * `city` est toujours renseignée et `parcel`, `building` et `unit` le sont
 * au plus une à la fois. Cette graduation permet d'agréger le total des
 * dépenses par ville, par parcelle, par immeuble et par période, et rend
 * l'isolation par Organization et par ville vérifiable sur une seule
 * colonne. L'invariant de cohérence de la chaîne relève des services.
 *
 * `worker` est renseignée pour les dépenses de nature SALARY, ce qui relie
 * une dépense à l'affectation qui l'a motivée sans dupliquer le montant :
 * `monthlySalary` de WorkerAssignment reste le taux contractuel, cette
 * entité porte le règlement effectif.
 *
 * `createdBy` conserve l'utilisateur ayant enregistré la dépense, comme
 * pour Payment. L'historique est assuré par les horodatages de
 * TimestampedEntity et par le journal d'audit ; cette entité n'est donc pas
 * supprimable logiquement, à l'instar de Payment et Rent.
 */
#[ORM\Entity(repositoryClass: ExpenseRepository::class)]
#[ORM\Table(name: 'expense')]
#[ORM\Index(name: 'idx_expense_city_date', columns: ['city_id', 'expense_date'])]
#[ORM\Index(name: 'idx_expense_category', columns: ['category'])]
#[ORM\Index(name: 'idx_expense_worker', columns: ['worker_id'])]
class Expense extends TimestampedEntity
{
    /**
     * Organization supportant la dépense.
     */
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false)]
    private Organization $organization;

    /**
     * Ville sur laquelle porte la dépense.
     */
    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn(name: 'city_id', referencedColumnName: 'id', nullable: false)]
    private City $city;

    /**
     * Parcelle concernée, lorsqu'il s'agit d'une dépense à ce niveau.
     */
    #[ORM\ManyToOne(targetEntity: Parcel::class)]
    #[ORM\JoinColumn(name: 'parcel_id', referencedColumnName: 'id', nullable: true)]
    private ?Parcel $parcel = null;

    /**
     * Immeuble concerné, lorsqu'il s'agit d'une dépense à ce niveau.
     */
    #[ORM\ManyToOne(targetEntity: Building::class)]
    #[ORM\JoinColumn(name: 'building_id', referencedColumnName: 'id', nullable: true)]
    private ?Building $building = null;

    /**
     * Unité concernée, lorsqu'il s'agit d'une dépense à ce niveau.
     */
    #[ORM\ManyToOne(targetEntity: Unit::class)]
    #[ORM\JoinColumn(name: 'unit_id', referencedColumnName: 'id', nullable: true)]
    private ?Unit $unit = null;

    /**
     * Travailleur concerné, pour une dépense de nature SALARY.
     */
    #[ORM\ManyToOne(targetEntity: Worker::class)]
    #[ORM\JoinColumn(name: 'worker_id', referencedColumnName: 'id', nullable: true)]
    private ?Worker $worker = null;

    /**
     * Utilisateur ayant enregistré la dépense.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: false)]
    private User $createdBy;

    /**
     * Nature de la dépense.
     */
    #[ORM\Column(type: Types::STRING, enumType: ExpenseCategory::class)]
    private ExpenseCategory $category;

    /**
     * Montant de la dépense.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $amount;

    /**
     * Devise utilisée pour la dépense.
     */
    #[ORM\Column(type: Types::STRING, enumType: Currency::class)]
    private Currency $currency;

    /**
     * Taux de change utilisé (1 devise_originale = X devise_dépense).
     * Null si dépense dans la devise de référence de l'organisation.
     * Figé au moment de l'enregistrement pour traçabilité historique.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 8, nullable: true)]
    private ?string $exchangeRate = null;

    /**
     * Montant original dans la devise d'origine (si conversion effectuée).
     * Null si pas de conversion.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $originalAmount = null;

    /**
     * Devise d'origine de la dépense (si conversion effectuée).
     * Null si pas de conversion.
     */
    #[ORM\Column(type: Types::STRING, enumType: Currency::class, nullable: true)]
    private ?Currency $originalCurrency = null;

    /**
     * Date à laquelle la dépense a été engagée ou réglée.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $expenseDate;

    /**
     * Début de la période couverte par la dépense, lorsqu'elle couvre
     * un exercice (taxe annuelle, forfait annuel).
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $periodStart = null;

    /**
     * Fin de la période couverte par la dépense, lorsqu'elle couvre
     * un exercice.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $periodEnd = null;

    /**
     * Mode de règlement de la dépense.
     */
    #[ORM\Column(type: Types::STRING, enumType: PaymentMethod::class, nullable: true)]
    private ?PaymentMethod $method = null;

    /**
     * Tiers payeur : administration fiscale, fournisseur, entreprise de
     * travaux ou prestataire.
     */
    #[ORM\Column(type: Types::STRING, length: 200, nullable: true)]
    private ?string $supplier = null;

    /**
     * Référence interne ou externe de la dépense.
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $reference = null;

    /**
     * Numéro de la pièce justificative associée à la dépense.
     */
    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $receiptNumber = null;

    /**
     * Description ou notes complémentaires sur la dépense.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function setOrganization(Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    public function getCity(): City
    {
        return $this->city;
    }

    public function setCity(City $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getParcel(): ?Parcel
    {
        return $this->parcel;
    }

    public function setParcel(?Parcel $parcel): static
    {
        $this->parcel = $parcel;

        return $this;
    }

    public function getBuilding(): ?Building
    {
        return $this->building;
    }

    public function setBuilding(?Building $building): static
    {
        $this->building = $building;

        return $this;
    }

    public function getUnit(): ?Unit
    {
        return $this->unit;
    }

    public function setUnit(?Unit $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getWorker(): ?Worker
    {
        return $this->worker;
    }

    public function setWorker(?Worker $worker): static
    {
        $this->worker = $worker;

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

    public function getCategory(): ExpenseCategory
    {
        return $this->category;
    }

    public function setCategory(ExpenseCategory $category): static
    {
        $this->category = $category;

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

    public function getExpenseDate(): \DateTimeImmutable
    {
        return $this->expenseDate;
    }

    public function setExpenseDate(\DateTimeImmutable $expenseDate): static
    {
        $this->expenseDate = $expenseDate;

        return $this;
    }

    public function getPeriodStart(): ?\DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function setPeriodStart(?\DateTimeImmutable $periodStart): static
    {
        $this->periodStart = $periodStart;

        return $this;
    }

    public function getPeriodEnd(): ?\DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function setPeriodEnd(?\DateTimeImmutable $periodEnd): static
    {
        $this->periodEnd = $periodEnd;

        return $this;
    }

    public function getMethod(): ?PaymentMethod
    {
        return $this->method;
    }

    public function setMethod(?PaymentMethod $method): static
    {
        $this->method = $method;

        return $this;
    }

    public function getSupplier(): ?string
    {
        return $this->supplier;
    }

    public function setSupplier(?string $supplier): static
    {
        $this->supplier = $supplier;

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
}
