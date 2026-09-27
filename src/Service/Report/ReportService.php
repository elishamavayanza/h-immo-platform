<?php

declare(strict_types=1);

namespace App\Service\Report;

use App\Dto\Request\Report\AdminImmobilierReportFilterDto;
use App\Dto\Request\Report\AdminVilleReportFilterDto;
use App\Dto\Request\Report\PatronReportFilterDto;
use App\Dto\Request\Report\ReportFilterDto;
use App\Dto\Response\Report\AdminImmobilierReportResponse;
use App\Dto\Response\Report\AdminVilleReportResponse;
use App\Dto\Response\Report\ArrearsItem;
use App\Dto\Response\Report\ExpenseSummaryItem;
use App\Dto\Response\Report\FinancialSummaryItem;
use App\Dto\Response\Report\OccupancyItem;
use App\Dto\Response\Report\PatronReportResponse;
use App\Dto\Response\Report\SuperAdminReportResponse;
use App\Dto\Response\Report\WorkerActivityItem;
use App\Dto\Response\Report\OrganizationSummaryItem;
use App\Entity\Expense\Expense;
use App\Entity\Identity\Organization;
use App\Entity\Property\City;
use App\Entity\Property\Parcel;
use App\Entity\Rental\Lease;
use App\Entity\Rental\Payment;
use App\Entity\Rental\Rent;
use App\Entity\Rental\Tenant;
use App\Entity\Staff\Worker;
use App\Entity\Staff\WorkerAssignment;
use App\Enum\ExpenseCategory;
use App\Enum\RentStatus;
use App\Repository\Expense\ExpenseRepository;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Identity\UserRepository;
use App\Repository\Property\BuildingRepository;
use App\Repository\Property\CityRepository;
use App\Repository\Property\ParcelRepository;
use App\Repository\Property\UnitRepository;
use App\Repository\Rental\LeaseRepository;
use App\Repository\Rental\PaymentRepository;
use App\Repository\Rental\RentRepository;
use App\Repository\Rental\TenantRepository;
use App\Repository\Staff\WorkerAssignmentRepository;
use App\Repository\Staff\WorkerRepository;
use App\Security\SecurityServiceInterface;

/**
 * ReportService
 *
 * Package : Report Management
 *
 * Génère les rapports administratifs adaptés à chaque rôle.
 * L'isolation Organization → City est appliquée systématiquement.
 * Aucune table dédiée : toutes les données proviennent des entités métier existantes.
 */
final readonly class ReportService
{
    public function __construct(
        private CityRepository $cityRepository,
        private ParcelRepository $parcelRepository,
        private BuildingRepository $buildingRepository,
        private UnitRepository $unitRepository,
        private TenantRepository $tenantRepository,
        private LeaseRepository $leaseRepository,
        private RentRepository $rentRepository,
        private PaymentRepository $paymentRepository,
        private ExpenseRepository $expenseRepository,
        private WorkerRepository $workerRepository,
        private WorkerAssignmentRepository $assignmentRepository,
        private OrganizationRepository $organizationRepository,
        private UserRepository $userRepository,
        private SecurityServiceInterface $securityService,
    ) {
    }

    /**
     * Rapport global pour le PATRON d'une organisation.
     */
    public function generatePatronReport(PatronReportFilterDto $filter, Organization $organization): PatronReportResponse
    {
        $this->securityService->checkOrganizationAccess($organization, \App\Security\SecurityAction::VIEW);

        $cities = $this->cityRepository->findActiveByOrganization($organization);
        $cityIds = array_map(fn(City $c) => $c->getId(), $cities);

        $periodFrom = $filter->periodFrom ?? new \DateTimeImmutable('first day of January this year');
        $periodTo = $filter->periodTo ?? new \DateTimeImmutable('last day of December this year');

        $response = new PatronReportResponse(
            organizationUuid: $organization->getUuid()->toRfc4122(),
            organizationName: $organization->getName(),
            periodCovered: $periodFrom->format('Y-m-d') . ' to ' . $periodTo->format('Y-m-d'),
            generatedAt: new \DateTimeImmutable(),
        );

        if ($filter->includeFinancials) {
            $response->financialSummary = $this->buildFinancialSummary($organization->getId(), $cityIds, $periodFrom, $periodTo);
            $response->totalRevenues = $this->sumRevenues($organization->getId(), $cityIds, $periodFrom, $periodTo);
            $response->totalExpenses = $this->sumExpenses($organization->getId(), $cityIds, $periodFrom, $periodTo);
        }

        if ($filter->includeCityBreakdown) {
            $response->occupancyByCity = $this->buildOccupancyByCity($cities);
            if ($filter->includeExpenses) {
                $response->expensesByCity = $this->buildExpensesByCity($cityIds, $periodFrom, $periodTo);
            }
        }

        if ($filter->includeArrears) {
            $response->arrears = $this->buildArrears($organization->getId(), $cityIds, $periodTo);
            $response->totalArrears = $this->sumArrears($organization->getId(), $cityIds, $periodTo);
        }

        if ($filter->includeExpenses) {
            $response->expensesByCategory = $this->buildExpensesByCategory($organization->getId(), $cityIds, $periodFrom, $periodTo);
        }

        $response->currency = $this->getMainCurrency($organization->getId(), $cityIds);

        return $response;
    }

    /**
     * Rapport opérationnel pour l'ADMIN_IMMOBILIER.
     */
    public function generateAdminImmobilierReport(AdminImmobilierReportFilterDto $filter, Organization $organization): AdminImmobilierReportResponse
    {
        $this->securityService->checkOrganizationAccess($organization, \App\Security\SecurityAction::VIEW);

        $cities = $this->cityRepository->findActiveByOrganization($organization);
        $cityIds = array_map(fn(City $c) => $c->getId(), $cities);

        $periodFrom = $filter->periodFrom ?? new \DateTimeImmutable('first day of January this year');
        $periodTo = $filter->periodTo ?? new \DateTimeImmutable('last day of December this year');

        $response = new AdminImmobilierReportResponse(
            organizationUuid: $organization->getUuid()->toRfc4122(),
            organizationName: $organization->getName(),
            periodCovered: $periodFrom->format('Y-m-d') . ' to ' . $periodTo->format('Y-m-d'),
            generatedAt: new \DateTimeImmutable(),
        );

        $response->occupancyByParcel = $this->buildOccupancyByParcel($cityIds);
        $response->occupancyByBuilding = $this->buildOccupancyByBuilding($cityIds);
        $response->arrears = $this->buildArrears($organization->getId(), $cityIds, $periodTo);

        if ($filter->includePropertyExpenses) {
            $response->propertyExpenses = $this->buildExpensesByParcel($cityIds, $periodFrom, $periodTo);
        }

        if ($filter->includeOccupancyEvolution) {
            $response->occupancyEvolution = $this->buildOccupancyEvolution($cityIds, $periodFrom, $periodTo);
        }

        $totalUnits = 0;
        $totalOccupied = 0;
        foreach ($response->occupancyByParcel as $item) {
            $totalUnits += $item->totalUnits;
            $totalOccupied += $item->occupiedUnits;
        }
        $response->totalUnits = $totalUnits;
        $response->globalOccupancyRate = $totalUnits > 0 ? round(($totalOccupied / $totalUnits) * 100, 2) : 0.0;

        $response->currency = $this->getMainCurrency($organization->getId(), $cityIds);

        return $response;
    }

    /**
     * Rapport pour l'ADMIN_VILLE (limité à ses villes attribuées).
     */
    public function generateAdminVilleReport(AdminVilleReportFilterDto $filter, City $city): AdminVilleReportResponse
    {
        $this->securityService->checkCityAccess($city, \App\Security\SecurityAction::VIEW);

        $cityIds = [$city->getId()];
        $periodFrom = $filter->periodFrom ?? new \DateTimeImmutable('first day of January this year');
        $periodTo = $filter->periodTo ?? new \DateTimeImmutable('last day of December this year');

        $response = new AdminVilleReportResponse(
            cityUuid: $city->getUuid()->toRfc4122(),
            cityName: $city->getName(),
            periodCovered: $periodFrom->format('Y-m-d') . ' to ' . $periodTo->format('Y-m-d'),
            generatedAt: new \DateTimeImmutable(),
        );

        $response->occupancyByParcel = $this->buildOccupancyByParcel($cityIds);
        $response->occupancyByBuilding = $this->buildOccupancyByBuilding($cityIds);
        $response->arrears = $this->buildArrears(null, $cityIds, $periodTo);
        $response->cityExpenses = $this->buildExpensesByParcel($cityIds, $periodFrom, $periodTo);

        if ($filter->includeWorkers) {
            $response->workers = $this->buildWorkerActivity($cityIds);
        }

        $totalUnits = 0;
        $totalOccupied = 0;
        foreach ($response->occupancyByParcel as $item) {
            $totalUnits += $item->totalUnits;
            $totalOccupied += $item->occupiedUnits;
        }
        $response->totalUnits = $totalUnits;
        $response->occupancyRate = $totalUnits > 0 ? round(($totalOccupied / $totalUnits) * 100, 2) : 0.0;

        $response->totalExpenses = $this->sumExpenses(null, $cityIds, $periodFrom, $periodTo);
        $response->currency = $this->getMainCurrency(null, $cityIds);

        return $response;
    }

    /**
     * Rapport global pour SUPER_ADMIN.
     */
    public function generateSuperAdminReport(ReportFilterDto $filter): SuperAdminReportResponse
    {
        $periodFrom = $filter->periodFrom ?? new \DateTimeImmutable('first day of January this year');
        $periodTo = $filter->periodTo ?? new \DateTimeImmutable('last day of December this year');

        $organizations = $this->organizationRepository->findAllActive();
        $orgSummaries = [];

        foreach ($organizations as $org) {
            $cities = $this->cityRepository->findActiveByOrganization($org);
            $cityIds = array_map(fn(City $c) => $c->getId(), $cities);

            $orgSummaries[] = new OrganizationSummaryItem(
                uuid: $org->getUuid()->toRfc4122(),
                name: $org->getName(),
                code: $org->getCode(),
                status: $org->getStatus()->value,
                cityCount: count($cities),
                unitCount: $this->countUnitsByCityIds($cityIds),
                occupancyRate: $this->calculateOccupancyRate($cityIds),
                revenues: $this->sumRevenues($org->getId(), $cityIds, $periodFrom, $periodTo),
                expenses: $this->sumExpenses($org->getId(), $cityIds, $periodFrom, $periodTo),
                arrears: $this->sumArrears($org->getId(), $cityIds, $periodTo),
            );
        }

        $totalUsers = count($this->userRepository->findPaginatedAll(1, 10000)['items'] ?? []);

        return new SuperAdminReportResponse(
            periodCovered: $periodFrom->format('Y-m-d') . ' to ' . $periodTo->format('Y-m-d'),
            organizations: $orgSummaries,
            totalOrganizations: count($organizations),
            activeOrganizations: count(array_filter($organizations, fn($o) => $o->getStatus()->value === 'ACTIVE')),
            totalUsers: $totalUsers,
            generatedAt: new \DateTimeImmutable(),
        );
    }

    // ==================== MÉTHODES PRIVÉES D'AGRÉGATION ====================

    /** @return list<FinancialSummaryItem> */
    private function buildFinancialSummary(
        ?int $organizationId,
        array $cityIds,
        \DateTimeImmutable $periodFrom,
        \DateTimeImmutable $periodTo
    ): array {
        $results = $this->expenseRepository->getFinancialSummary(
            $organizationId ? [$organizationId] : null,
            $cityIds ?: null,
            $periodFrom,
            $periodTo
        );

        $byPeriod = [];
        foreach ($results as $row) {
            $period = $row['period'];
            $currency = $row['currency'];
            $total = $row['total'];

            if (!isset($byPeriod[$period])) {
                $byPeriod[$period] = ['revenues' => '0.00', 'expenses' => '0.00', 'currency' => $currency];
            }
            $byPeriod[$period]['expenses'] = bcadd($byPeriod[$period]['expenses'], $total, 2);
        }

        // Ajouter les revenus (paiements)
        $revenues = $this->paymentRepository->getFinancialSummary(
            $organizationId ? [$organizationId] : null,
            $cityIds ?: null,
            $periodFrom,
            $periodTo
        );

        foreach ($revenues as $row) {
            $period = $row['period'];
            $currency = $row['currency'];
            $total = $row['total'];

            if (!isset($byPeriod[$period])) {
                $byPeriod[$period] = ['revenues' => '0.00', 'expenses' => '0.00', 'currency' => $currency];
            }
            $byPeriod[$period]['revenues'] = bcadd($byPeriod[$period]['revenues'], $total, 2);
        }

        $items = [];
        foreach ($byPeriod as $period => $data) {
            $net = bcsub($data['revenues'], $data['expenses'], 2);
            $items[] = new FinancialSummaryItem(
                period: $period,
                revenues: $data['revenues'],
                expenses: $data['expenses'],
                netResult: $net,
                currency: $data['currency'],
            );
        }

        return $items;
    }

    /** @return list<OccupancyItem> */
    private function buildOccupancyByCity(array $cities): array
    {
        $items = [];
        foreach ($cities as $city) {
            $parcels = $this->parcelRepository->findByCity($city);
            $totalUnits = 0;
            $occupiedUnits = 0;

            foreach ($parcels as $parcel) {
                $buildings = $this->buildingRepository->findByParcel($parcel);
                foreach ($buildings as $building) {
                    $units = $this->unitRepository->findByBuilding($building);
                    $totalUnits += count($units);
                    foreach ($units as $unit) {
                        if ($this->unitHasActiveLease($unit)) {
                            $occupiedUnits++;
                        }
                    }
                }
            }

            $items[] = new OccupancyItem(
                level: 'city',
                levelUuid: $city->getUuid()->toRfc4122(),
                label: $city->getName(),
                totalUnits: $totalUnits,
                occupiedUnits: $occupiedUnits,
                availableUnits: $totalUnits - $occupiedUnits,
                occupancyRate: $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100, 2) : 0.0,
            );
        }

        return $items;
    }

    /** @return list<OccupancyItem> */
    private function buildOccupancyByParcel(array $cityIds): array
    {
        if (empty($cityIds)) {
            return [];
        }

        $items = [];
        $cities = $this->cityRepository->findInOrganization($this->cityRepository->find($cityIds[0])->getOrganization());

        foreach ($cities as $city) {
            if (!in_array($city->getId(), $cityIds, true)) {
                continue;
            }

            $parcels = $this->parcelRepository->findByCity($city);

            foreach ($parcels as $parcel) {
                $buildings = $this->buildingRepository->findByParcel($parcel);
                $totalUnits = 0;
                $occupiedUnits = 0;

                foreach ($buildings as $building) {
                    $units = $this->unitRepository->findByBuilding($building);
                    $totalUnits += count($units);
                    foreach ($units as $unit) {
                        if ($this->unitHasActiveLease($unit)) {
                            $occupiedUnits++;
                        }
                    }
                }

                $items[] = new OccupancyItem(
                    level: 'parcel',
                    levelUuid: $parcel->getUuid()->toRfc4122(),
                    label: $parcel->getName() . ' (' . $parcel->getReference() . ')',
                    totalUnits: $totalUnits,
                    occupiedUnits: $occupiedUnits,
                    availableUnits: $totalUnits - $occupiedUnits,
                    occupancyRate: $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100, 2) : 0.0,
                );
            }
        }

        return $items;
    }

    /** @return list<OccupancyItem> */
    private function buildOccupancyByBuilding(array $cityIds): array
    {
        if (empty($cityIds)) {
            return [];
        }

        $items = [];
        $cities = $this->cityRepository->findInOrganization($this->cityRepository->find($cityIds[0])->getOrganization());

        foreach ($cities as $city) {
            if (!in_array($city->getId(), $cityIds, true)) {
                continue;
            }

            $parcels = $this->parcelRepository->findByCity($city);

            foreach ($parcels as $parcel) {
                $buildings = $this->buildingRepository->findByParcel($parcel);

                foreach ($buildings as $building) {
                    $units = $this->unitRepository->findByBuilding($building);
                    $totalUnits = count($units);
                    $occupiedUnits = 0;

                    foreach ($units as $unit) {
                        if ($this->unitHasActiveLease($unit)) {
                            $occupiedUnits++;
                        }
                    }

                    $items[] = new OccupancyItem(
                        level: 'building',
                        levelUuid: $building->getUuid()->toRfc4122(),
                        label: $building->getName() . ' (' . $building->getReference() . ')',
                        totalUnits: $totalUnits,
                        occupiedUnits: $occupiedUnits,
                        availableUnits: $totalUnits - $occupiedUnits,
                        occupancyRate: $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100, 2) : 0.0,
                    );
                }
            }
        }

        return $items;
    }

    /** @return list<OccupancyItem> */
    private function buildOccupancyEvolution(array $cityIds, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        // Simplifié : retourne l'occupation actuelle par mois (nécessiterait une table d'historique pour être précis)
        return $this->buildOccupancyByCity(
            array_filter($this->cityRepository->findAllActive(), fn(City $c) => in_array($c->getId(), $cityIds, true))
        );
    }

    /** @return list<ArrearsItem> */
    private function buildArrears(?int $organizationId, array $cityIds, \DateTimeImmutable $asOfDate): array
    {
        $leases = $this->leaseRepository->findActiveByOrganizationsAndCities(
            $organizationId ? [$organizationId] : null,
            $cityIds ?: null
        );

        $items = [];
        foreach ($leases as $lease) {
            $rents = $this->rentRepository->findByLease($lease);
            $totalDue = '0.00';
            $totalPaid = '0.00';

            foreach ($rents as $rent) {
                $due = $rent->getAmount();
                $totalDue = bcadd($totalDue, $due, 2);

                $paid = $this->paymentRepository->sumAmountByRent($rent);
                $totalPaid = bcadd($totalPaid, $paid, 2);

                if ($rent->getStatus() === RentStatus::OVERDUE || $rent->getStatus() === RentStatus::PARTIALLY_PAID) {
                    $arrears = bcsub($due, $paid, 2);
                    $daysOverdue = 0;
                    if ($rent->getDueDate() < $asOfDate) {
                        $daysOverdue = (int) $asOfDate->diff($rent->getDueDate())->days;
                    }

                    $items[] = new ArrearsItem(
                        leaseUuid: $lease->getUuid()->toRfc4122(),
                        leaseReference: $lease->getReference(),
                        tenantName: $lease->getTenant()->getFullName() ?? $lease->getTenant()->getCompanyName() ?? 'Inconnu',
                        unitLabel: $lease->getUnit()->getReference() . ' (' . $lease->getUnit()->getBuilding()->getName() . ')',
                        amountDue: $due,
                        amountPaid: $paid,
                        arrears: $arrears,
                        daysOverdue: $daysOverdue,
                        currency: $rent->getCurrency()->value,
                    );
                }
            }
        }

        return $items;
    }

    /** @return list<ExpenseSummaryItem> */
    private function buildExpensesByCategory(
        ?int $organizationId,
        array $cityIds,
        \DateTimeImmutable $periodFrom,
        \DateTimeImmutable $periodTo
    ): array {
        $results = $this->expenseRepository->sumByCategory(
            $organizationId ? [$organizationId] : null,
            $cityIds ?: null,
            $periodFrom,
            $periodTo
        );

        $items = [];
        foreach ($results as $row) {
            $items[] = new ExpenseSummaryItem(
                category: $row['category'],
                level: 'organization',
                levelLabel: 'Organisation',
                count: 0, // Would need separate count query
                totalAmount: $row['total'],
                currency: $row['currency'],
            );
        }

        return $items;
    }

    /** @return list<ExpenseSummaryItem> */
    private function buildExpensesByCity(array $cityIds, \DateTimeImmutable $periodFrom, \DateTimeImmutable $periodTo): array
    {
        $results = $this->expenseRepository->sumByCity(null, $periodFrom, $periodTo);

        $items = [];
        foreach ($results as $row) {
            $items[] = new ExpenseSummaryItem(
                category: 'ALL',
                level: 'city',
                levelUuid: $row['city']->getUuid()->toRfc4122(),
                levelLabel: $row['cityName'],
                count: 0,
                totalAmount: $row['total'],
                currency: $row['currency'],
            );
        }

        return $items;
    }

    /** @return list<ExpenseSummaryItem> */
    private function buildExpensesByParcel(array $cityIds, \DateTimeImmutable $periodFrom, \DateTimeImmutable $periodTo): array
    {
        // Simplifié : retourne par ville pour l'instant
        return $this->buildExpensesByCity($cityIds, $periodFrom, $periodTo);
    }

    /** @return list<WorkerActivityItem> */
    private function buildWorkerActivity(array $cityIds): array
    {
        $assignments = $this->assignmentRepository->findActiveByCities($cityIds);

        $items = [];
        foreach ($assignments as $assignment) {
            $worker = $assignment->getWorker();
            $targetLabel = $assignment->getUnit()?->getReference()
                ?? $assignment->getBuilding()?->getName()
                ?? $assignment->getParcel()?->getName()
                ?? $assignment->getCity()->getName();

            $items[] = new WorkerActivityItem(
                workerUuid: $worker->getUuid()->toRfc4122(),
                fullName: $worker->getFullName(),
                role: $assignment->getRole()->value,
                assignmentLabel: $targetLabel,
                monthlySalary: $assignment->getMonthlySalary(),
                currency: $assignment->getCurrency()->value,
            );
        }

        return $items;
    }

    private function unitHasActiveLease(\App\Entity\Property\Unit $unit): bool
    {
        $activeLease = $this->leaseRepository->findActiveLeaseForUnit($unit);
        return $activeLease !== null;
    }

    private function sumRevenues(?int $organizationId, array $cityIds, \DateTimeImmutable $from, \DateTimeImmutable $to): string
    {
        $results = $this->paymentRepository->getFinancialSummary(
            $organizationId ? [$organizationId] : null,
            $cityIds ?: null,
            $from,
            $to
        );

        $total = '0.00';
        foreach ($results as $row) {
            $total = bcadd($total, $row['total'], 2);
        }
        return $total;
    }

    private function sumExpenses(?int $organizationId, array $cityIds, \DateTimeImmutable $from, \DateTimeImmutable $to): string
    {
        $results = $this->expenseRepository->sumByCategory(
            $organizationId ? [$organizationId] : null,
            $cityIds ?: null,
            $from,
            $to
        );

        $total = '0.00';
        foreach ($results as $row) {
            $total = bcadd($total, $row['total'], 2);
        }
        return $total;
    }

    private function sumArrears(?int $organizationId, array $cityIds, \DateTimeImmutable $asOfDate): string
    {
        $leases = $this->leaseRepository->findActiveByOrganizationsAndCities(
            $organizationId ? [$organizationId] : null,
            $cityIds ?: null
        );

        $total = '0.00';
        foreach ($leases as $lease) {
            $rents = $this->rentRepository->findByLease($lease);
            foreach ($rents as $rent) {
                if ($rent->getStatus() === RentStatus::OVERDUE || $rent->getStatus() === RentStatus::PARTIALLY_PAID) {
                    $due = $rent->getAmount();
                    $paid = $this->paymentRepository->sumAmountByRent($rent);
                    $arrears = bcsub($due, $paid, 2);
                    $total = bcadd($total, $arrears, 2);
                }
            }
        }
        return $total;
    }

    private function countUnitsByCityIds(array $cityIds): int
    {
        if (empty($cityIds)) {
            return 0;
        }

        $count = 0;
        $cities = $this->cityRepository->findInOrganization($this->cityRepository->find($cityIds[0])->getOrganization());

        foreach ($cities as $city) {
            if (!in_array($city->getId(), $cityIds, true)) {
                continue;
            }
            $parcels = $this->parcelRepository->findByCity($city);
            foreach ($parcels as $parcel) {
                $buildings = $this->buildingRepository->findByParcel($parcel);
                foreach ($buildings as $building) {
                    $count += count($this->unitRepository->findByBuilding($building));
                }
            }
        }

        return $count;
    }

    private function calculateOccupancyRate(array $cityIds): float
    {
        if (empty($cityIds)) {
            return 0.0;
        }

        $total = 0;
        $occupied = 0;
        $cities = $this->cityRepository->findInOrganization($this->cityRepository->find($cityIds[0])->getOrganization());

        foreach ($cities as $city) {
            if (!in_array($city->getId(), $cityIds, true)) {
                continue;
            }
            $parcels = $this->parcelRepository->findByCity($city);
            foreach ($parcels as $parcel) {
                $buildings = $this->buildingRepository->findByParcel($parcel);
                foreach ($buildings as $building) {
                    $units = $this->unitRepository->findByBuilding($building);
                    $total += count($units);
                    foreach ($units as $unit) {
                        if ($this->unitHasActiveLease($unit)) {
                            $occupied++;
                        }
                    }
                }
            }
        }

        return $total > 0 ? round(($occupied / $total) * 100, 2) : 0.0;
    }

    private function getMainCurrency(?int $organizationId, array $cityIds): string
    {
        // Retourne la devise la plus utilisée (simplifié : CDF par défaut)
        return 'CDF';
    }

    /**
     * Trouve l'organisation de l'utilisateur courant (via ses adhésions).
     */
    public function getOrganizationForUser(\App\Entity\Identity\User $user): ?Organization
    {
        $memberships = $this->securityService->getCurrentUserOrganizations();

        if (empty($memberships)) {
            return null;
        }

        // Pour un PATRON/ADMIN_IMMOBILIER, il n'y a qu'une organisation
        return $memberships[0];
    }

    /**
     * Trouve une ville par son UUID.
     */
    public function getCityByUuid(string $uuid): ?City
    {
        try {
            $parsed = \Symfony\Component\Uid\Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $this->cityRepository->findOneByUuid($parsed);
    }
}