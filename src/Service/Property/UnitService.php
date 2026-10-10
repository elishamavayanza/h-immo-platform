<?php

declare(strict_types=1);

namespace App\Service\Property;

use App\Dto\Feedback;
use App\Dto\Request\Property\UnitFilterDto;
use App\Dto\Request\Property\UnitRequest;
use App\Entity\Property\Building;
use App\Entity\Property\City;
use App\Entity\Property\Unit;
use App\Mapper\Property\UnitMapper;
use App\Repository\Property\BuildingRepository;
use App\Repository\Property\UnitRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
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
        private BuildingRepository $buildingRepository,
        private UnitMapper $unitMapper,
        private SecurityServiceInterface $securityService,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
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
                'items' => array_map([$this->unitMapper, 'toResponse'], $result['items']),
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
            ->setData($this->unitMapper->toResponse($unitRepository))
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
        $this->unitMapper->copyToEntity($request, $unitRepository);

        $this->entityManager->persist($unitRepository);
        $this->entityManager->flush();

        return $feedback
            ->setData($this->unitMapper->toResponse($unitRepository))
            ->setFlushDescription('Le unité a été créé avec succès.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    public function update(string $uuid, UnitRequest $request): Feedback
    {
        $feedback = new Feedback();

        $unitRepository = $this->findUnit($uuid, $feedback);
        if ($unitRepository === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkUnitAccess($unitRepository, SecurityAction::UPDATE_UNIT);

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

        $this->unitMapper->copyToEntity($request, $unitRepository);
        $this->entityManager->flush();

        return $feedback
            ->setData($this->unitMapper->toResponse($unitRepository))
            ->setFlushDescription('Le unité a été mis à jour avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function delete(string $uuid): Feedback
    {
        $feedback = new Feedback();

        $unitRepository = $this->findUnit($uuid, $feedback);
        if ($unitRepository === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkUnitAccess($unitRepository, SecurityAction::DELETE_UNIT);

        $unitRepository->softDelete();
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
     * applique son propre contrôle (`PUBLISH_LISTING`) après la résolution.
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
}
