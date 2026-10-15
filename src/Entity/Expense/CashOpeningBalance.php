<?php

declare(strict_types=1);

namespace App\Entity\Expense;

use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Entity\Property\City;
use App\Entity\Shared\TimestampedEntity;
use App\Enum\Currency;
use App\Repository\Expense\CashOpeningBalanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CashOpeningBalanceRepository::class)]
#[ORM\Table(name: 'cash_opening_balance')]
#[ORM\UniqueConstraint(name: 'uniq_cash_opening_city_currency', columns: ['city_id', 'currency'])]
#[ORM\Index(name: 'idx_cash_opening_org', columns: ['organization_id'])]
class CashOpeningBalance extends TimestampedEntity
{
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn(name: 'city_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private City $city;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private User $updatedBy;

    #[ORM\Column(type: Types::STRING, enumType: Currency::class, length: 3)]
    private Currency $currency;

    #[ORM\Column(name: 'opening_balance', type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $openingBalance;

    #[ORM\Column(name: 'opening_date', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $openingDate;

    public function getOrganization(): Organization { return $this->organization; }
    public function setOrganization(Organization $organization): static { $this->organization = $organization; return $this; }
    public function getCity(): City { return $this->city; }
    public function setCity(City $city): static { $this->city = $city; return $this; }
    public function getUpdatedBy(): User { return $this->updatedBy; }
    public function setUpdatedBy(User $user): static { $this->updatedBy = $user; return $this; }
    public function getCurrency(): Currency { return $this->currency; }
    public function setCurrency(Currency $currency): static { $this->currency = $currency; return $this; }
    public function getOpeningBalance(): string { return $this->openingBalance; }
    public function setOpeningBalance(string $balance): static { $this->openingBalance = $balance; return $this; }
    public function getOpeningDate(): \DateTimeImmutable { return $this->openingDate; }
    public function setOpeningDate(\DateTimeImmutable $date): static { $this->openingDate = $date; return $this; }
}
