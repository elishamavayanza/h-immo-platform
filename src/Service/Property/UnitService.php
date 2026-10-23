<?php

declare(strict_types=1);

namespace App\Service\Property;

use App\Dto\Feedback;
use App\Dto\Request\Property\UnitFilterDto;
use App\Dto\Request\Property\UnitRequest;
use App\Entity\Property\Building;
use App\Entity\Property\BuildingLevel;
use App\Entity\Property\City;
use App\Entity\Property\Unit;
use App\Enum\UnitOccupancyStatus;
use App\Mapper\Property\UnitMapper;
use App\Repository\Property\BuildingRepository;
use App\Repository\Property\BuildingLevelRepository;
use App\Repository\Property\UnitRepository;
use App\Repository\Rental\LeaseRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\System\AuditLogService;
use App\Service\Identity\OrganizationCapacityService;
use App\Service\System\DateTimeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * UnitService
 *
 * Package : Property Management — Service Métier
 *
 * Gère les unités locatives (Unit) d'un bâtiment et assure l'unicité de
 * leur référence au sein de ce bâtiment.
 *
 * Portée : `unit -> building -> parcel -> city -> organization`; la liste est
 * bornée aux villes réellement accessibles à l'appelant.
 */
final readonly class UnitService
{
    public function __construct(
        private UnitRepository $unitRepository,
        private LeaseRepository $leaseRepository,
        private BuildingRepository $buildingRepository,
        private BuildingLevelRepository $buildingLevelRepository,
        private UnitMapper $unitMapper,
        private SecurityServiceInterface $securityService,
        private AuditLogService $auditLogService,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private OrganizationCapacityService $capacityService,
        private DateTimeService $dateTime,
    ) {
    }

    public function list(?UnitFilterDto $filter = null): Feedback
    {
        $feedback = new Feedback();
        $filter ??= new UnitFilterDto();

        $cities = $this->securityService->getScopedCities();

        if ($filter->organizationId !== null && $filter->organizationId !== '') {
            $cities = $this->citiesOfOrganization($cities, $filter->organizationId);
        }

        if ($cities === []) {
            return $this->emptyListResponse($feedback, $filter->page, $filter->limit);
        }

        $building = null;
        if ($filter->buildingUuid !== null && $filter->buildingUuid !== '') {
            $building = $this->resolveScopedBuilding($filter->buildingUuid);
            if ($building === null) {
                return $this->emptyListResponse($feedback, $filter->page, $filter->limit);
            }
        }

        $result = $this->unitRepository->findPaginatedAccessible(
            $cities,
            $filter->page,
            $filter->limit,
            $filter->search,
            $building
        );

        return $feedback
            ->setData([
                'items' => array_map(fn (Unit $unit) => $this->toResponse($unit), $result['items']),
                'total' => $result['total'],
                'page' => $filter->page,
                'limit' => $filter->limit,
            ])
            ->setFlushDescription('Liste des unités récupérée avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Filtre une liste de villes sur celles d'une organisation donnée.
     *
     * @param list<City> $cities
     *
     * @return list<City>
     */
    private function citiesOfOrganization(array $cities, string $uuid): array
    {
        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            return [];
        }

        $target = (string) $parsed;

        return array_values(array_filter(
            $cities,
            fn (City $city): bool => (string) $city->getOrganization()->getUuid() === $target
        ));
    }

    /**
     * Résout un bâtiment parent de filtre, sous contrôle d'accès.
     *
     * Retourne `null` (liste vide) pour un bâtiment inexistant ou hors
     * périmètre : distinguer les deux révélerait son existence.
     */
    private function resolveScopedBuilding(string $uuid): ?Building
    {
        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $building = $this->buildingRepository->findOneByUuid($parsed);

        if ($building === null
            || !$this->securityService->canAccessBuilding($building, SecurityAction::VIEW_UNIT)
        ) {
            return null;
        }

        return $building;
    }

    private function emptyListResponse(Feedback $feedback, int $page, int $limit): Feedback
    {
        return $feedback
            ->setData([
                'items' => [],
                'total' => 0,
                'page' => max(1, $page),
                'limit' => $limit,
            ])
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function getByUuid(string $uuid): Feedback
    {
        $feedback = new Feedback();

        $unitRepository = $this->findUnit($uuid, $feedback);
        if ($unitRepository === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkUnitAccess($unitRepository, SecurityAction::VIEW_UNIT);

        return $feedback
            ->setData($this->toResponse($unitRepository))
            ->setFlushDescription('Détails du unité récupérés.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function create(UnitRequest $request): Feedback
    {
        $feedback = new Feedback();

        $violations = $this->validator->validate($request, groups: ['create']);
        if (count($violations) > 0) {
            return $feedback->bind($violations)
                ->setErrorFlushDescription('Les données soumises sont invalides.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $parent = $this->resolveParent($request->buildingUuid, $feedback);
        if ($parent === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkBuildingAccess($parent, SecurityAction::CREATE_UNIT);

        $organization = $parent->getParcel()->getCity()->getOrganization();
        if ($request->reference !== null
            && $this->unitRepository->findOneByBuildingAndReference($parent, $request->reference) !== null
        ) {
            return $feedback->addError('reference', 'Cette référence existe déjà pour ce bâtiment.')
                ->setErrorFlushDescription('Conflit d\'unicité détecté.')
                ->setStatus(409)
                ->autoInitFlush();
        }

        $unitRepository = new Unit();
        $unitRepository->setBuilding($parent);
        $parentSpace = $this->resolveParentSpace($request->parentUnitUuid, $parent, $feedback);
        if ($request->parentUnitUuid !== null && $request->parentUnitUuid !== '' && $parentSpace === null) {
            return $feedback->autoInitFlush();
        }
        if ($parentSpace !== null && $this->leaseRepository->findActiveLeaseForUnit($parentSpace) !== null) {
            return $feedback->addError('parentUnitUuid', 'Un espace déjà loué ne peut pas être restructuré.')
                ->setErrorFlushDescription('Cette subdivision entrerait en conflit avec un bail actif.')
                ->setStatus(409)
                ->autoInitFlush();
        }
        $unitRepository->setParentUnit($parentSpace);
        $this->unitMapper->copyToEntity($request, $unitRepository);
        $level = $this->resolveBuildingLevel($request->levelUuid, $parent, $feedback);
        if ($request->levelUuid !== null && $request->levelUuid !== '' && $level === null) return $feedback->autoInitFlush();
        if (!$this->isFloorAllowed($parent, $level?->getDisplayOrder() ?? $request->floor, $feedback)) {
            return $feedback->autoInitFlush();
        }
        $unitRepository->setLevel($level);
        if ($level !== null) $unitRepository->setFloor($level->getDisplayOrder())->setFloorLabel($level->getLabel());

        $attempt = $this->capacityService->executeWithinLimit($organization, 'units', function () use ($unitRepository): void {
            $this->entityManager->persist($unitRepository);
            $this->entityManager->flush();
        });
        if ($attempt['message'] !== null) {
            return $feedback->addError('capacity', $attempt['message'])->setErrorFlushDescription($attempt['message'])->setStatus($attempt['status'])->autoInitFlush();
        }

        return $feedback
            ->setData($this->toResponse($unitRepository))
            ->setFlushDescription('Le unité a été créé avec succès.')
            ->autoInitFlush()
            ->setStatus(201);
    }

    public function update(string $uuid, UnitRequest $request): Feedback
    {
        $feedback = new Feedback();

        $unitRepository = $this->findUnit($uuid, $feedback);
        if ($unitRepository === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkUnitAccess($unitRepository, SecurityAction::UPDATE_UNIT);
        $previousParentUuid = $unitRepository->getParentUnit() !== null
            ? (string) $unitRepository->getParentUnit()->getUuid()
            : null;

        $violations = $this->validator->validate($request, groups: ['update']);
        if (count($violations) > 0) {
            return $feedback->bind($violations)
                ->setErrorFlushDescription('Les données de mise à jour sont invalides.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        // Le rattachement au parent (ville, parcelle, bâtiment) n'est pas
        // modifiable : le déplacer reviendrait à faire basculer la ressource
        // dans un autre périmètre d'organization sans contrôle.
        if ($request->buildingUuid !== null
            && $request->buildingUuid !== (string) $unitRepository->getBuilding()->getUuid()
        ) {
            $feedback->addError(
                'buildingUuid',
                'Le rattachement à un autre bâtiment n\'est pas autorisé.'
            );

            return $feedback
                ->setErrorFlushDescription('Changement de bâtiment refusé.')
                ->autoInitFlush();
        }

        $level = $unitRepository->getLevel();
        if ($request->levelUuid !== null) {
            $level = $this->resolveBuildingLevel($request->levelUuid, $unitRepository->getBuilding(), $feedback);
            if ($request->levelUuid !== '' && $level === null) return $feedback->autoInitFlush();
            if ($level?->getId() !== $unitRepository->getLevel()?->getId()) {
                if ($this->leaseRepository->findActiveLeaseForRelatedSpaces($this->unitRepository->findRelatedSpaces($unitRepository)) !== null) {
                    return $feedback->addError('levelUuid', 'Le niveau d’un espace loué ne peut pas être modifié pendant un bail actif.')
                        ->setFlushDescriptionWithError('Terminez ou transférez les contrats concernés avant ce changement de structure.')
                        ->autoInitFlush()->setStatus(409);
                }
            }
        }

        $effectiveFloor = $level?->getDisplayOrder() ?? $request->floor ?? $unitRepository->getFloor();
        if (!$this->isFloorAllowed($unitRepository->getBuilding(), $effectiveFloor, $feedback)) {
            return $feedback->autoInitFlush();
        }

        if ($request->parentUnitUuid !== null) {
            $parentSpace = $this->resolveParentSpace($request->parentUnitUuid, $unitRepository->getBuilding(), $feedback);
            if ($request->parentUnitUuid !== '' && $parentSpace === null) {
                return $feedback->autoInitFlush();
            }
            if ($parentSpace !== null && ($parentSpace->getId() === $unitRepository->getId() || $this->unitRepository->isDescendantOf($parentSpace, $unitRepository))) {
                return $feedback->addError('parentUnitUuid', 'Un espace ne peut pas devenir son propre parent ou celui de son ancêtre.')
                    ->setErrorFlushDescription('La hiérarchie des espaces ne peut pas contenir de cycle.')
                    ->setStatus(422)
                    ->autoInitFlush();
            }
            if ($parentSpace?->getId() !== $unitRepository->getParentUnit()?->getId()) {
                $affected = array_merge(
                    $this->unitRepository->findRelatedSpaces($unitRepository),
                    $parentSpace !== null ? $this->unitRepository->findRelatedSpaces($parentSpace) : [],
                );
                if ($this->leaseRepository->findActiveLeaseForRelatedSpaces($affected) !== null) {
                    return $feedback->addError('parentUnitUuid', 'La hiérarchie ne peut pas être modifiée pendant un bail actif.')
                        ->setErrorFlushDescription('Clôturez ou transférez les baux concernés avant de restructurer ces espaces.')
                        ->setStatus(409)
                        ->autoInitFlush();
                }
            }
            $unitRepository->setParentUnit($parentSpace);
        }

        if ($request->reference !== null && $request->reference !== $unitRepository->getReference()) {
            $existing = $this->unitRepository->findOneByBuildingAndReference(
                $unitRepository->getBuilding(),
                $request->reference
            );

            if ($existing !== null) {
                return $feedback->addError('reference', 'Cette référence existe déjà pour ce bâtiment.')
                    ->setErrorFlushDescription('Conflit d\'unicité détecté.')
                    ->setStatus(409)
                    ->autoInitFlush();
            }
        }

        $oldValues = ['reference' => $unitRepository->getReference(), 'type' => $unitRepository->getType()->value, 'operationalStatus' => $unitRepository->getOperationalStatus()->value, 'commercialActivity' => $unitRepository->getCommercialActivity(), 'floor' => $unitRepository->getFloor(), 'floorLabel' => $unitRepository->getFloorLabel(), 'parentUnitUuid' => $previousParentUuid, 'surface' => $unitRepository->getSurface(), 'bedrooms' => $unitRepository->getBedrooms(), 'rooms' => $unitRepository->getRooms(), 'bathrooms' => $unitRepository->getBathrooms(), 'monthlyRent' => $unitRepository->getMonthlyRent(), 'currency' => $unitRepository->getCurrency()->value, 'description' => $unitRepository->getDescription()];
        $this->unitMapper->copyToEntity($request, $unitRepository);
        $unitRepository->setLevel($level);
        if ($level !== null) $unitRepository->setFloor($level->getDisplayOrder())->setFloorLabel($level->getLabel());
        $this->auditLogService->log(
            action: 'UPDATE_UNIT', entityType: Unit::class, entityId: (int) $unitRepository->getId(),
            organization: $unitRepository->getBuilding()->getParcel()->getCity()->getOrganization(), user: $this->securityService->getCurrentUser(),
            oldValues: $oldValues,
            newValues: ['reference' => $unitRepository->getReference(), 'type' => $unitRepository->getType()->value, 'operationalStatus' => $unitRepository->getOperationalStatus()->value, 'commercialActivity' => $unitRepository->getCommercialActivity(), 'floor' => $unitRepository->getFloor(), 'floorLabel' => $unitRepository->getFloorLabel(), 'parentUnitUuid' => $unitRepository->getParentUnit() !== null ? (string) $unitRepository->getParentUnit()->getUuid() : null, 'surface' => $unitRepository->getSurface(), 'bedrooms' => $unitRepository->getBedrooms(), 'rooms' => $unitRepository->getRooms(), 'bathrooms' => $unitRepository->getBathrooms(), 'monthlyRent' => $unitRepository->getMonthlyRent(), 'currency' => $unitRepository->getCurrency()->value, 'description' => $unitRepository->getDescription()],
            flush: false,
        );
        $this->entityManager->flush();

        return $feedback
            ->setData($this->toResponse($unitRepository))
            ->setFlushDescription('Le unité a été mis à jour avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    private function resolveParentSpace(?string $uuid, Building $building, Feedback $feedback): ?Unit
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            $feedback->addError('parentUnitUuid', 'Identifiant d’espace parent invalide.')
                ->setStatus(422);
            return null;
        }

        $parent = $this->unitRepository->findOneByUuid($parsed);
        if ($parent === null
            || (string) $parent->getBuilding()->getUuid() !== (string) $building->getUuid()
            || !$this->securityService->canAccessBuilding($parent->getBuilding(), SecurityAction::VIEW_UNIT)
        ) {
            $feedback->addError('parentUnitUuid', 'L’espace parent est introuvable dans ce bâtiment.')
                ->setStatus(404);
            return null;
        }

        return $parent;
    }

    private function resolveBuildingLevel(?string $uuid, Building $building, Feedback $feedback): ?BuildingLevel
    {
        if ($uuid === null || $uuid === '') return null;
        try { $level = $this->buildingLevelRepository->findOneByUuid(Uuid::fromString($uuid)); }
        catch (\InvalidArgumentException) { $level = null; }
        if ($level === null || $level->getBuilding()->getId() !== $building->getId()) {
            $feedback->addError('levelUuid', 'Le niveau sélectionné doit appartenir au même bâtiment.')->setStatus(422);
            return null;
        }
        $this->securityService->checkBuildingAccess($building, SecurityAction::VIEW_UNIT);
        return $level;
    }

    private function isFloorAllowed(Building $building, ?int $floor, Feedback $feedback): bool
    {
        $maximumFloor = $building->getNumberOfFloors()
            ?? max(0, $this->unitRepository->highestFloor($building), $this->buildingLevelRepository->highestFloor($building));
        if ($floor === null || $floor > $maximumFloor) {
            $feedback->addError('floor', sprintf(
                'Cet étage dépasse la capacité déclarée du bâtiment. Les valeurs autorisées vont du sous-sol (−1, −2…) à %d (0 = rez-de-chaussée).',
                $maximumFloor
            ))
                ->setErrorFlushDescription('Choisissez un étage compris dans les limites du bâtiment.')
                ->setStatus(422);

            return false;
        }

        return true;
    }

    public function delete(string $uuid): Feedback
    {
        $feedback = new Feedback();

        $unitRepository = $this->findUnit($uuid, $feedback);
        if ($unitRepository === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkUnitAccess($unitRepository, SecurityAction::DELETE_UNIT);

        if ($this->leaseRepository->findActiveLeaseForRelatedSpaces(
            $this->unitRepository->findOverlappingSpaces($unitRepository, includeArchivedDescendants: true)
        ) !== null) {
            return $feedback
                ->addError('unitUuid', 'Une unité liée à un bail actif ne peut pas être supprimée.')
                ->setErrorFlushDescription('Terminez ou annulez les baux actifs de cet espace, de son parent ou de ses sous-espaces avant de l’archiver.')
                ->autoInitFlush()
                ->setStatus(409);
        }

        if ($this->unitRepository->hasActiveSubspaces($unitRepository)) {
            return $feedback
                ->addError('unitUuid', 'Détachez ou déplacez d’abord les sous-espaces.')
                ->setErrorFlushDescription('Un espace parent qui contient des subdivisions ne peut pas être supprimé.')
                ->setStatus(409)
                ->autoInitFlush();
        }

        $actor = $this->securityService->getCurrentUser();
        $unitRepository->softDelete();
        $this->auditLogService->log(
            action: 'SOFT_DELETE_UNIT', entityType: Unit::class, entityId: (int) $unitRepository->getId(),
            organization: $unitRepository->getBuilding()->getParcel()->getCity()->getOrganization(), user: $actor,
            oldValues: ['reference' => $unitRepository->getReference(), 'type' => $unitRepository->getType()->value],
            newValues: ['deletedAt' => $unitRepository->getDeletedAt()?->format(DATE_ATOM)],
            flush: false,
        );
        $this->entityManager->flush();

        return $feedback
            ->setFlushDescription('Le unité a été supprimé avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Trouve une unité par son UUID, ou lève NotFoundHttpException.
     *
     * Ne fait PAS de contrôle d'accès : l'appelant (PublicShowcaseManagementService)
     * applique son propre contrôle d'action après la résolution.
     * C'est délibéré pour que l'exception 404 ne distingue pas "unité d'un autre
     * tenant" d'"unité inexistante" — seul un 404 unifié évite l'énumération.
     */
    public function findByUuidOrFail(string $uuid): Unit
    {
        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('Identifiant de unité invalide.');
        }

        $unit = $this->unitRepository->findOneByUuid($parsed);

        if ($unit === null) {
            throw new NotFoundHttpException('Unité introuvable.');
        }

        return $unit;
    }

    /**
     * Résolution du parent (ville, parcelle, bâtiment) par UUID public, avec
     * contrôle d'accès : la ressource ne peut être rattachée qu'à un parent
     * que l'appelant est autorisé à administrer.
     */
    private function resolveParent(?string $uuid, Feedback $feedback): ?Building
    {
        if ($uuid === null || $uuid === '') {
            $feedback->addError('buildingUuid', 'Le bâtiment est obligatoire.');

            return null;
        }

        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            $feedback->addError('buildingUuid', 'Identifiant de bâtiment invalide.');

            return null;
        }

        $parent = $this->buildingRepository->findOneByUuid($parsed);

        if ($parent === null) {
            $feedback
                ->addError('buildingUuid', 'Le bâtiment spécifié n\'existe pas.')
                ->setStatus(404);
        }

        return $parent;
    }

    private function findUnit(string $uuid, Feedback $feedback): ?Unit
    {
        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            $feedback
                ->setErrorFlushDescription('Identifiant de unité invalide.')
                ->setStatus(400);

            return null;
        }

        $unitRepository = $this->unitRepository->findOneByUuid($parsed);

        if ($unitRepository === null) {
            $feedback
                ->setErrorFlushDescription('Le unité demandé n\'existe pas.')
                ->setStatus(404);
        }

        return $unitRepository;
    }

    private function toResponse(Unit $unit): \App\Dto\Response\Property\UnitResponse
    {
        return $this->unitMapper->toResponse($unit, $this->getOccupancyStatus($unit));
    }

    public function getOccupancyStatus(Unit $unit): UnitOccupancyStatus
    {
        $activeLeases = $this->leaseRepository->findActiveLeasesForRelatedSpacesAtDate(
            $this->unitRepository->findOverlappingSpaces($unit),
            $this->dateTime->today(),
        );
        if ($activeLeases === []) return UnitOccupancyStatus::VACANT;

        $hasOccupiedSpace = false;
        foreach ($activeLeases as $lease) {
            foreach ([$lease->getUnit(), ...$lease->getAdditionalUnits()->toArray()] as $leasedSpace) {
                if ($leasedSpace === $unit || $this->unitRepository->isDescendantOf($unit, $leasedSpace)) {
                    $hasOccupiedSpace = true;
                }
            }
        }

        return $hasOccupiedSpace ? UnitOccupancyStatus::OCCUPIED : UnitOccupancyStatus::PARTIALLY_OCCUPIED;
    }
}
