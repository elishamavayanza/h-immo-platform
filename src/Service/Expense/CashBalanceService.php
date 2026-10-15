<?php

declare(strict_types=1);

namespace App\Service\Expense;

use App\Dto\Request\Expense\CashOpeningBalanceRequest;
use App\Entity\Expense\CashOpeningBalance;
use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Entity\Property\City;
use App\Enum\Currency;
use App\Exception\AccessDeniedException;
use App\Repository\Expense\CashOpeningBalanceRepository;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Property\CityRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\System\AuditLogService;
use App\Service\System\DateTimeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class CashBalanceService
{
    public function __construct(
        private CashOpeningBalanceRepository $balances,
        private OrganizationRepository $organizations,
        private CityRepository $cities,
        private SecurityServiceInterface $security,
        private EntityManagerInterface $entityManager,
        private DateTimeService $dateTime,
        private AuditLogService $auditLog,
    ) {
    }

    /** @return array<string, mixed> */
    public function view(User $user, string $organizationUuid, string $cityUuid, string $currencyValue): array
    {
        [$organization, $city, $currency] = $this->resolveScope($user, $organizationUuid, $cityUuid, $currencyValue, SecurityAction::VIEW_PAYMENT);
        $opening = $this->balances->findForCity($city, $currency);
        $balance = $opening === null ? null : $this->balances->cashBalance($organization, $city, $currency, $opening->getOpeningDate(), $opening->getOpeningBalance());

        return [
            'cityUuid' => $city->getUuid()->toRfc4122(),
            'currency' => $currency->value,
            'openingBalance' => $opening?->getOpeningBalance(),
            'openingDate' => $opening?->getOpeningDate()->format('Y-m-d'),
            'balance' => $balance,
            'updatedAt' => $opening?->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public function set(User $user, string $organizationUuid, string $cityUuid, CashOpeningBalanceRequest $request): array
    {
        [$organization, $city, $currency] = $this->resolveScope($user, $organizationUuid, $cityUuid, $request->currency, SecurityAction::MANAGE_TREASURY_BALANCE);
        $date = $this->dateTime->parseDate($request->openingDate);
        if ($date === null || $date > $this->dateTime->today()) {
            throw new \InvalidArgumentException('La date du solde d’ouverture doit être valide et ne peut pas être future.');
        }

        $opening = $this->balances->findForCity($city, $currency);
        $oldValues = $opening === null ? null : ['openingBalance' => $opening->getOpeningBalance(), 'openingDate' => $opening->getOpeningDate()->format('Y-m-d')];
        if ($opening === null) {
            $opening = (new CashOpeningBalance())->setOrganization($organization)->setCity($city)->setCurrency($currency);
            $this->entityManager->persist($opening);
        }
        $opening->setUpdatedBy($user)->setOpeningBalance($this->normalizeDecimal($request->openingBalance))->setOpeningDate($date)->setUpdatedAt($this->dateTime->now());
        $this->entityManager->flush();
        $id = $opening->getId() ?? throw new \LogicException('Le solde de caisse n’a pas reçu son identifiant.');
        $this->auditLog->log('SET_CASH_OPENING_BALANCE', 'cash_opening_balance', $id, $organization, $user, $oldValues, [
            'currency' => $currency->value,
            'openingBalance' => $opening->getOpeningBalance(),
            'openingDate' => $date->format('Y-m-d'),
            'cityUuid' => $city->getUuid()->toRfc4122(),
        ]);

        return $this->view($user, $organizationUuid, $cityUuid, $currency->value);
    }

    /** @return array{Organization, City, Currency} */
    private function resolveScope(User $user, string $organizationUuid, string $cityUuid, string $currencyValue, SecurityAction $action): array
    {
        try {
            $organization = $this->organizations->findOneByUuid(Uuid::fromString($organizationUuid));
            $city = $this->cities->findOneByUuid(Uuid::fromString($cityUuid));
        } catch (\InvalidArgumentException) {
            $organization = null;
            $city = null;
        }
        $currency = Currency::tryFrom($currencyValue);
        if ($organization === null || $city === null || $city->getOrganization()->getId() !== $organization->getId() || $currency === null) {
            throw new \InvalidArgumentException('Organisation, ville ou devise invalide.');
        }
        if ($this->security->getOrganizationRole($user, $organization) === null) {
            throw new AccessDeniedException('Cet espace métier est réservé aux membres de l’organisation.');
        }
        $this->security->checkOrganizationAccess($organization, $action);
        $this->security->checkCityAccess($city, $action);

        return [$organization, $city, $currency];
    }

    private function normalizeDecimal(string $value): string
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;

        return $whole.'.'.str_pad($fraction, 2, '0');
    }
}
