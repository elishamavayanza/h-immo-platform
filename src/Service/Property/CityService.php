<?php

declare(strict_types=1);

namespace App\Service\Property;

use App\Dto\Feedback;
use App\Dto\Request\PaginationQuery;
use App\Dto\Request\Property\CityRequest;
use App\Entity\Property\City;
use App\Mapper\Property\CityMapper;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Property\CityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * CityService
 *
 * Package : Property Management — Service Métier
 *
 * Administre le cycle de vie des villes d'exploitation (City) et contrôle
 * l'unicité du code de la ville par organisation.
 */
final readonly class CityService
{
    /**
     * Injecte l'EntityManager, les repositories et le service de mapping.
     * Prépare les dépendances pour la gestion des entités City.
     */
    public function __construct(
        private EntityManagerInterface $em,
        private CityRepository $cityRepository,
        private OrganizationRepository $organizationRepository,
        private CityMapper $mapper,
        private ValidatorInterface $validator
    ) {
    }

    /**
     * Retourne la liste paginée des villes configurées sur la plateforme.
     * Encapsule le résultat transformé dans une structure Feedback standard.
     */
    public function list(PaginationQuery $query): Feedback
    {
        $feedback = new Feedback();
        $paginatedResult = $this->cityRepository->findPaginated($query);

        $data = [
            'items' => array_map([$this->mapper, 'toResponse'], $paginatedResult['items']),
            'total' => $paginatedResult['total'],
            'page' => $query->page,
            'limit' => $query->limit,
        ];

        return $feedback
            ->setData($data)
            ->setFlushDescription('Liste des villes récupérée avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Extrait les informations d'une ville via son identifiant UUID unique.
     * Retourne une erreur HTTP 404 au format Feedback si la ville n'existe pas.
     */
    public function getByUuid(string $uuid): Feedback
    {
        $feedback = new Feedback();
        $city = $this->cityRepository->findOneBy(['uuid' => $uuid]);

        if (!$city) {
            return $feedback
                ->addError('uuid', 'La ville demandée n\'existe pas.')
                ->setErrorFlushDescription('Ville introuvable.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        return $feedback
            ->setData($this->mapper->toResponse($city))
            ->setFlushDescription('Détails de la ville récupérés.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Crée une nouvelle ville rattachée à une organisation donnée.
     * Valide l'absence de doublon sur le code unique de la ville dans l'organisation.
     */
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

        $organization = $this->organizationRepository->findOneBy(['uuid' => $request->organizationUuid]);
        if (!$organization) {
            return $feedback
                ->addError('organizationUuid', 'L\'organisation spécifiée est introuvable.')
                ->setErrorFlushDescription('Organisation inexistante.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $existing = $this->cityRepository->findOneBy([
            'organization' => $organization,
            'code' => $request->code,
        ]);

        if ($existing) {
            return $feedback
                ->addError('code', 'Ce code de ville existe déjà pour cette organisation.')
                ->setErrorFlushDescription('Code de ville indisponible.')
                ->setStatus(422)
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

    /**
     * Modifie les attributs d'une ville désignée par son UUID.
     * Valide les nouvelles valeurs et vérifie l'unicité du code si modifié.
     */
    public function update(string $uuid, CityRequest $request): Feedback
    {
        $feedback = new Feedback();
        $city = $this->cityRepository->findOneBy(['uuid' => $uuid]);

        if (!$city) {
            return $feedback
                ->addError('uuid', 'Ville introuvable.')
                ->setErrorFlushDescription('Mise à jour impossible.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $violations = $this->validator->validate($request, groups: ['update']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setErrorFlushDescription('Données de mise à jour invalides.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        if ($request->code !== null && $request->code !== $city->getCode()) {
            $existing = $this->cityRepository->findOneBy([
                'organization' => $city->getOrganization(),
                'code' => $request->code,
            ]);

            if ($existing) {
                return $feedback
                    ->addError('code', 'Ce code de ville est déjà attribué dans cette organisation.')
                    ->setErrorFlushDescription('Code de ville déjà utilisé.')
                    ->setStatus(422)
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
     * Supprime logiquement une ville de la base de données.
     * Déclenche le soft delete tout en maintenant l'intégrité des parcelles sous-jacentes.
     */
    public function delete(string $uuid): Feedback
    {
        $feedback = new Feedback();
        $city = $this->cityRepository->findOneBy(['uuid' => $uuid]);

        if (!$city) {
            return $feedback
                ->addError('uuid', 'Ville introuvable.')
                ->setErrorFlushDescription('Suppression impossible.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $city->softDelete();
        $this->em->flush();

        return $feedback
            ->setFlushDescription('La ville a été supprimée avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }
}
