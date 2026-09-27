<?php

declare(strict_types=1);

namespace App\Service\Property;

use App\Dto\Feedback;
use App\Dto\Request\PaginationQuery;
use App\Dto\Request\Property\CityRequest;
use App\Entity\Identity\Organization;
use App\Entity\Property\City;
use App\Mapper\Property\CityMapper;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Property\CityRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * CityService
 *
 * Package : Property Management — Service Métier
 *
 * Administre le cycle de vie des villes d'exploitation (City) et contrôle
 * l'unicité du code de la ville par organisation.
 *
 * Portée : une ville appartient à une organization. La liste est bornée
 * aux organizations réellement accessibles à l'appelant, et resserrée aux
 * villes attitrées s'il est administrateur de ville.
 */
final readonly class CityService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CityRepository $cityRepository,
        private OrganizationRepository $organizationRepository,
        private CityMapper $mapper,
        private SecurityServiceInterface $securityService,
        private ValidatorInterface $validator
    ) {
    }

    /**
     * Liste paginée des villes accessibles à l'appelant.
     */
    public function list(PaginationQuery $query): Feedback
    {
        $feedback = new Feedback();

        $result = $this->cityRepository->findPaginatedAccessible(
            $this->securityService->getCurrentUserOrganizations(),
            $this->securityService->getAccessibleCities(),
            $query->page,
            $query->limit,
            $query->search
        );

        return $feedback
            ->setData([
                'items' => array_map([$this->mapper, 'toResponse'], $result['items']),
                'total' => $result['total'],
                'page' => $query->page,
                'limit' => $query->limit,
            ])
            ->setFlushDescription('Liste des villes récupérée avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function getByUuid(string $uuid): Feedback
    {
        $feedback = new Feedback();

        $city = $this->findCity($uuid, $feedback);
        if ($city === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkCityAccess($city, SecurityAction::VIEW_CITY);

        return $feedback
            ->setData($this->mapper->toResponse($city))
            ->setFlushDescription('Détails de la ville récupérés.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function create(CityRequest $request): Feedback
    {
        $feedback = new Feedback();

        $violations = $this->validator->validate($request, groups: ['create']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setErrorFlushDescription('Données de la ville invalides.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $organization = $this->resolveOrganization($request->organizationUuid, $feedback);
        if ($organization === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkOrganizationAccess($organization, SecurityAction::CREATE_CITY);

        if ($request->code !== null
            && $this->cityRepository->findOneByOrganizationAndCode($organization, $request->code) !== null
        ) {
            return $feedback
                ->addError('code', 'Ce code de ville existe déjà pour cette organisation.')
                ->setErrorFlushDescription('Code de ville indisponible.')
                ->setStatus(409)
                ->autoInitFlush();
        }

        $city = new City();
        $city->setOrganization($organization);
        $this->mapper->copyToEntity($request, $city);

        $this->em->persist($city);
        $this->em->flush();

        return $feedback
            ->setData($this->mapper->toResponse($city))
            ->setFlushDescription('Ville enregistrée avec succès.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    public function update(string $uuid, CityRequest $request): Feedback
    {
        $feedback = new Feedback();

        $city = $this->findCity($uuid, $feedback);
        if ($city === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkCityAccess($city, SecurityAction::UPDATE_CITY);

        $violations = $this->validator->validate($request, groups: ['update']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setErrorFlushDescription('Données de mise à jour invalides.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        // Le rattachement à une autre organization n'est pas modifiable.
        if ($request->organizationUuid !== null
            && $request->organizationUuid !== (string) $city->getOrganization()->getUuid()
        ) {
            $feedback->addError(
                'organizationUuid',
                'Le rattachement de la ville à une autre organisation n\'est pas autorisé.'
            );

            return $feedback
                ->setErrorFlushDescription('Changement d\'organisation refusé.')
                ->autoInitFlush();
        }

        if ($request->code !== null && $request->code !== $city->getCode()) {
            $existing = $this->cityRepository->findOneByOrganizationAndCode(
                $city->getOrganization(),
                $request->code
            );

            if ($existing !== null) {
                return $feedback
                    ->addError('code', 'Ce code de ville est déjà attribué dans cette organisation.')
                    ->setErrorFlushDescription('Code de ville déjà utilisé.')
                    ->setStatus(409)
                    ->autoInitFlush();
            }
        }

        $this->mapper->copyToEntity($request, $city);
        $this->em->flush();

        return $feedback
            ->setData($this->mapper->toResponse($city))
            ->setFlushDescription('Ville mise à jour avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Suppression logique d'une ville.
     *
     * La ville n'est pas réellement effacée : ses parcelles, bâtiments et
     * unités la référencent, une suppression physique rendrait l'historique
     * de location illisible.
     */
    public function delete(string $uuid): Feedback
    {
        $feedback = new Feedback();

        $city = $this->findCity($uuid, $feedback);
        if ($city === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkCityAccess($city, SecurityAction::DELETE_CITY);

        $city->softDelete();
        $this->em->flush();

        return $feedback
            ->setFlushDescription('La ville a été supprimée avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    private function resolveOrganization(?string $uuid, Feedback $feedback): ?Organization
    {
        if ($uuid === null || $uuid === '') {
            $feedback->addError('organizationUuid', 'L\'organisation est obligatoire.');

            return null;
        }

        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            $feedback->addError('organizationUuid', 'Identifiant d\'organisation invalide.');

            return null;
        }

        $organization = $this->organizationRepository->findOneByUuid($parsed);

        if ($organization === null) {
            $feedback
                ->addError('organizationUuid', 'L\'organisation spécifiée est introuvable.')
                ->setStatus(404);
        }

        return $organization;
    }

    private function findCity(string $uuid, Feedback $feedback): ?City
    {
        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            $feedback
                ->setErrorFlushDescription('Identifiant de ville invalide.')
                ->setStatus(400);

            return null;
        }

        $city = $this->cityRepository->findOneByUuid($parsed);

        if ($city === null) {
            $feedback
                ->setErrorFlushDescription('Ville introuvable.')
                ->setStatus(404);
        }

        return $city;
    }
}
