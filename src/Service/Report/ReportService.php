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
use App\Dto\Response\Report\SuperAdminDashboardResponse;
use App\Dto\Response\Report\WorkerActivityItem;
use App\Dto\Response\Report\OrganizationSummaryItem;
use App\Dto\Response\Report\KpiMetric;
use App\Dto\Response\Report\RevenuePoint;
use App\Dto\Response\Report\ActivityEntry;
use App\Dto\Response\Report\SystemHealthMetric;
use App\Dto\Response\Report\CityShare;
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
use App\Enum\Currency;
use App\Repository\Financial\ExchangeRateRepository;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Identity\UserRepository;
use App\Repository\Property\BuildingRepository;
use App\Repository\Property\CityRepository;
use App\Repository\Property\ParcelRepository;
use App\Repository\Property\UnitRepository;
use App\Repository\Rental\LeaseRepository;
use App\Repository\Rental\PaymentRepository;
use App\Repository\Rental\RentRepository;
use App\Repository\System\AuditLogRepository;
use App\Repository\Rental\TenantRepository;
use App\Repository\Staff\WorkerAssignmentRepository;
use App\Repository\Staff\WorkerRepository;
use App\Security\SecurityServiceInterface;
use App\Service\System\DateTimeService;

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
        private AuditLogRepository $auditLogRepository,
        private SecurityServiceInterface $securityService,
        private DateTimeService $dateTime,
        private ExchangeRateRepository $exchangeRateRepository,
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

        $periodFrom = $filter->periodFrom ?? $this->dateTime->startOfCurrentYear();
        $periodTo = $filter->periodTo ?? $this->dateTime->endOfCurrentYear();

        $response = new PatronReportResponse(
            organizationUuid: $organization->getUuid()->toRfc4122(),
            organizationName: $organization->getName(),
            periodCovered: $this->dateTime->format($periodFrom, 'Y-m-d') . ' to ' . $this->dateTime->format($periodTo, 'Y-m-d'),
            generatedAt: $this->dateTime->now(),
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

        $periodFrom = $filter->periodFrom ?? $this->dateTime->startOfCurrentYear();
        $periodTo = $filter->periodTo ?? $this->dateTime->endOfCurrentYear();

        $response = new AdminImmobilierReportResponse(
            organizationUuid: $organization->getUuid()->toRfc4122(),
            organizationName: $organization->getName(),
            periodCovered: $this->dateTime->format($periodFrom, 'Y-m-d') . ' to ' . $this->dateTime->format($periodTo, 'Y-m-d'),
            generatedAt: $this->dateTime->now(),
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
        $periodFrom = $filter->periodFrom ?? $this->dateTime->startOfCurrentYear();
        $periodTo = $filter->periodTo ?? $this->dateTime->endOfCurrentYear();

        $response = new AdminVilleReportResponse(
            cityUuid: $city->getUuid()->toRfc4122(),
            cityName: $city->getName(),
            periodCovered: $this->dateTime->format($periodFrom, 'Y-m-d') . ' to ' . $this->dateTime->format($periodTo, 'Y-m-d'),
            generatedAt: $this->dateTime->now(),
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
        $periodFrom = $filter->periodFrom ?? $this->dateTime->startOfCurrentYear();
        $periodTo = $filter->periodTo ?? $this->dateTime->endOfCurrentYear();

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
            periodCovered: $this->dateTime->format($periodFrom, 'Y-m-d') . ' to ' . $this->dateTime->format($periodTo, 'Y-m-d'),
            organizations: $orgSummaries,
            totalOrganizations: count($organizations),
            activeOrganizations: count(array_filter($organizations, fn($o) => $o->getStatus()->value === 'ACTIVE')),
            totalUsers: $totalUsers,
            generatedAt: $this->dateTime->now(),
);
    }

    /**
     * Tableau de bord temps réel pour SUPER_ADMIN.
     */
    public function generateSuperAdminDashboard(ReportFilterDto $filter): SuperAdminDashboardResponse
    {
        $periodFrom = $filter->periodFrom ?? $this->dateTime->startOfCurrentYear();
        $periodTo = $filter->periodTo ?? $this->dateTime->endOfCurrentYear();

        $organizations = $this->organizationRepository->findAllActive();
        $orgSummaries = [];
        $totalRevenue = '0.00';
        $totalExpense = '0.00';
        $totalArrears = '0.00';

        foreach ($organizations as $org) {
            $cities = $this->cityRepository->findActiveByOrganization($org);
            $cityIds = array_map(fn(City $c) => $c->getId(), $cities);

            $orgRevenue = $this->sumRevenues($org->getId(), $cityIds, $periodFrom, $periodTo);
            $orgExpense = $this->sumExpenses($org->getId(), $cityIds, $periodFrom, $periodTo);
            $orgArrears = $this->sumArrears($org->getId(), $cityIds, $periodTo);

            $totalRevenue = bcadd($totalRevenue, $orgRevenue, 2);
            $totalExpense = bcadd($totalExpense, $orgExpense, 2);
            $totalArrears = bcadd($totalArrears, $orgArrears, 2);

            $orgSummaries[] = new OrganizationSummaryItem(
                uuid: $org->getUuid()->toRfc4122(),
                name: $org->getName(),
                code: $org->getCode(),
                status: $org->getStatus()->value,
                cityCount: count($cities),
                unitCount: $this->countUnitsByCityIds($cityIds),
                occupancyRate: $this->calculateOccupancyRate($cityIds),
                revenues: $orgRevenue,
                expenses: $orgExpense,
                arrears: $orgArrears,
            );
        }

        // KPIs
        $totalOrgs = count($organizations);
        $activeOrgs = count(array_filter($organizations, fn($o) => $o->getStatus()->value === 'ACTIVE'));
        $totalUsers = count($this->userRepository->findPaginatedAll(1, 10000)['items'] ?? []);

        $orgsDelta = $totalOrgs > 0 ? 5.0 : 0; // placeholder
        $usersDelta = $totalUsers > 0 ? 3.0 : 0;
        $revenueDelta = bccomp($totalRevenue, '0', 2) > 0 ? 8.0 : 0;
        $churnRate = $totalOrgs > 0 ? round((($totalOrgs - $activeOrgs) / $totalOrgs) * 100, 1) : 0;

        $kpis = [
            new KpiMetric(
                id: 'orgs',
                label: 'Organisations actives',
                value: (string) $activeOrgs,
                delta: $orgsDelta,
                trend: 'up',
                positive: true,
                helper: "{$activeOrgs} / {$totalOrgs} totales",
                tone: 'primary',
                icon: 'building',
            ),
            new KpiMetric(
                id: 'users',
                label: 'Utilisateurs',
                value: number_format($totalUsers, 0, ',', ' '),
                delta: $usersDelta,
                trend: 'up',
                positive: true,
                helper: 'Plateforme entière',
                tone: 'info',
                icon: 'users',
            ),
            new KpiMetric(
                id: 'mrr',
                label: 'Revenu mensuel (MRR)',
                value: $totalRevenue . ' $',
                delta: $revenueDelta,
                trend: 'up',
                positive: true,
                helper: 'Période en cours',
                tone: 'success',
                icon: 'revenue',
            ),
            new KpiMetric(
                id: 'churn',
                label: 'Taux d\'inactivité',
                value: $churnRate . ' %',
                delta: -0.5,
                trend: 'down',
                positive: true,
                helper: 'Objectif < 5 %',
                tone: 'warning',
                icon: 'pulse',
            ),
        ];

        // Revenue series (12 mois)
        $revenueSeries = [];
        $current = $this->dateTime->startOfCurrentYear();
        $end = $this->dateTime->endOfCurrentYear();
        for ($i = 0; $i < 12; $i++) {
            $monthStart = $current->modify('first day of +' . $i . ' month');
            $monthEnd = (clone $monthStart)->modify('last day of this month 23:59:59');
            if ($monthStart > $end) break;

            $monthRevenue = '0.00';
            foreach ($organizations as $org) {
                $cities = $this->cityRepository->findActiveByOrganization($org);
                $cityIds = array_map(fn(City $c) => $c->getId(), $cities);
                $monthRevenue = bcadd($monthRevenue, $this->sumRevenues($org->getId(), $cityIds, $monthStart, $monthEnd), 2);
            }

            $revenueSeries[] = new RevenuePoint(
                label: $this->dateTime->format($monthStart, 'M'),
                revenue: (float) $monthRevenue,
                subscriptions: $activeOrgs, // approx
            );
        }

        // Recent organizations (5 dernières créées)
        $recentOrgs = array_slice($orgSummaries, 0, 5);

        // Activity feed : événements métier significatifs pour SUPER_ADMIN
        // On filtre les actions d'audit pertinentes (pas les simples LOGIN/LOGOUT)
        $auditLogs = $this->auditLogRepository->findByFilter(
            null,
            null, // action = null -> on filtre côté PHP pour plus de contrôle
            null,
            null,
            null,
            1,
            50, // on récupère plus large pour filtrer
            null
        );

        $importantActions = [
            'CREATE_ORGANIZATION' => ['label' => 'Nouvelle organisation', 'kind' => 'create'],
            'SUSPEND_ORGANIZATION' => ['label' => 'Organisation suspendue', 'kind' => 'alert'],
            'ACTIVATE_ORGANIZATION' => ['label' => 'Organisation réactivée', 'kind' => 'create'],
            'CREATE_ADMIN' => ['label' => 'Administrateur créé', 'kind' => 'create'],
            'SUSPEND_USER' => ['label' => 'Utilisateur suspendu', 'kind' => 'alert'],
            'CREATE_LEASE' => ['label' => 'Bail créé', 'kind' => 'create'],
            'TERMINATE_LEASE' => ['label' => 'Bail terminé', 'kind' => 'delete'],
            'CANCEL_LEASE' => ['label' => 'Bail annulé', 'kind' => 'delete'],
            'CREATE_PAYMENT' => ['label' => 'Paiement reçu', 'kind' => 'create'],
            'CANCEL_PAYMENT' => ['label' => 'Paiement annulé', 'kind' => 'delete'],
            'CREATE_EXPENSE' => ['label' => 'Dépense enregistrée', 'kind' => 'create'],
            'CREATE_WORKER' => ['label' => 'Personnel ajouté', 'kind' => 'create'],
            'UPDATE_RENT' => ['label' => 'Échéance mise à jour', 'kind' => 'update'],
        ];

        $activity = [];
        foreach ($auditLogs['items'] as $log) {
            $action = $log->getAction();
            if (!isset($importantActions[$action])) {
                continue; // ignore LOGIN, LOGOUT, LOGIN_FAILED, etc.
            }

            $meta = $importantActions[$action];
            $actor = $log->getUser()?->getFullName() ?? 'Système';
            $target = $this->formatAuditTarget($log);
            $newValues = $log->getNewValues();
            $detail = $newValues && is_array($newValues) ? $this->extractDetail($newValues) : '';

            $activity[] = new ActivityEntry(
                id: (string) $log->getUuid(),
                actor: $actor,
                action: $meta['label'],
                target: $target . ($detail ? ' — ' . $detail : ''),
                timestamp: $this->formatRelativeTime($log->getCreatedAt()),
                kind: $meta['kind'],
            );

            if (count($activity) >= 10) {
                break; // limite à 10 entrées
            }
        }

        // Si pas d'activité significative, message par défaut
        if (empty($activity)) {
            $activity[] = new ActivityEntry(
                id: 'none',
                actor: '—',
                action: 'Aucun événement métier récent',
                target: '—',
                timestamp: '—',
                kind: 'update',
            );
        }

        // Health metrics (statiques - en production viendraient d'un système de monitoring)
        $health = [
            new SystemHealthMetric(id: 'cpu', label: 'CPU', value: 38, unit: '%', status: 'healthy'),
            new SystemHealthMetric(id: 'latency', label: 'Latence p95', value: 172, unit: 'ms', status: 'healthy'),
            new SystemHealthMetric(id: 'storage', label: 'Stockage', value: 71, unit: '%', status: 'warning'),
            new SystemHealthMetric(id: 'errors', label: 'Erreurs 5xx', value: 0.4, unit: '%', status: 'healthy'),
        ];

        // Top cities across all organizations
        $cityCounts = [];
        foreach ($organizations as $org) {
            $cities = $this->cityRepository->findActiveByOrganization($org);
            foreach ($cities as $city) {
                $parcels = $this->parcelRepository->findByCity($city);
                $count = 0;
                foreach ($parcels as $parcel) {
                    $buildings = $this->buildingRepository->findByParcel($parcel);
                    foreach ($buildings as $building) {
                        $count += count($this->unitRepository->findByBuilding($building));
                    }
                }
                $cityCounts[$city->getName()] = ($cityCounts[$city->getName()] ?? 0) + $count;
            }
        }
        arsort($cityCounts);
        $totalUnits = array_sum($cityCounts);
        $topCities = [];
        foreach (array_slice($cityCounts, 0, 5, true) as $name => $count) {
            $topCities[] = new CityShare(
                name: $name,
                count: $count,
                share: $totalUnits > 0 ? round($count / $totalUnits, 2) : 0,
            );
        }

        return new SuperAdminDashboardResponse(
            periodCovered: $this->dateTime->format($periodFrom, 'Y-m-d') . ' to ' . $this->dateTime->format($periodTo, 'Y-m-d'),
            generatedAt: $this->dateTime->now(),
            kpis: $kpis,
            revenueSeries: $revenueSeries,
            recentOrganizations: $recentOrgs,
            activity: $activity,
            health: $health,
            topCities: $topCities,
            totalOrganizations: $totalOrgs,
            activeOrganizations: $activeOrgs,
            totalUsers: $totalUsers,
        );
    }

    /**
     * Formate un DateTimeImmutable en temps relatif (ex: "il y a 4 min").
     */
    private function formatRelativeTime(\DateTimeImmutable $date): string
    {
        $now = $this->dateTime->now();
        $diff = $now->diff($date);

        if ($diff->y > 0) return "il y a {$diff->y} an" . ($diff->y > 1 ? 's' : '');
        if ($diff->m > 0) return "il y a {$diff->m} mois";
        if ($diff->d > 0) return "il y a {$diff->d} jour" . ($diff->d > 1 ? 's' : '');
        if ($diff->h > 0) return "il y a {$diff->h} h";
        if ($diff->i > 0) return "il y a {$diff->i} min";
        return 'à l\'instant';
    }

    /**
     * Mappe une action d'audit vers un type d'activité.
     */
    private function mapActionToKind(string $action): string
    {
        $prefix = strtolower(explode('_', $action)[0]);
        return match ($prefix) {
            'create' => 'create',
            'update' => 'update',
            'delete' => 'delete',
            'suspend' => 'alert',
            'activate' => 'create',
            'login' => 'login',
            'logout' => 'login',
            default => 'update',
        };
    }

    /**
     * Formate la cible d'un log d'audit pour l'affichage.
     */
    private function formatAuditTarget(\App\Entity\System\AuditLog $log): string
    {
        $entityType = $log->getEntityType();
        $entityId = $log->getEntityId();

        $shortType = match (true) {
            str_contains($entityType, 'Organization') && !str_contains($entityType, 'User') => 'Organisation',
            str_contains($entityType, 'User') && !str_contains($entityType, 'City') => 'Utilisateur',
            str_contains($entityType, 'Lease') => 'Bail',
            str_contains($entityType, 'Payment') => 'Paiement',
            str_contains($entityType, 'Expense') => 'Dépense',
            str_contains($entityType, 'Worker') && !str_contains($entityType, 'Assignment') => 'Personnel',
            str_contains($entityType, 'WorkerAssignment') => 'Affectation',
            str_contains($entityType, 'City') => 'Ville',
            str_contains($entityType, 'Parcel') => 'Parcelle',
            str_contains($entityType, 'Building') => 'Bâtiment',
            str_contains($entityType, 'Unit') && !str_contains($entityType, 'Photo') => 'Unité',
            str_contains($entityType, 'UnitPhoto') => 'Photo unité',
            str_contains($entityType, 'Tenant') => 'Locataire',
            str_contains($entityType, 'Rent') => 'Échéance',
            str_contains($entityType, 'ExchangeRate') => 'Taux de change',
            default => (string) $entityType,
        };

        $newValues = $log->getNewValues();
        if ($newValues && is_array($newValues)) {
            $name = $newValues['name'] ?? $newValues['fullName'] ?? $newValues['companyName'] ?? $newValues['reference'] ?? null;
            if ($name) {
                return $shortType . ' « ' . $name . ' »';
            }
        }

        return $shortType . ($entityId ? ' #' . $entityId : '');
    }

    /**
     * Extrait un détail lisible depuis newValues.
     */
    private function extractDetail(array $values): string
    {
        $details = [];

        if (isset($values['status'])) {
            $details[] = 'statut : ' . $values['status'];
        }
        if (isset($values['role'])) {
            $details[] = 'rôle : ' . $values['role'];
        }
        if (isset($values['monthlyRent'])) {
            $details[] = 'loyer : ' . $values['monthlyRent'];
        }
        if (isset($values['amount'])) {
            $details[] = 'montant : ' . $values['amount'];
        }
        if (isset($values['reason'])) {
            $details[] = 'motif : ' . $values['reason'];
        }

        return implode(', ', $details);
    }

    // ==================== MÉTHODES PRIVÉES D'AGRÉGATION ====================

    /** @return list<FinancialSummaryItem> */
    private function buildFinancialSummary(
        ?int $organizationId,
        array $cityIds,
        \DateTimeImmutable $periodFrom,
        \DateTimeImmutable $periodTo
    ): array {
        // Récupérer les dépenses groupées par période et devise
        $expenseResults = $this->expenseRepository->getFinancialSummary(
            $organizationId ? [$organizationId] : null,
            $cityIds ?: null,
            $periodFrom,
            $periodTo
        );

        // Récupérer les revenus (paiements) groupés par période et devise
        $revenueResults = $this->paymentRepository->getFinancialSummary(
            $organizationId ? [$organizationId] : null,
            $cityIds ?: null,
            $periodFrom,
            $periodTo
        );

        // Récupérer les loyers attendus (somme des montants des échéances)
        // pour la période, groupés par période et devise
        $expectedResults = $this->rentRepository->getExpectedRentsSummary(
            $organizationId ? [$organizationId] : null,
            $cityIds ?: null,
            $periodFrom,
            $periodTo
        );

        // Agréger par clé "période|devise" pour ne pas mélanger les devises
        $byPeriodCurrency = [];

        foreach ($expenseResults as $row) {
            $currencyKey = $row['currency'] instanceof \BackedEnum ? $row['currency']->value : (string) $row['currency'];
            $key = $row['period'] . '|' . $currencyKey;
            if (!isset($byPeriodCurrency[$key])) {
                $byPeriodCurrency[$key] = [
                    'period' => $row['period'],
                    'currency' => $currencyKey,
                    'revenues' => '0.00',
                    'expenses' => '0.00',
                    'expected' => '0.00',
                ];
            }
            $byPeriodCurrency[$key]['expenses'] = bcadd($byPeriodCurrency[$key]['expenses'], $row['total'], 2);
        }

        foreach ($revenueResults as $row) {
            $currencyKey = $row['currency'] instanceof \BackedEnum ? $row['currency']->value : (string) $row['currency'];
            $key = $row['period'] . '|' . $currencyKey;
            if (!isset($byPeriodCurrency[$key])) {
                $byPeriodCurrency[$key] = [
                    'period' => $row['period'],
                    'currency' => $currencyKey,
                    'revenues' => '0.00',
                    'expenses' => '0.00',
                    'expected' => '0.00',
                ];
            }
            $byPeriodCurrency[$key]['revenues'] = bcadd($byPeriodCurrency[$key]['revenues'], $row['total'], 2);
        }

        foreach ($expectedResults as $row) {
            $currencyKey = $row['currency'] instanceof \BackedEnum ? $row['currency']->value : (string) $row['currency'];
            $key = $row['period'] . '|' . $currencyKey;
            if (!isset($byPeriodCurrency[$key])) {
                $byPeriodCurrency[$key] = [
                    'period' => $row['period'],
                    'currency' => $currencyKey,
                    'revenues' => '0.00',
                    'expenses' => '0.00',
                    'expected' => '0.00',
                ];
            }
            $byPeriodCurrency[$key]['expected'] = bcadd($byPeriodCurrency[$key]['expected'], $row['total'], 2);
        }

        $items = [];
        foreach ($byPeriodCurrency as $data) {
            $net = bcsub($data['revenues'], $data['expenses'], 2);
            $items[] = new FinancialSummaryItem(
                period: $data['period'],
                revenues: $data['revenues'],
                expenses: $data['expenses'],
                expected: $data['expected'],
                netResult: $net,
                currency: \App\Enum\Currency::from($data['currency']),
            );
        }

        // Trier par période
        usort($items, fn($a, $b) => strcmp($a->period, $b->period));

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
        // Générer l'évolution de l'occupation mois par mois
        $items = [];
        $current = $from->modify('first day of this month');
        $endMonth = $to->modify('first day of this month');

        while ($current <= $endMonth) {
            $totalUnits = 0;
            $occupiedUnits = 0;

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
                        $totalUnits += count($units);

                        foreach ($units as $unit) {
                            // Un bail est actif pour ce mois si sa période le couvre
                            $activeLease = $this->leaseRepository->findActiveLeaseForUnitAtDate($unit, $current);
                            if ($activeLease !== null) {
                                $occupiedUnits++;
                            }
                        }
                    }
                }
            }

            $items[] = new OccupancyItem(
                level: 'month',
                levelUuid: $this->dateTime->format($current, 'Y-m'),
                label: $this->dateTime->format($current, 'F Y'),
                totalUnits: $totalUnits,
                occupiedUnits: $occupiedUnits,
                availableUnits: $totalUnits - $occupiedUnits,
                occupancyRate: $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100, 2) : 0.0,
            );

            $current = $current->modify('+1 month');
        }

        return $items;
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
            // `e.category` est mappée sur l'enum `ExpenseCategory` : Doctrine
            // la rend comme telle, alors que le DTO attend une chaîne (il
            // porte aussi la catégorie littérale 'ALL' pour les agrégats
            // qui ne sont pas une catégorie). On normalise ici plutôt que
            // de changer le type du DTO et de casser 'ALL'.
            $category = $row['category'] instanceof \BackedEnum
                ? (string) $row['category']->value
                : (string) $row['category'];

            $items[] = new ExpenseSummaryItem(
                category: $category,
                level: 'organization',
                levelLabel: 'Organisation',
                count: 0, // Would need separate count query
                totalAmount: $row['total'],
                currency: $row['currency'],
            );
        }

        return $items;
    }

    /**
     * Dépenses agrégées par ville, strictement bornées à `$cityIds`.
     *
     * `$cityIds` n'est jamais `null` : une liste vide renvoie un rapport
     * vide. La fixer obligatoire dans `sumByCity()` empêche le cas
     * historique où l'appelant passait `null` et obtenait la somme des
     * dépenses de toutes les organisations, accompagnées des noms de leurs
     * villes.
     *
     * @param list<int> $cityIds
     *
     * @return list<ExpenseSummaryItem>
     */
    private function buildExpensesByCity(array $cityIds, \DateTimeImmutable $periodFrom, \DateTimeImmutable $periodTo): array
    {
        $results = $this->expenseRepository->sumByCity($cityIds, $periodFrom, $periodTo);

        $items = [];
        foreach ($results as $row) {
            $items[] = new ExpenseSummaryItem(
                category: 'ALL',
                level: 'city',
                levelUuid: (string) $row['cityUuid'],
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

    /**
     * Convertit un montant d'une devise vers la devise de référence de l'organisation.
     * Utilise le taux de change historique applicable à la date de l'opération.
     * Si pas de taux trouvé, retourne le montant original (fallback).
     */
    private function convertToReferenceCurrency(string $amount, Currency $fromCurrency, Currency $toCurrency, \DateTimeImmutable $date): string
    {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        $rate = $this->exchangeRateRepository->findRateForDate($fromCurrency, $toCurrency, $date);
        if ($rate === null) {
            // Fallback : essayer le taux inverse
            $inverseRate = $this->exchangeRateRepository->findInverseRateForDate($fromCurrency, $toCurrency, $date);
            if ($inverseRate !== null) {
                return bcdiv($amount, $inverseRate->getRate(), 2);
            }
            // Pas de taux disponible : retourner le montant original (ne pas casser le rapport)
            return $amount;
        }

        return $rate->convertBaseToQuote($amount);
    }

    /**
     * Détermine la devise de référence pour une organisation.
     * Utilise getMainCurrency comme fallback.
     */
    private function getReferenceCurrency(?int $organizationId, array $cityIds): Currency
    {
        $currencyCode = $this->getMainCurrency($organizationId, $cityIds);
        return \App\Enum\Currency::from($currencyCode);
    }

    private function sumRevenues(?int $organizationId, array $cityIds, \DateTimeImmutable $from, \DateTimeImmutable $to): string
    {
        $results = $this->paymentRepository->getFinancialSummary(
            $organizationId ? [$organizationId] : null,
            $cityIds ?: null,
            $from,
            $to
        );

        $referenceCurrency = $this->getReferenceCurrency($organizationId, $cityIds);
        $total = '0.00';
        foreach ($results as $row) {
            $currency = $row['currency'] instanceof \BackedEnum ? $row['currency']->value : (string) $row['currency'];
            $converted = $this->convertToReferenceCurrency($row['total'], \App\Enum\Currency::from($currency), $referenceCurrency, $from);
            $total = bcadd($total, $converted, 2);
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

        $referenceCurrency = $this->getReferenceCurrency($organizationId, $cityIds);
        $total = '0.00';
        foreach ($results as $row) {
            $currency = $row['currency'] instanceof \BackedEnum ? $row['currency']->value : (string) $row['currency'];
            $converted = $this->convertToReferenceCurrency($row['total'], \App\Enum\Currency::from($currency), $referenceCurrency, $from);
            $total = bcadd($total, $converted, 2);
        }
        return $total;
    }

    private function sumArrears(?int $organizationId, array $cityIds, \DateTimeImmutable $asOfDate): string
    {
        $leases = $this->leaseRepository->findActiveByOrganizationsAndCities(
            $organizationId ? [$organizationId] : null,
            $cityIds ?: null
        );

        $referenceCurrency = $this->getReferenceCurrency($organizationId, $cityIds);
        $total = '0.00';
        foreach ($leases as $lease) {
            $rents = $this->rentRepository->findByLease($lease);
            foreach ($rents as $rent) {
                if ($rent->getStatus() === RentStatus::OVERDUE || $rent->getStatus() === RentStatus::PARTIALLY_PAID) {
                    $due = $rent->getAmount();
                    $paid = $this->paymentRepository->sumAmountByRent($rent);
                    $arrears = bcsub($due, $paid, 2);
                    $converted = $this->convertToReferenceCurrency($arrears, $rent->getCurrency(), $referenceCurrency, $asOfDate);
                    $total = bcadd($total, $converted, 2);
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
        // Compter les devises utilisées dans les paiements et dépenses du périmètre
        $currencyCounts = [];

        $payments = $this->paymentRepository->getFinancialSummary(
            $organizationId ? [$organizationId] : null,
            $cityIds ?: null,
            $this->dateTime->startOfCurrentYear(),
            $this->dateTime->endOfCurrentYear()
        );
        foreach ($payments as $row) {
            $currencyKey = $row['currency'] instanceof \BackedEnum ? $row['currency']->value : (string) $row['currency'];
            $currencyCounts[$currencyKey] = ($currencyCounts[$currencyKey] ?? 0) + 1;
        }

        $expenses = $this->expenseRepository->getFinancialSummary(
            $organizationId ? [$organizationId] : null,
            $cityIds ?: null,
            $this->dateTime->startOfCurrentYear(),
            $this->dateTime->endOfCurrentYear()
        );
        foreach ($expenses as $row) {
            $currencyKey = $row['currency'] instanceof \BackedEnum ? $row['currency']->value : (string) $row['currency'];
            $currencyCounts[$currencyKey] = ($currencyCounts[$currencyKey] ?? 0) + 1;
        }

        if (empty($currencyCounts)) {
            return \App\Enum\Currency::CDF->value; // fallback
        }

        arsort($currencyCounts);
        return array_key_first($currencyCounts);
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