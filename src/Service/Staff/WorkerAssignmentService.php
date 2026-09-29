<?php

declare(strict_types=1);

namespace App\Service\Staff;

use App\Dto\Feedback;
use App\Dto\Request\Staff\WorkerAssignmentRequest;
use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Entity\Property\Building;
use App\Entity\Property\City;
use App\Entity\Property\Parcel;
use App\Entity\Property\Unit;
use App\Entity\Staff\Worker;
use App\Entity\Staff\WorkerAssignment;
use App\Enum\WorkerRole;
use App\Mapper\Staff\WorkerAssignmentMapper;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Property\BuildingRepository;
use App\Repository\Property\CityRepository;
use App\Repository\Property\ParcelRepository;
use App\Repository\Property\UnitRepository;
use App\Repository\Staff\WorkerAssignmentRepository;
use App\Repository\Staff\WorkerRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\System\AuditLogService;
use App\Service\System\DateTimeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * WorkerAssignmentService
 *
 * Package : Staff Management
 *
 * Règles métier :
 * - Une affectation porte EXACTEMENT UNE cible parmi parcel, building, unit.
 * - La ville est obligatoire ; elle déduit l'Organization.
 * - La cible doit appartenir à la ville et à l'Organization déduite.
 * - Le worker doit appartenir à la même Organization.
 * - Pas de suppression : l'entité n'est pas supprimable (traçabilité).
 *   Fin d'affectation par mise à jour de endDate.
 * - ADMIN_VILLE limité à ses villes (couvert par checkCityAccess sur l'affectation).
 */
final readonly class WorkerAssignmentService
{
    public function __construct(
        private WorkerAssignmentRepository $assignmentRepository,
        private WorkerRepository $workerRepository,
        private CityRepository $cityRepository,
        private ParcelRepository $parcelRepository,
        private BuildingRepository $buildingRepository,
        private UnitRepository $unitRepository,
        private WorkerAssignmentMapper $assignmentMapper,
        private SecurityServiceInterface $securityService,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private AuditLogService $auditLogService,
        private DateTimeService $dateTime,
    ) {
    }

    public function createAssignment(WorkerAssignmentRequest $request, User $currentUser): Feedback
    {
        $feedback = new Feedback();

        $violations = $this->validator->validate($request, null, ['create']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setFlushDescriptionWithError('Les données de l\'affectation sont invalides.')
                ->autoInitFlush();
        }

        // 1) Résoudre la ville (obligatoire) et déduire l'Organization
        $city = $this->resolveCity($request->cityUuid, SecurityAction::CREATE_WORKER_ASSIGNMENT, $feedback);
        if ($city === null) {
            return $feedback->autoInitFlush();
        }
        $organization = $city->getOrganization();

        // 2) Vérifier l'accès à l'Organization
        $this->securityService->checkOrganizationAccess($organization, SecurityAction::CREATE_WORKER_ASSIGNMENT);

        // 3) Résoudre le worker (obligatoire)
        $worker = $this->resolveWorker($request->workerUuid, $organization, $feedback);
        if ($worker === null) {
            return $feedback->autoInitFlush();
        }

        // 4) Résoudre la cible unique (obligatoire à la création)
        $target = $this->resolveUniqueTarget(
            $request->parcelUuid,
            $request->buildingUuid,
            $request->unitUuid,
            $city,
            $organization,
            $feedback,
            true // cible obligatoire à la création
        );
        if ($target === null) {
            return $feedback->autoInitFlush();
        }

        // 5) Vérifier chevauchement : un worker ne peut pas avoir deux affectations
        // actives sur la même période dans la même ville
        if (!$this->checkNoOverlap($worker, $city, $request->startDate, $request->endDate, null, $feedback)) {
            return $feedback->autoInitFlush();
        }

        // 6) Créer l'affectation
        $assignment = new WorkerAssignment();
        $assignment->setWorker($worker);
        $assignment->setCity($city);
        $this->assignmentMapper->copyToEntity($request, $assignment);

        // Positionner la cible
        if ($target instanceof Parcel) {
            $assignment->setParcel($target);
        } elseif ($target instanceof Building) {
            $assignment->setBuilding($target);
        } elseif ($target instanceof Unit) {
            $assignment->setUnit($target);
        }

        $this->entityManager->persist($assignment);
        $this->entityManager->flush();

        // Log d'audit : création de l'affectation
        $this->auditLogService->log(
            action: 'CREATE_WORKER_ASSIGNMENT',
            entityType: WorkerAssignment::class,
            entityId: $assignment->getId(),
            organization: $organization,
            user: $currentUser,
            oldValues: null,
            newValues: [
                'workerUuid' => $worker->getUuid()->toRfc4122(),
                'cityUuid' => $city->getUuid()->toRfc4122(),
                'role' => $assignment->getRole()->value,
                'monthlySalary' => $assignment->getMonthlySalary(),
                'currency' => $assignment->getCurrency()->value,
                'startDate' => $this->dateTime->format($assignment->getStartDate(), 'Y-m-d'),
            ],
        );

        return $feedback
            ->setData($this->assignmentMapper->toResponse($assignment))
            ->setFlushDescription('L\'affectation a été créée avec succès.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    public function updateAssignment(string $uuid, WorkerAssignmentRequest $request, User $currentUser): Feedback
    {
        $feedback = new Feedback();

        $violations = $this->validator->validate($request, null, ['update']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setFlushDescriptionWithError('Les données de mise à jour sont invalides.')
                ->autoInitFlush();
        }

        $assignment = $this->findAssignment($uuid, $feedback);
        if ($assignment === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkWorkerAssignmentAccess($assignment, SecurityAction::UPDATE_WORKER_ASSIGNMENT);

        // Si cityUuid est fourni et différent, revalider tout le périmètre
        if ($request->cityUuid !== null) {
            $newCity = $this->resolveCity($request->cityUuid, SecurityAction::UPDATE_WORKER_ASSIGNMENT, $feedback);
            if ($newCity === null) {
                return $feedback->autoInitFlush();
            }
            if ($newCity !== $assignment->getCity()) {
                $assignment->setCity($newCity);
                $this->securityService->checkOrganizationAccess($newCity->getOrganization(), SecurityAction::UPDATE_WORKER_ASSIGNMENT);
            }
        }

        // Résoudre la cible si l'une des UUIDs est fournie
        $hasTargetChange = $request->parcelUuid !== null || $request->buildingUuid !== null || $request->unitUuid !== null;
        if ($hasTargetChange) {
            $target = $this->resolveUniqueTarget(
                $request->parcelUuid,
                $request->buildingUuid,
                $request->unitUuid,
                $assignment->getCity(),
                $assignment->getWorker()->getOrganization(),
                $feedback
            );
            if ($target === null) {
                return $feedback->autoInitFlush();
            }
            // Nettoyer les anciennes cibles
            $assignment->setParcel(null);
            $assignment->setBuilding(null);
            $assignment->setUnit(null);
            if ($target instanceof Parcel) {
                $assignment->setParcel($target);
            } elseif ($target instanceof Building) {
                $assignment->setBuilding($target);
            } elseif ($target instanceof Unit) {
                $assignment->setUnit($target);
            }
        }

        // Vérifier chevauchement si dates changent
        if ($request->startDate !== null || $request->endDate !== null) {
            $startDate = $request->startDate ?? $assignment->getStartDate();
            $endDate = $request->endDate ?? $assignment->getEndDate();
            if (!$this->checkNoOverlap($assignment->getWorker(), $assignment->getCity(), $startDate, $endDate, $assignment, $feedback)) {
                return $feedback->autoInitFlush();
            }
        }

        // Appliquer les changements scalaires
        $this->assignmentMapper->copyToEntity($request, $assignment);

        $this->entityManager->flush();

        return $feedback
            ->setData($this->assignmentMapper->toResponse($assignment))
            ->setFlushDescription('L\'affectation a été mise à jour avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function getAssignmentByUuid(string $uuid): Feedback
    {
        $feedback = new Feedback();

        $assignment = $this->findAssignment($uuid, $feedback);
        if ($assignment === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkWorkerAssignmentAccess($assignment, SecurityAction::VIEW_WORKER_ASSIGNMENT);

        return $feedback
            ->setData($this->assignmentMapper->toResponse($assignment))
            ->setFlushDescription('Affectation trouvée.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function listAssignments(
        ?array $cityIds = null,
        ?int $page = 1,
        ?int $limit = 20,
        ?string $sortBy = 'startDate',
        ?string $sortOrder = 'DESC'
    ): Feedback {
        $feedback = new Feedback();

        // Vérifier l'accès aux villes demandées
        if ($cityIds !== null && !empty($cityIds)) {
            foreach ($cityIds as $cityId) {
                try {
                    $city = $this->cityRepository->findOneById($cityId);
                    if ($city !== null) {
                        $this->securityService->checkCityAccess($city, SecurityAction::VIEW_WORKER_ASSIGNMENT);
                    }
                } catch (\Throwable) {
                    // Ville introuvable : ignorer
                }
            }
        }

        $result = $this->assignmentRepository->findByFilters(
            cityIds: $cityIds,
            page: $page,
            limit: $limit,
            sortBy: $sortBy,
            sortOrder: $sortOrder
        );

        $items = array_map(
            fn (WorkerAssignment $a) => $this->assignmentMapper->toResponse($a),
            $result['items']
        );

        return $feedback
            ->setData(['items' => $items, 'total' => $result['total']])
            ->setFlushDescription('Liste des affectations.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Termine une affectation en posant endDate (pas de suppression).
     */
    public function endAssignment(string $uuid, \DateTimeImmutable $endDate, User $currentUser): Feedback
    {
        $feedback = new Feedback();

        $assignment = $this->findAssignment($uuid, $feedback);
        if ($assignment === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkWorkerAssignmentAccess($assignment, SecurityAction::DELETE_WORKER_ASSIGNMENT);

        if ($endDate < $assignment->getStartDate()) {
            return $feedback
                ->addError('endDate', 'La date de fin doit être postérieure à la date de début.')
                ->setFlushDescriptionWithError('Date de fin invalide.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $assignment->setEndDate($endDate);
        $this->entityManager->flush();

        return $feedback
            ->setData($this->assignmentMapper->toResponse($assignment))
            ->setFlushDescription('L\'affectation a été terminée.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    private function resolveCity(?string $cityUuid, SecurityAction $action, Feedback $feedback): ?City
    {
        if ($cityUuid === null || $cityUuid === '') {
            $feedback
                ->addError('cityUuid', 'La ville d\'exercice est obligatoire.')
                ->setFlushDescriptionWithError('Le paramètre "cityUuid" est obligatoire.')
                ->setStatus(400);

            return null;
        }

        try {
            $parsed = Uuid::fromString($cityUuid);
        } catch (\InvalidArgumentException) {
            $feedback
                ->addError('cityUuid', 'UUID de ville invalide.')
                ->setFlushDescriptionWithError('Le paramètre "cityUuid" n\'est pas un UUID valide.')
                ->setStatus(400);

            return null;
        }

        $city = $this->cityRepository->findOneByUuid($parsed);
        if ($city === null) {
            $feedback
                ->setErrorFlushDescription('Ville introuvable.')
                ->setStatus(404);

            return null;
        }

        try {
            $this->securityService->checkCityAccess($city, $action);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            $feedback
                ->setErrorFlushDescription($e->getMessage())
                ->setStatus(403);

            return null;
        }

        return $city;
    }

    private function resolveWorker(string $workerUuid, Organization $organization, Feedback $feedback): ?Worker
    {
        try {
            $parsed = Uuid::fromString($workerUuid);
        } catch (\InvalidArgumentException) {
            $feedback
                ->addError('workerUuid', 'UUID de travailleur invalide.')
                ->setFlushDescriptionWithError('Le paramètre "workerUuid" n\'est pas un UUID valide.')
                ->setStatus(400);

            return null;
        }

        $worker = $this->workerRepository->findOneByUuid($parsed);
        if ($worker === null || $worker->getOrganization() !== $organization) {
            $feedback
                ->addError('workerUuid', 'Travailleur introuvable ou n\'appartient pas à l\'organisation.')
                ->setFlushDescriptionWithError('Le travailleur doit appartenir à la même organisation que l\'affectation.')
                ->setStatus(422);

            return null;
        }

        return $worker;
    }

    private function resolveUniqueTarget(
        ?string $parcelUuid,
        ?string $buildingUuid,
        ?string $unitUuid,
        City $city,
        Organization $organization,
        Feedback $feedback,
        bool $required = false
    ): Parcel|Building|Unit|null {
        $targetsProvided = array_filter([
            'parcel' => $parcelUuid,
            'building' => $buildingUuid,
            'unit' => $unitUuid,
        ], fn ($v) => $v !== null && $v !== '');

        if (count($targetsProvided) > 1) {
            $feedback
                ->addError('target', 'Une seule cible parmi parcel/building/unit est autorisée.')
                ->setFlushDescriptionWithError('Au plus une cible parmi parcel, building, unit peut être fournie.')
                ->setStatus(422);

            return null;
        }

        if ($targetsProvided === []) {
            if ($required) {
                $feedback
                    ->addError('target', 'Une cible (parcel, building ou unit) est obligatoire.')
                    ->setFlushDescriptionWithError('Une cible parmi parcel, building, unit doit être fournie.')
                    ->setStatus(422);
            }
            return null;
        }

        foreach ($targetsProvided as $type => $uuid) {
            try {
                $parsed = Uuid::fromString($uuid);
            } catch (\InvalidArgumentException) {
                $feedback
                    ->addError($type . 'Uuid', "UUID de {$type} invalide.")
                    ->setFlushDescriptionWithError("Le paramètre \"{$type}Uuid\" n'est pas un UUID valide.")
                    ->setStatus(400);

                return null;
            }

            switch ($type) {
                case 'parcel':
                    $target = $this->parcelRepository->findOneByUuid($parsed);
                    if ($target === null || $target->getCity() !== $city) {
                        $feedback
                            ->addError('parcelUuid', 'Parcelle introuvable ou n\'appartient pas à la ville.')
                            ->setFlushDescriptionWithError('La parcelle doit appartenir à la ville fournie.')
                            ->setStatus(422);
                        return null;
                    }
                    return $target;

                case 'building':
                    $target = $this->buildingRepository->findOneByUuid($parsed);
                    if ($target === null || $target->getParcel()->getCity() !== $city) {
                        $feedback
                            ->addError('buildingUuid', 'Immeuble introuvable ou n\'appartient pas à la ville.')
                            ->setFlushDescriptionWithError('L\'immeuble doit appartenir à la ville fournie.')
                            ->setStatus(422);
                        return null;
                    }
                    return $target;

                case 'unit':
                    $target = $this->unitRepository->findOneByUuid($parsed);
                    if ($target === null || $target->getBuilding()->getParcel()->getCity() !== $city) {
                        $feedback
                            ->addError('unitUuid', 'Unité introuvable ou n\'appartient pas à la ville.')
                            ->setFlushDescriptionWithError('L\'unité doit appartenir à la ville fournie.')
                            ->setStatus(422);
                        return null;
                    }
                    return $target;
            }
        }

        return null;
    }

    private function checkNoOverlap(
        Worker $worker,
        City $city,
        \DateTimeImmutable $startDate,
        ?\DateTimeImmutable $endDate,
        ?WorkerAssignment $exclude,
        Feedback $feedback
    ): bool {
        $overlaps = $this->assignmentRepository->findOverlapping($worker, $city, $startDate, $endDate, $exclude);

        if (!empty($overlaps)) {
            $feedback
                ->addError('dates', 'Le travailleur a déjà une affectation active sur cette période dans cette ville.')
                ->setFlushDescriptionWithError('Chevauchement d\'affectation détecté.')
                ->setStatus(409);

            return false;
        }

        return true;
    }

    private function findAssignment(string $uuid, Feedback $feedback): ?WorkerAssignment
    {
        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            $feedback
                ->addError('uuid', 'Identifiant d\'affectation invalide.')
                ->setFlushDescriptionWithError('L\'identifiant de l\'affectation n\'est pas un UUID valide.')
                ->setStatus(400);

            return null;
        }

        $assignment = $this->assignmentRepository->findOneByUuid($parsed);
        if ($assignment === null) {
            $feedback
                ->setErrorFlushDescription('Affectation introuvable.')
                ->setStatus(404);
        }

        return $assignment;
    }
}