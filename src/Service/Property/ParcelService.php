<?php

declare(strict_types=1);

namespace App\Service\Property;

use App\Dto\Feedback;
use App\Dto\Request\Property\ParcelFilterDto;
use App\Dto\Request\Property\ParcelRequest;
use App\Entity\Property\City;
use App\Entity\Property\Parcel;
use App\Mapper\Property\ParcelMapper;
use App\Repository\Property\CityRepository;
use App\Repository\Property\ParcelRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * ParcelService
 *
 * Package : Property Management — Service Métier
 *
 * Gère les parcelles (Parcel) d'une ville et assure l'unicité de leur
 * référence au sein de cette ville.
 *
 * Portée : une parcelle hérite de son organisation via `city`; la liste est
 * bornée aux villes réellement accessibles à l'appelant.
 */
final readonly class ParcelService
{
    public function __construct(
        private ParcelRepository $parcelRepository,
        private CityRepository $cityRepository,
        private ParcelMapper $parcelMapper,
        private SecurityServiceInterface $securityService,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
    ) {
    }

    public function list(?ParcelFilterDto $filter = null): Feedback
    {
        $feedback = new Feedback();
        $filter ??= new ParcelFilterDto();

        $cities = $this->securityService->getScopedCities();

        if ($filter->organizationId !== null && $filter->organizationId !== '') {
            $cities = $this->citiesOfOrganization($cities, $filter->organizationId);
        }

        if ($cities === []) {
            return $this->emptyListResponse($feedback, $filter->page, $filter->limit);
        }

        $city = null;
        if ($filter->cityUuid !== null && $filter->cityUuid !== '') {
            $city = $this->resolveScopedCity($filter->cityUuid, $cities);
            if ($city === null) {
                return $this->emptyListResponse($feedback, $filter->page, $filter->limit);
            }
        }

        $result = $this->parcelRepository->findPaginatedAccessible(
            $cities,
            $filter->page,
            $filter->limit,
            $filter->search,
            $city
        );

        return $feedback
            ->setData([
                'items' => array_map([$this->parcelMapper, 'toResponse'], $result['items']),
                'total' => $result['total'],
                'page' => $filter->page,
                'limit' => $filter->limit,
            ])
            ->setFlushDescription('Liste des parcelles récupérée avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Filtre une liste de villes sur celles d'une organisation donnée.
     *
     * Les villes proviennent déjà du périmètre de l'appelant
     * (`getScopedCities`) : une organisation hors périmètre ne possède
     * aucune ville dans cette liste et renvoie donc naturellement une liste
     * vide.
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
     * Résout une ville parente de filtre, bornée à la liste autorisée.
     *
     * @param list<City> $cities
     */
    private function resolveScopedCity(string $uuid, array $cities): ?City
    {
        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $city = $this->cityRepository->findOneByUuid($parsed);

        if ($city === null) {
            return null;
        }

        foreach ($cities as $candidate) {
            if ($candidate->getId() === $city->getId()) {
                return $city;
            }
        }

        return null;
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

        $parcelRepository = $this->findParcel($uuid, $feedback);
        if ($parcelRepository === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkParcelAccess($parcelRepository, SecurityAction::VIEW_PARCEL);

        return $feedback
            ->setData($this->parcelMapper->toResponse($parcelRepository))
            ->setFlushDescription('Détails du parcelle récupérés.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function create(ParcelRequest $request): Feedback
    {
        $feedback = new Feedback();

        $violations = $this->validator->validate($request, groups: ['create']);
        if (count($violations) > 0) {
            return $feedback->bind($violations)
                ->setErrorFlushDescription('Les données soumises sont invalides.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $parent = $this->resolveParent($request->cityUuid, $feedback);
        if ($parent === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkCityAccess($parent, SecurityAction::CREATE_PARCEL);

        if ($request->reference !== null
            && $this->parcelRepository->findOneByCityAndReference($parent, $request->reference) !== null
        ) {
            return $feedback->addError('reference', 'Cette référence existe déjà pour ce ville.')
                ->setErrorFlushDescription('Conflit d\'unicité détecté.')
                ->setStatus(409)
                ->autoInitFlush();
        }

        $parcelRepository = new Parcel();
        $parcelRepository->setCity($parent);
        $this->parcelMapper->copyToEntity($request, $parcelRepository);

        $this->entityManager->persist($parcelRepository);
        $this->entityManager->flush();

        return $feedback
            ->setData($this->parcelMapper->toResponse($parcelRepository))
            ->setFlushDescription('Le parcelle a été créé avec succès.')
            ->autoInitFlush()
            ->setStatus(201);
    }

    public function update(string $uuid, ParcelRequest $request): Feedback
    {
        $feedback = new Feedback();

        $parcelRepository = $this->findParcel($uuid, $feedback);
        if ($parcelRepository === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkParcelAccess($parcelRepository, SecurityAction::UPDATE_PARCEL);

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
        if ($request->cityUuid !== null
            && $request->cityUuid !== (string) $parcelRepository->getCity()->getUuid()
        ) {
            $feedback->addError(
                'cityUuid',
                'Le rattachement à un autre ville n\'est pas autorisé.'
            );

            return $feedback
                ->setErrorFlushDescription('Changement de ville refusé.')
                ->autoInitFlush();
        }

        if ($request->reference !== null && $request->reference !== $parcelRepository->getReference()) {
            $existing = $this->parcelRepository->findOneByCityAndReference(
                $parcelRepository->getCity(),
                $request->reference
            );

            if ($existing !== null) {
                return $feedback->addError('reference', 'Cette référence existe déjà pour ce ville.')
                    ->setErrorFlushDescription('Conflit d\'unicité détecté.')
                    ->setStatus(409)
                    ->autoInitFlush();
            }
        }

        $this->parcelMapper->copyToEntity($request, $parcelRepository);
        $this->entityManager->flush();

        return $feedback
            ->setData($this->parcelMapper->toResponse($parcelRepository))
            ->setFlushDescription('Le parcelle a été mis à jour avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function delete(string $uuid): Feedback
    {
        $feedback = new Feedback();

        $parcelRepository = $this->findParcel($uuid, $feedback);
        if ($parcelRepository === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkParcelAccess($parcelRepository, SecurityAction::DELETE_PARCEL);

        $parcelRepository->softDelete();
        $this->entityManager->flush();

        return $feedback
            ->setFlushDescription('Le parcelle a été supprimé avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Résolution du parent (ville, parcelle, bâtiment) par UUID public, avec
     * contrôle d'accès : la ressource ne peut être rattachée qu'à un parent
     * que l'appelant est autorisé à administrer.
     */
    private function resolveParent(?string $uuid, Feedback $feedback): ?City
    {
        if ($uuid === null || $uuid === '') {
            $feedback->addError('cityUuid', 'Le ville est obligatoire.');

            return null;
        }

        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            $feedback->addError('cityUuid', 'Identifiant de ville invalide.');

            return null;
        }

        $parent = $this->cityRepository->findOneByUuid($parsed);

        if ($parent === null) {
            $feedback
                ->addError('cityUuid', 'Le ville spécifié n\'existe pas.')
                ->setStatus(404);
        }

        return $parent;
    }

    private function findParcel(string $uuid, Feedback $feedback): ?Parcel
    {
        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            $feedback
                ->setErrorFlushDescription('Identifiant de parcelle invalide.')
                ->setStatus(400);

            return null;
        }

        $parcelRepository = $this->parcelRepository->findOneByUuid($parsed);

        if ($parcelRepository === null) {
            $feedback
                ->setErrorFlushDescription('Le parcelle demandé n\'existe pas.')
                ->setStatus(404);
        }

        return $parcelRepository;
    }
}
