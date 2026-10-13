<?php

declare(strict_types=1);

namespace App\Service\Financial;

use App\Dto\Feedback;
use App\Dto\Request\Financial\CityExchangeRatePermissionRequest;
use App\Dto\Request\Financial\ExchangeRateRequest;
use App\Dto\Request\Financial\OrganizationCurrencyRequest;
use App\Entity\Financial\ExchangeRate;
use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationUser;
use App\Entity\Property\City;
use App\Enum\Currency;
use App\Enum\OrganizationRole;
use App\Repository\Financial\ExchangeRateRepository;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Identity\OrganizationUserRepository;
use App\Repository\Property\CityRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\System\DateTimeService;
use App\Service\System\AuditLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Uid\Uuid;

/** Gestion des taux organisationnels et des dérogations temporaires par ville. */
final readonly class ExchangeRateService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ExchangeRateRepository $rates,
        private OrganizationRepository $organizations,
        private OrganizationUserRepository $memberships,
        private CityRepository $cities,
        private SecurityServiceInterface $security,
        private DateTimeService $dateTime,
        private ValidatorInterface $validator,
        private AuditLogService $auditLog,
    ) {
    }

    public function settings(string $organizationUuid): Feedback
    {
        $organization = $this->findOrganization($organizationUuid);
        if (!$organization instanceof Organization) {
            return $this->failure(404, 'Organisation introuvable.');
        }
        $user = $this->security->getCurrentUser();
        $role = $this->security->getOrganizationRole($user, $organization);
        $this->security->checkOrganizationAccess($organization, SecurityAction::VIEW);

        if ($role === OrganizationRole::ADMIN_IMMOBILIER) {
            $this->security->checkOrganizationAccess($organization, SecurityAction::MANAGE_EXCHANGE_RATE);
            $admins = array_map(function (OrganizationUser $membership) use ($organization): array {
                $assigned = $this->cities->findAssignedToUserInOrganization($membership->getUser(), $organization);
                return [
                    'uuid' => $membership->getUuid()->toRfc4122(),
                    'name' => $membership->getUser()->getFullName(),
                    'allowed' => $membership->canManageCityExchangeRate(),
                    'cities' => array_map(static fn(City $city): array => ['uuid' => $city->getUuid()->toRfc4122(), 'name' => $city->getName()], $assigned),
                ];
            }, $this->memberships->findByOrganizationAndRole($organization, OrganizationRole::ADMIN_VILLE));
            return (new Feedback())->setData([
                'scope' => 'organization',
                'organizationCurrency' => $organization->getCurrency()->value,
                'organizationRate' => $this->rateData($this->rates->findCurrentForScope(Currency::USD, Currency::CDF, $this->organizationScope($organization))),
                'cityAdmins' => $admins,
            ])->setStatus(200)->autoInitFlush();
        }

        if ($role !== OrganizationRole::ADMIN_VILLE) {
            return $this->failure(403, 'Ce réglage est réservé à l’administrateur immobilier ou aux administrateurs de ville autorisés.');
        }

        $membership = $this->memberships->findOneByOrganizationUserAndRole($organization, $user, OrganizationRole::ADMIN_VILLE);
        if (!$membership?->canManageCityExchangeRate()) {
            return (new Feedback())->setData(['scope' => 'none', 'enabled' => false, 'organizationCurrency' => $organization->getCurrency()->value])->setStatus(200)->autoInitFlush();
        }
        $availableCities = $this->cities->findAssignedToUserInOrganization($user, $organization);
        $cities = array_map(fn(City $city): array => [
            'uuid' => $city->getUuid()->toRfc4122(),
            'name' => $city->getName(),
            'rate' => $this->rateData($this->rates->findCurrentForScope(Currency::USD, Currency::CDF, $this->cityScope($city))),
        ], $availableCities);
        return (new Feedback())->setData(['scope' => 'city', 'enabled' => true, 'organizationCurrency' => $organization->getCurrency()->value, 'cities' => $cities])->setStatus(200)->autoInitFlush();
    }

    public function history(string $organizationUuid, string $dateValue): Feedback
    {
        $organization = $this->findOrganization($organizationUuid);
        if (!$organization instanceof Organization) return $this->failure(404, 'Organisation introuvable.');
        $user = $this->security->getCurrentUser();
        $role = $this->security->getOrganizationRole($user, $organization);
        $this->security->checkOrganizationAccess($organization, SecurityAction::VIEW);
        $date = $this->dateTime->parseDate($dateValue);
        if ($date === null) return $this->failure(422, 'La date sélectionnée est invalide.');
        // Le calendrier présente le taux applicable en fin de journée, afin
        // d’inclure un changement de taux enregistré pendant cette journée.
        $at = $date->modify('+1 day')->modify('-1 second');
        $allowCityRate = $role === OrganizationRole::ADMIN_IMMOBILIER;
        if ($role === OrganizationRole::ADMIN_VILLE) {
            $membership = $this->memberships->findOneByOrganizationUserAndRole($organization, $user, OrganizationRole::ADMIN_VILLE);
            $allowCityRate = $membership?->canManageCityExchangeRate() ?? false;
        }
        $rates = [];
        if ($role === OrganizationRole::ADMIN_IMMOBILIER) {
            $rate = $this->rates->findRateInScope(Currency::USD, Currency::CDF, $at, $organization->getId(), null);
            $rates[] = ['scope' => 'Organisation', 'rate' => $this->rateData($rate)];
        } elseif ($role === OrganizationRole::ADMIN_VILLE && $allowCityRate) {
            foreach ($this->cities->findAssignedToUserInOrganization($user, $organization) as $city) {
                $rate = $this->rates->findApplicableRate(Currency::USD, Currency::CDF, $at, $organization->getId(), $city->getId(), true);
                $source = $rate?->getCity() !== null ? 'Dérogation locale' : 'Taux de l’organisation';
                $rates[] = ['scope' => $city->getName(), 'source' => $source, 'rate' => $this->rateData($rate)];
            }
        }
        return (new Feedback())->setData(['date' => $dateValue, 'rates' => $rates])->setStatus(200)->autoInitFlush();
    }

    public function updateOrganizationCurrency(string $organizationUuid, OrganizationCurrencyRequest $request): Feedback
    {
        $organization = $this->findOrganization($organizationUuid);
        if (!$organization instanceof Organization) return $this->failure(404, 'Organisation introuvable.');
        $this->security->checkOrganizationAccess($organization, SecurityAction::MANAGE_EXCHANGE_RATE);
        if ($this->security->getOrganizationRole($this->security->getCurrentUser(), $organization) !== OrganizationRole::ADMIN_IMMOBILIER) {
            return $this->failure(403, 'Seul l’administrateur immobilier peut modifier la devise de référence.');
        }
        $violations = $this->validator->validate($request);
        if (count($violations) > 0 || $request->currency === null) {
            return (new Feedback())->bind($violations)->addError('currency', 'Choisissez une devise prise en charge.')
                ->setErrorFlushDescription('La devise sélectionnée est invalide.')->setStatus(422)->autoInitFlush();
        }
        $previous = $organization->getCurrency();
        if ($previous !== $request->currency) {
            $organization->setCurrency($request->currency);
            $this->em->flush();
            $this->auditLog->log('UPDATE_ORGANIZATION_CURRENCY', 'Organization', $organization->getId(), $organization, $this->security->getCurrentUser(),
                ['currency' => $previous->value], ['currency' => $request->currency->value]);
        }
        return (new Feedback())->setData(['currency' => $organization->getCurrency()->value])
            ->setFlushDescription('Devise de référence mise à jour.')->setStatus(200)->autoInitFlush();
    }

    public function updateOrganizationRate(string $organizationUuid, ExchangeRateRequest $request): Feedback
    {
        $organization = $this->findOrganization($organizationUuid);
        if (!$organization instanceof Organization) return $this->failure(404, 'Organisation introuvable.');
        $this->security->checkOrganizationAccess($organization, SecurityAction::MANAGE_EXCHANGE_RATE);
        if ($this->security->getOrganizationRole($this->security->getCurrentUser(), $organization) !== OrganizationRole::ADMIN_IMMOBILIER) {
            return $this->failure(403, 'Seul l’administrateur immobilier peut modifier le taux de l’organisation.');
        }
        return $this->saveRate($request, $organization, null);
    }

    public function updateCityRate(string $cityUuid, ExchangeRateRequest $request): Feedback
    {
        try {
            $city = $this->cities->findOneByUuid(Uuid::fromString($cityUuid));
        } catch (\InvalidArgumentException) {
            $city = null;
        }
        if (!$city instanceof City) return $this->failure(404, 'Ville introuvable.');
        $this->security->checkCityExchangeRateAccess($city);
        return $this->saveRate($request, $city->getOrganization(), $city);
    }

    public function setCityPermission(string $organizationUuid, CityExchangeRatePermissionRequest $request): Feedback
    {
        $organization = $this->findOrganization($organizationUuid);
        if (!$organization instanceof Organization) return $this->failure(404, 'Organisation introuvable.');
        $this->security->checkOrganizationAccess($organization, SecurityAction::MANAGE_EXCHANGE_RATE);
        if ($this->security->getOrganizationRole($this->security->getCurrentUser(), $organization) !== OrganizationRole::ADMIN_IMMOBILIER) {
            return $this->failure(403, 'Seul l’administrateur immobilier peut accorder cette autorisation.');
        }
        $membership = $this->memberships->findOneByUuidAndOrganization($request->organizationUserUuid ?? '', $organization);
        if (!$membership instanceof OrganizationUser || $membership->getRole() !== OrganizationRole::ADMIN_VILLE) {
            return $this->failure(404, 'Administrateur ville introuvable dans cette organisation.');
        }
        $previousPermission = $membership->canManageCityExchangeRate();
        $membership->setCanManageCityExchangeRate($request->allowed);
        if (!$request->allowed) {
            // L'ancien taux local ne doit pas réapparaître si le droit est accordé de nouveau plus tard.
            foreach ($this->cities->findAssignedToUserInOrganization($membership->getUser(), $organization) as $city) {
                if (!$this->memberships->hasCityExchangeRateManagerForCity($city)) {
                    $this->closeCurrentRate($this->cityScope($city));
                }
            }
        }
        $this->em->flush();
        if ($previousPermission !== $membership->canManageCityExchangeRate()) {
            $this->auditLog->log('UPDATE_CITY_EXCHANGE_RATE_PERMISSION', 'OrganizationUser', $membership->getId(), $organization, $this->security->getCurrentUser(),
                ['canManageCityExchangeRate' => $previousPermission], ['canManageCityExchangeRate' => $membership->canManageCityExchangeRate()]);
        }
        return (new Feedback())->setData(['allowed' => $membership->canManageCityExchangeRate()])->setFlushDescription('Autorisation mise à jour.')->setStatus(200)->autoInitFlush();
    }

    private function saveRate(ExchangeRateRequest $request, Organization $organization, ?City $city): Feedback
    {
        $violations = $this->validator->validate($request);
        if (count($violations) > 0 || $request->baseCurrency !== Currency::USD || $request->quoteCurrency !== Currency::CDF || $request->rate === null || bccomp($request->rate, '0', 8) <= 0) {
            $feedback = (new Feedback())->bind($violations);
            return $feedback->addError('rate', 'Indiquez deux devises différentes et un taux strictement positif (8 décimales maximum).')->setErrorFlushDescription('Le taux saisi est invalide.')->setStatus(422)->autoInitFlush();
        }
        $now = $this->dateTime->now();
        $scope = $city ? $this->cityScope($city) : $this->organizationScope($organization);
        $previousRate = $this->rates->findCurrentForScope($request->baseCurrency, $request->quoteCurrency, $scope);
        $this->closeCurrentRate($scope, $request->baseCurrency, $request->quoteCurrency, $now);
        $rate = new ExchangeRate($request->baseCurrency, $request->quoteCurrency, $request->rate, $now, $this->security->getCurrentUser(), null, $organization, $city);
        $this->rates->save($rate);
        $this->em->flush();
        $this->auditLog->log('CREATE_EXCHANGE_RATE', 'ExchangeRate', $rate->getId(), $organization, $this->security->getCurrentUser(),
            ['rate' => $previousRate?->getRate(), 'scope' => $city ? 'city' : 'organization'],
            ['rate' => $rate->getRate(), 'baseCurrency' => $rate->getBaseCurrency()->value, 'quoteCurrency' => $rate->getQuoteCurrency()->value, 'scope' => $city ? 'city' : 'organization', 'cityUuid' => $city?->getUuid()->toRfc4122()]);
        return (new Feedback())->setData($this->rateData($rate))->setFlushDescription('Taux de change enregistré.')->setStatus(200)->autoInitFlush();
    }

    private function closeCurrentRate(string $scope, ?Currency $base = null, ?Currency $quote = null, ?\DateTimeImmutable $at = null): void
    {
        $current = $this->rates->findCurrentForScope($base ?? Currency::USD, $quote ?? Currency::CDF, $scope);
        if ($current !== null) $current->setEffectiveTo($at ?? $this->dateTime->now());
    }

    private function rateData(?ExchangeRate $rate): ?array
    {
        return $rate === null ? null : ['baseCurrency' => $rate->getBaseCurrency()->value, 'quoteCurrency' => $rate->getQuoteCurrency()->value, 'rate' => $rate->getRate(), 'effectiveFrom' => $this->dateTime->format($rate->getEffectiveFrom(), 'Y-m-d H:i:s')];
    }

    private function organizationScope(Organization $organization): string { return 'organization:' . $organization->getUuid()->toRfc4122(); }
    private function cityScope(City $city): string { return 'city:' . $city->getUuid()->toRfc4122(); }
    private function findOrganization(string $uuid): ?Organization
    {
        try {
            return $this->organizations->findOneByUuid(Uuid::fromString($uuid));
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
    private function failure(int $status, string $message): Feedback { return (new Feedback())->setErrorFlushDescription($message)->setStatus($status)->autoInitFlush(); }
}
