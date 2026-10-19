<?php

declare(strict_types=1);

namespace App\Service\Property;

use App\Dto\Feedback;
use App\Dto\Request\Property\BuildingFilterDto;
use App\Dto\Request\Property\BuildingRequest;
use App\Entity\Property\Building;
use App\Entity\Property\City;
use App\Entity\Property\Parcel;
use App\Mapper\Property\BuildingMapper;
use App\Repository\Property\ParcelRepository;
use App\Repository\Property\BuildingRepository;
use App\Repository\Property\UnitRepository;
use App\Repository\Rental\LeaseRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\System\AuditLogService;
use App\Service\Identity\OrganizationCapacityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * BuildingService
 *
 * Package : Property Management — Service Métier
 *
 * Gère les bâtiments (Building) d'une parcelle et assure l'unicité de leur
 * référence au sein de cette parcelle.
 *
 * Portée : `building -> parcel -> city -> organization`; la liste est bornée
 * aux villes réellement accessibles à l'appelant.
 */
final readonly class BuildingService
{
    public function __construct(
        private BuildingRepository $buildingRepository,
        private ParcelRepository $parcelRepository,
        private UnitRepository $unitRepository,
        private LeaseRepository $leaseRepository,
        private BuildingMapper $buildingMapper,
        private SecurityServiceInterface $securityService,
        private AuditLogService $auditLogService,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private OrganizationCapacityService $capacityService,
    ) {
    }

    public function list(?BuildingFilterDto $filter = null): Feedback
    {
        $feedback = new Feedback();
        $filter ??= new BuildingFilterDto();

        $cities = $this->securityService->getScopedCities();

        if ($filter->organizationId !== null && $filter->organizationId !== '') {
            $cities = $this->citiesOfOrganization($cities, $filter->organizationId);
        }

        if ($cities === []) {
            return $this->emptyListResponse($feedback, $filter->page, $filter->limit);
        }

        $parcel = null;
        if ($filter->parcelUuid !== null && $filter->parcelUuid !== '') {
            $parcel = $this->resolveScopedParcel($filter->parcelUuid);
            if ($parcel === null) {
                return $this->emptyListResponse($feedback, $filter->page, $filter->limit);
            }
        }

        $result = $this->buildingRepository->findPaginatedAccessible(
            $cities,
            $filter->page,
            $filter->limit,
            $filter->search,
            $parcel
        );

        return $feedback
            ->setData([
                'items' => array_map([$this->buildingMapper, 'toResponse'], $result['items']),
                'total' => $result['total'],
                'page' => $filter->page,
                'limit' => $filter->limit,
            ])
            ->setFlushDescription('Liste des bâtiments récupérée avec succès.')
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
     * Résout une parcelle parente de filtre, sous contrôle d'accès.
     *
     * Retourne `null` (liste vide) pour une parcelle inexistante ou hors
     * périmètre : distinguer les deux révélerait son existence.
     */
    private function resolveScopedParcel(string $uuid): ?Parcel
    {
        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $parcel = $this->parcelRepository->findOneByUuid($parsed);

        if ($parcel === null
            || !$this->securityService->canAccessParcel($parcel, SecurityAction::VIEW_BUILDING)
        ) {
            return null;
        }

        return $parcel;
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

        $buildingRepository = $this->findBuilding($uuid, $feedback);
        if ($buildingRepository === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkBuildingAccess($buildingRepository, SecurityAction::VIEW_BUILDING);

        return $feedback
            ->setData($this->buildingMapper->toResponse($buildingRepository))
            ->setFlushDescription('Détails du bâtiment récupérés.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function create(BuildingRequest $request): Feedback
    {
        $feedback = new Feedback();

        $violations = $this->validator->validate($request, groups: ['create']);
        if (count($violations) > 0) {
            return $feedback->bind($violations)
                ->setErrorFlushDescription('Les données soumises sont invalides.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $parent = $this->resolveParent($request->parcelUuid, $feedback);
        if ($parent === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkParcelAccess($parent, SecurityAction::CREATE_BUILDING);

        $organization = $parent->getCity()->getOrganization();
        if ($request->reference !== null
            && $this->buildingRepository->findOneByParcelAndReference($parent, $request->reference) !== null
        ) {
            return $feedback->addError('reference', 'Cette référence existe déjà pour ce parcelle.')
                ->setErrorFlushDescription('Conflit d\'unicité détecté.')
                ->setStatus(409)
                ->autoInitFlush();
        }

        $buildingRepository = new Building();
        $buildingRepository->setParcel($parent);
        $this->buildingMapper->copyToEntity($request, $buildingRepository);

        $attempt = $this->capacityService->executeWithinLimit($organization, 'buildings', function () use ($buildingRepository): void {
            $this->entityManager->persist($buildingRepository);
            $this->entityManager->flush();
        });
        if ($attempt['message'] !== null) {
            return $feedback->addError('capacity', $attempt['message'])->setErrorFlushDescription($attempt['message'])->setStatus($attempt['status'])->autoInitFlush();
        }

        return $feedback
            ->setData($this->buildingMapper->toResponse($buildingRepository))
            ->setFlushDescription('Le bâtiment a été créé avec succès.')
            ->autoInitFlush()
            ->setStatus(201);
    }

    public function update(string $uuid, BuildingRequest $request): Feedback
    {
        $feedback = new Feedback();

        $buildingRepository = $this->findBuilding($uuid, $feedback);
        if ($buildingRepository === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkBuildingAccess($buildingRepository, SecurityAction::UPDATE_BUILDING);

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
        if ($request->parcelUuid !== null
            && $request->parcelUuid !== (string) $buildingRepository->getParcel()->getUuid()
        ) {
            $feedback->addError(
                'parcelUuid',
                'Le rattachement à un autre parcelle n\'est pas autorisé.'
            );

            return $feedback
                ->setErrorFlushDescription('Changement de parcelle refusé.')
                ->autoInitFlush();
        }

        if ($request->reference !== null && $request->reference !== $buildingRepository->getReference()) {
            $existing = $this->buildingRepository->findOneByParcelAndReference(
                $buildingRepository->getParcel(),
                $request->reference
            );

            if ($existing !== null) {
                return $feedback->addError('reference', 'Cette référence existe déjà pour ce parcelle.')
                    ->setErrorFlushDescription('Conflit d\'unicité détecté.')
                    ->setStatus(409)
                    ->autoInitFlush();
            }
        }

        $oldValues = ['reference' => $buildingRepository->getReference(), 'name' => $buildingRepository->getName(), 'type' => $buildingRepository->getType()->value, 'numberOfFloors' => $buildingRepository->getNumberOfFloors(), 'description' => $buildingRepository->getDescription()];
        $this->buildingMapper->copyToEntity($request, $buildingRepository);
        $this->auditLogService->log(
            action: 'UPDATE_BUILDING', entityType: Building::class, entityId: (int) $buildingRepository->getId(),
            organization: $buildingRepository->getParcel()->getCity()->getOrganization(), user: $this->securityService->getCurrentUser(),
            oldValues: $oldValues,
            newValues: ['reference' => $buildingRepository->getReference(), 'name' => $buildingRepository->getName(), 'type' => $buildingRepository->getType()->value, 'numberOfFloors' => $buildingRepository->getNumberOfFloors(), 'description' => $buildingRepository->getDescription()],
            flush: false,
        );
        $this->entityManager->flush();

        return $feedback
            ->setData($this->buildingMapper->toResponse($buildingRepository))
            ->setFlushDescription('Le bâtiment a été mis à jour avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function delete(string $uuid): Feedback
    {
        $feedback = new Feedback();

        $buildingRepository = $this->findBuilding($uuid, $feedback);
        if ($buildingRepository === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkBuildingAccess($buildingRepository, SecurityAction::DELETE_BUILDING);

        if ($this->leaseRepository->findActiveLeaseForBuilding($buildingRepository) !== null) {
            return $feedback
                ->addError('buildingUuid', 'Un bâtiment lié à un bail actif ne peut pas être supprimé.')
                ->setErrorFlushDescription('Terminez ou annulez les baux actifs et archivez les unités avant de supprimer ce bâtiment.')
                ->autoInitFlush()
                ->setStatus(409);
        }

        if ($this->unitRepository->findByBuilding($buildingRepository) !== []) {
            return $feedback
                ->addError('buildingUuid', 'Un bâtiment qui contient encore des unités ne peut pas être supprimé.')
                ->setErrorFlushDescription('Archivez d’abord les unités de ce bâtiment. Les baux historiques restent conservés.')
                ->autoInitFlush()
                ->setStatus(409);
        }

        $actor = $this->securityService->getCurrentUser();
        $buildingRepository->softDelete();
        $this->auditLogService->log(
            action: 'SOFT_DELETE_BUILDING', entityType: Building::class, entityId: (int) $buildingRepository->getId(),
            organization: $buildingRepository->getParcel()->getCity()->getOrganization(), user: $actor,
            oldValues: ['name' => $buildingRepository->getName(), 'reference' => $buildingRepository->getReference()],
            newValues: ['deletedAt' => $buildingRepository->getDeletedAt()?->format(DATE_ATOM)],
            flush: false,
        );
        $this->entityManager->flush();

        return $feedback
            ->setFlushDescription('Le bâtiment a été supprimé avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Résolution du parent (ville, parcelle, bâtiment) par UUID public, avec
     * contrôle d'accès : la ressource ne peut être rattachée qu'à un parent
     * que l'appelant est autorisé à administrer.
     */
    private function resolveParent(?string $uuid, Feedback $feedback): ?Parcel
    {
        if ($uuid === null || $uuid === '') {
            $feedback->addError('parcelUuid', 'Le parcelle est obligatoire.');

            return null;
        }

        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            $feedback->addError('parcelUuid', 'Identifiant de parcelle invalide.');

            return null;
        }

        $parent = $this->parcelRepository->findOneByUuid($parsed);

        if ($parent === null) {
            $feedback
                ->addError('parcelUuid', 'Le parcelle spécifié n\'existe pas.')
                ->setStatus(404);
        }

        return $parent;
    }

    private function findBuilding(string $uuid, Feedback $feedback): ?Building
    {
        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            $feedback
                ->setErrorFlushDescription('Identifiant de bâtiment invalide.')
                ->setStatus(400);

            return null;
        }

        $buildingRepository = $this->buildingRepository->findOneByUuid($parsed);

        if ($buildingRepository === null) {
            $feedback
                ->setErrorFlushDescription('Le bâtiment demandé n\'existe pas.')
                ->setStatus(404);
        }

        return $buildingRepository;
    }
}
