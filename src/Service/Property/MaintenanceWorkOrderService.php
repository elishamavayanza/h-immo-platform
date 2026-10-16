<?php

declare(strict_types=1);

namespace App\Service\Property;

use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Entity\Property\Building;
use App\Entity\Property\City;
use App\Entity\Property\MaintenanceWorkOrder;
use App\Entity\Property\Parcel;
use App\Entity\Property\Unit;
use App\Entity\Staff\WorkerAssignment;
use App\Enum\MaintenancePriority;
use App\Enum\MaintenanceStatus;
use App\Exception\AccessDeniedException;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Property\BuildingRepository;
use App\Repository\Property\CityRepository;
use App\Repository\Property\MaintenanceWorkOrderRepository;
use App\Repository\Property\ParcelRepository;
use App\Repository\Property\UnitRepository;
use App\Repository\Staff\WorkerAssignmentRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\System\AuditLogService;
use App\Service\System\DateTimeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class MaintenanceWorkOrderService
{
    public function __construct(
        private MaintenanceWorkOrderRepository $workOrders,
        private OrganizationRepository $organizations,
        private CityRepository $cities,
        private ParcelRepository $parcels,
        private BuildingRepository $buildings,
        private UnitRepository $units,
        private WorkerAssignmentRepository $assignments,
        private SecurityServiceInterface $security,
        private EntityManagerInterface $entityManager,
        private DateTimeService $dateTime,
        private AuditLogService $auditLog,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(User $user, string $organizationUuid, string $cityUuid, ?string $status): array
    {
        [$organization, $city] = $this->scope($user, $organizationUuid, $cityUuid, SecurityAction::VIEW_MAINTENANCE);
        if ($status !== null && MaintenanceStatus::tryFrom($status) === null) {
            throw new \InvalidArgumentException('État de maintenance invalide.');
        }

        return array_map($this->present(...), $this->workOrders->findForCity($organization, $city, $status === null ? null : MaintenanceStatus::from($status)));
    }

    /** @return array<string, mixed> */
    public function create(User $user, \App\Dto\Request\Property\MaintenanceWorkOrderRequest $request): array
    {
        [$organization, $city] = $this->scope($user, $request->organizationUuid, $request->cityUuid, SecurityAction::CREATE_MAINTENANCE);
        [$parcel, $building, $unit] = $this->resolveAsset($request, $city);
        $assignment = $this->resolveAssignment($request->workerAssignmentUuid, $city);
        $reportedAt = $request->reportedAt === null ? $this->dateTime->today() : $this->dateTime->parseDate($request->reportedAt);
        if ($reportedAt === null || $reportedAt > $this->dateTime->today()) {
            throw new \InvalidArgumentException('La date du signalement doit être une date valide, aujourd’hui ou antérieure.');
        }
        $dueDate = $request->dueDate === null ? null : $this->dateTime->parseDate($request->dueDate);
        if ($request->dueDate !== null && ($dueDate === null || $dueDate < $this->dateTime->today())) {
            throw new \InvalidArgumentException('La date prévue doit être valide et ne peut pas être passée.');
        }
        $priority = MaintenancePriority::tryFrom($request->priority);
        if ($priority === null) {
            throw new \InvalidArgumentException('Priorité de maintenance invalide.');
        }
        if (trim($request->title) === '' || trim($request->description) === '') {
            throw new \InvalidArgumentException('Le titre et la description sont obligatoires.');
        }

        $workOrder = (new MaintenanceWorkOrder())
            ->setOrganization($organization)->setCity($city)->setParcel($parcel)->setBuilding($building)->setUnit($unit)
            ->setWorkerAssignment($assignment)->setCreatedBy($user)->setTitle(trim($request->title))
            ->setDescription(trim($request->description))->setPriority($priority)->setReportedAt($reportedAt)->setDueDate($dueDate);
        $this->entityManager->persist($workOrder);
        $this->entityManager->flush();
        $id = $workOrder->getId() ?? throw new \LogicException('L’intervention de maintenance n’a pas reçu son identifiant.');
        $this->auditLog->log('CREATE_MAINTENANCE_WORK_ORDER', MaintenanceWorkOrder::class, $id, $organization, $user, null, [
            'cityUuid' => $city->getUuid()->toRfc4122(), 'priority' => $priority->value, 'reportedAt' => $reportedAt->format('Y-m-d'), 'target' => $this->targetType($parcel, $building, $unit),
        ]);

        return $this->present($workOrder);
    }

    /** @return array<string, mixed> */
    public function update(User $user, string $uuid, \App\Dto\Request\Property\MaintenanceWorkOrderRequest $request): array
    {
        $workOrder = $this->find($uuid);
        [$organization, $city] = $this->scope($user, $request->organizationUuid, $request->cityUuid, SecurityAction::UPDATE_MAINTENANCE);
        $this->assertWorkOrderScope($workOrder, $organization, $city);
        if (in_array($workOrder->getStatus(), [MaintenanceStatus::COMPLETED, MaintenanceStatus::CANCELLED], true)) {
            throw new \InvalidArgumentException('Une intervention terminée ou annulée ne peut plus être modifiée.');
        }
        $assignment = $this->resolveAssignment($request->workerAssignmentUuid, $city);
        $reportedAt = $request->reportedAt === null ? $workOrder->getReportedAt() : $this->dateTime->parseDate($request->reportedAt);
        if ($reportedAt === null || $reportedAt > $this->dateTime->today()) {
            throw new \InvalidArgumentException('La date du signalement doit être une date valide, aujourd’hui ou antérieure.');
        }
        $dueDate = $request->dueDate === null ? null : $this->dateTime->parseDate($request->dueDate);
        if ($request->dueDate !== null && ($dueDate === null || $dueDate < $this->dateTime->today())) {
            throw new \InvalidArgumentException('La date prévue doit être valide et ne peut pas être passée.');
        }
        $priority = MaintenancePriority::tryFrom($request->priority);
        if ($priority === null) {
            throw new \InvalidArgumentException('Priorité de maintenance invalide.');
        }
        if (trim($request->title) === '' || trim($request->description) === '') {
            throw new \InvalidArgumentException('Le titre et la description sont obligatoires.');
        }
        $old = ['title' => $workOrder->getTitle(), 'priority' => $workOrder->getPriority()->value, 'reportedAt' => $workOrder->getReportedAt()->format('Y-m-d'), 'dueDate' => $workOrder->getDueDate()?->format('Y-m-d')];
        $workOrder->setTitle(trim($request->title))->setDescription(trim($request->description))->setPriority($priority)
            ->setWorkerAssignment($assignment)->setReportedAt($reportedAt)->setDueDate($dueDate)->setUpdatedAt($this->dateTime->now());
        $this->entityManager->flush();
        $this->auditLog->log('UPDATE_MAINTENANCE_WORK_ORDER', MaintenanceWorkOrder::class, $workOrder->getId() ?? 0, $organization, $user, $old, [
            'title' => $workOrder->getTitle(), 'priority' => $priority->value, 'reportedAt' => $reportedAt->format('Y-m-d'), 'dueDate' => $dueDate?->format('Y-m-d'),
        ]);

        return $this->present($workOrder);
    }

    /** @return array<string, mixed> */
    public function transition(User $user, string $uuid, string $organizationUuid, string $cityUuid, string $status, ?string $completedAt = null): array
    {
        $workOrder = $this->find($uuid);
        [$organization, $city] = $this->scope($user, $organizationUuid, $cityUuid, SecurityAction::UPDATE_MAINTENANCE);
        $this->assertWorkOrderScope($workOrder, $organization, $city);
        $next = MaintenanceStatus::tryFrom($status);
        if ($next === null || !$this->canTransition($workOrder->getStatus(), $next)) {
            throw new \InvalidArgumentException('Transition d’état non autorisée.');
        }
        $previous = $workOrder->getStatus();
        $completionDate = null;
        if ($next === MaintenanceStatus::COMPLETED) {
            $completionDate = $completedAt === null ? $this->dateTime->today() : $this->dateTime->parseDate($completedAt);
            if ($completionDate === null || $completionDate > $this->dateTime->today() || $completionDate < $workOrder->getReportedAt()) {
                throw new \InvalidArgumentException('La date d’achèvement doit être valide, comprise entre le signalement et aujourd’hui.');
            }
        }
        $previousCompletionDate = $workOrder->getCompletedAt()?->format('Y-m-d');
        $workOrder->setStatus($next)->setCompletedAt($completionDate)->setUpdatedAt($this->dateTime->now());
        $this->entityManager->flush();
        $this->auditLog->log(
            'TRANSITION_MAINTENANCE_WORK_ORDER',
            MaintenanceWorkOrder::class,
            $workOrder->getId() ?? 0,
            $organization,
            $user,
            ['status' => $previous->value, 'completedAt' => $previousCompletionDate],
            ['status' => $next->value, 'completedAt' => $completionDate?->format('Y-m-d')],
        );

        return $this->present($workOrder);
    }

    /** @return array{Organization, City} */
    private function scope(User $user, string $organizationUuid, string $cityUuid, SecurityAction $action): array
    {
        try {
            $organization = $this->organizations->findOneByUuid(Uuid::fromString($organizationUuid));
            $city = $this->cities->findOneByUuid(Uuid::fromString($cityUuid));
        } catch (\InvalidArgumentException) {
            $organization = null; $city = null;
        }
        if ($organization === null || $city === null || $city->getOrganization()->getId() !== $organization->getId()) {
            throw new \InvalidArgumentException('Organisation ou ville introuvable.');
        }
        if ($this->security->getOrganizationRole($user, $organization) === null) {
            throw new AccessDeniedException('Cet espace métier ne vous est pas attribué.');
        }
        $this->security->checkOrganizationAccess($organization, $action);
        $this->security->checkCityAccess($city, $action);

        return [$organization, $city];
    }

    /** @return array{?Parcel, ?Building, ?Unit} */
    private function resolveAsset(\App\Dto\Request\Property\MaintenanceWorkOrderRequest $request, City $city): array
    {
        $provided = array_filter([$request->parcelUuid, $request->buildingUuid, $request->unitUuid], static fn (?string $v): bool => $v !== null && $v !== '');
        if (count($provided) !== 1) {
            throw new \InvalidArgumentException('Choisissez une seule cible : parcelle, bâtiment ou unité.');
        }
        try {
            $parcel = $request->parcelUuid ? $this->parcels->findOneByUuid(Uuid::fromString($request->parcelUuid)) : null;
            $building = $request->buildingUuid ? $this->buildings->findOneByUuid(Uuid::fromString($request->buildingUuid)) : null;
            $unit = $request->unitUuid ? $this->units->findOneByUuid(Uuid::fromString($request->unitUuid)) : null;
        } catch (\InvalidArgumentException) {
            throw new \InvalidArgumentException('Cible de maintenance invalide.');
        }
        $targetCity = $parcel?->getCity() ?? $building?->getParcel()->getCity() ?? $unit?->getBuilding()->getParcel()->getCity();
        if ($targetCity === null || $targetCity->getId() !== $city->getId()) {
            throw new AccessDeniedException('La cible ne fait pas partie de la ville autorisée.');
        }
        if ($parcel !== null) $this->security->checkParcelAccess($parcel, SecurityAction::VIEW_PARCEL);
        if ($building !== null) $this->security->checkBuildingAccess($building, SecurityAction::VIEW_BUILDING);
        if ($unit !== null) $this->security->checkUnitAccess($unit, SecurityAction::VIEW_UNIT);

        return [$parcel, $building, $unit];
    }

    private function resolveAssignment(?string $uuid, City $city): ?WorkerAssignment
    {
        if ($uuid === null || $uuid === '') return null;
        try { $assignment = $this->assignments->findOneByUuid(Uuid::fromString($uuid)); } catch (\InvalidArgumentException) { $assignment = null; }
        if ($assignment === null || $assignment->getCity()->getId() !== $city->getId()) {
            throw new AccessDeniedException('L’affectation de personnel ne relève pas de cette ville.');
        }
        $this->security->checkWorkerAssignmentAccess($assignment, SecurityAction::VIEW_WORKER_ASSIGNMENT);

        return $assignment;
    }

    private function find(string $uuid): MaintenanceWorkOrder
    {
        try { $workOrder = $this->workOrders->findOneByUuid(Uuid::fromString($uuid)); } catch (\InvalidArgumentException) { $workOrder = null; }
        return $workOrder ?? throw new \InvalidArgumentException('Intervention introuvable.');
    }

    private function assertWorkOrderScope(MaintenanceWorkOrder $workOrder, Organization $organization, City $city): void
    {
        if ($workOrder->getOrganization()->getId() !== $organization->getId() || $workOrder->getCity()->getId() !== $city->getId()) {
            throw new AccessDeniedException('Intervention hors du périmètre autorisé.');
        }
    }

    private function canTransition(MaintenanceStatus $from, MaintenanceStatus $to): bool
    {
        return match ($from) {
            MaintenanceStatus::OPEN => in_array($to, [MaintenanceStatus::IN_PROGRESS, MaintenanceStatus::CANCELLED], true),
            MaintenanceStatus::IN_PROGRESS => in_array($to, [MaintenanceStatus::OPEN, MaintenanceStatus::COMPLETED, MaintenanceStatus::CANCELLED], true),
            MaintenanceStatus::COMPLETED, MaintenanceStatus::CANCELLED => false,
        };
    }

    /** @return array<string, mixed> */
    private function present(MaintenanceWorkOrder $workOrder): array
    {
        $target = $workOrder->getUnit()?->getReference() ?? $workOrder->getBuilding()?->getName() ?? $workOrder->getParcel()?->getName() ?? '—';
        return [
            'uuid' => $workOrder->getUuid()->toRfc4122(), 'organizationUuid' => $workOrder->getOrganization()->getUuid()->toRfc4122(),
            'cityUuid' => $workOrder->getCity()->getUuid()->toRfc4122(), 'parcelUuid' => $workOrder->getParcel()?->getUuid()->toRfc4122(),
            'buildingUuid' => $workOrder->getBuilding()?->getUuid()->toRfc4122(), 'unitUuid' => $workOrder->getUnit()?->getUuid()->toRfc4122(),
            'targetLabel' => $target, 'title' => $workOrder->getTitle(), 'description' => $workOrder->getDescription(),
            'priority' => $workOrder->getPriority()->value, 'status' => $workOrder->getStatus()->value,
            'workerAssignmentUuid' => $workOrder->getWorkerAssignment()?->getUuid()->toRfc4122(),
            'workerName' => $workOrder->getWorkerAssignment()?->getWorker()->getFullName(),
            'reportedAt' => $workOrder->getReportedAt()->format('Y-m-d'), 'dueDate' => $workOrder->getDueDate()?->format('Y-m-d'),
            'completedAt' => $workOrder->getCompletedAt()?->format('Y-m-d'), 'createdAt' => $workOrder->getCreatedAt()->format(DATE_ATOM),
            'createdBy' => $workOrder->getCreatedBy()->getFullName(),
        ];
    }

    private function targetType(?Parcel $parcel, ?Building $building, ?Unit $unit): string
    {
        return $unit !== null ? 'unit' : ($building !== null ? 'building' : 'parcel');
    }
}
