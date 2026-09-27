<?php

declare(strict_types=1);

namespace App\Service\Property;

use App\Dto\Feedback;
use App\Dto\Request\PaginationQuery;
use App\Dto\Request\Property\BuildingRequest;
use App\Entity\Property\Building;
use App\Mapper\Property\BuildingMapper;
use App\Repository\Property\BuildingRepository;
use App\Repository\Property\ParcelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * BuildingService
 *
 * Package : Property Management — Service Métier
 *
 * Gère la logique applicative, la validation et la persistance des bâtiments (Building).
 * Assure également l'unicité des références par parcelle.
 */
final readonly class BuildingService
{
    /**
     * Initialise les repositories, le mapper et les services de validation.
     * Injecte l'EntityManager pour la persistance des entités Building.
     */
    public function __construct(
        private EntityManagerInterface $em,
        private BuildingRepository $buildingRepository,
        private ParcelRepository $parcelRepository,
        private BuildingMapper $mapper,
        private ValidatorInterface $validator
    ) {
    }

    /**
     * Récupère la liste paginée des bâtiments enregistrés.
     * Retourne le jeu de données mappé encapsulé dans un objet Feedback HTTP 200.
     */
    public function list(PaginationQuery $query): Feedback
    {
        $feedback = new Feedback();
        $paginatedResult = $this->buildingRepository->findPaginated($query);

        $data = [
            'items' => array_map([$this->mapper, 'toResponse'], $paginatedResult['items']),
            'total' => $paginatedResult['total'],
            'page' => $query->page,
            'limit' => $query->limit,
        ];

        return $feedback
            ->setData($data)
            ->setFlushDescription('Liste des bâtiments récupérée avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Restitue les détails d'un bâtiment spécifique désigné par son UUID.
     * Renvoie une réponse Feedback 404 si le bâtiment n'est pas trouvé.
     */
    public function getByUuid(string $uuid): Feedback
    {
        $feedback = new Feedback();
        $building = $this->buildingRepository->findOneBy(['uuid' => $uuid]);

        if (!$building) {
            return $feedback
                ->addError('uuid', 'Le bâtiment demandé n\'existe pas.')
                ->setErrorFlushDescription('Bâtiment introuvable.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        return $feedback
            ->setData($this->mapper->toResponse($building))
            ->setFlushDescription('Détails du bâtiment récupérés avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Enregistre un nouveau bâtiment rattaché à une parcelle existante.
     * Valide le DTO et garantit l'unicité de la référence au sein de la parcelle.
     */
    public function create(BuildingRequest $request): Feedback
    {
        $feedback = new Feedback();
        $violations = $this->validator->validate($request, groups: ['create']);

        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setErrorFlushDescription('Données du bâtiment invalides.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $parcel = $this->parcelRepository->findOneBy(['uuid' => $request->parcelUuid]);
        if (!$parcel) {
            return $feedback
                ->addError('parcelUuid', 'La parcelle spécifiée est introuvable.')
                ->setErrorFlushDescription('Parcelle inexistante.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $existing = $this->buildingRepository->findOneBy(['parcel' => $parcel, 'reference' => $request->reference]);
        if ($existing) {
            return $feedback
                ->addError('reference', 'Cette référence existe déjà pour cette parcelle.')
                ->setErrorFlushDescription('Référence de bâtiment déjà utilisée.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $building = new Building();
        $building->setParcel($parcel);
        $this->mapper->copyToEntity($request, $building);

        $this->em->persist($building);
        $this->em->flush();

        return $feedback
            ->setData($this->mapper->toResponse($building))
            ->setFlushDescription('Bâtiment créé avec succès.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    /**
     * Met à jour les propriétés d'un bâtiment existant identifié par son UUID.
     * Vérifie l'unicité de la référence en cas de modification de celle-ci.
     */
    public function update(string $uuid, BuildingRequest $request): Feedback
    {
        $feedback = new Feedback();
        $building = $this->buildingRepository->findOneBy(['uuid' => $uuid]);

        if (!$building) {
            return $feedback
                ->addError('uuid', 'Bâtiment introuvable.')
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

        if ($request->reference !== null && $request->reference !== $building->getReference()) {
            $existing = $this->buildingRepository->findOneBy([
                'parcel' => $building->getParcel(),
                'reference' => $request->reference,
            ]);

            if ($existing) {
                return $feedback
                    ->addError('reference', 'Cette référence est déjà attribuée dans cette parcelle.')
                    ->setErrorFlushDescription('Conflit de référence.')
                    ->setStatus(422)
                    ->autoInitFlush();
            }
        }

        $this->mapper->copyToEntity($request, $building);
        $this->em->flush();

        return $feedback
            ->setData($this->mapper->toResponse($building))
            ->setFlushDescription('Bâtiment mis à jour avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Effectue une suppression logique (Soft Delete) du bâtiment.
     * Marque la date de suppression sans retirer définitivement l'enregistrement.
     */
    public function delete(string $uuid): Feedback
    {
        $feedback = new Feedback();
        $building = $this->buildingRepository->findOneBy(['uuid' => $uuid]);

        if (!$building) {
            return $feedback
                ->addError('uuid', 'Bâtiment introuvable.')
                ->setErrorFlushDescription('Suppression impossible.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $building->softDelete();
        $this->em->flush();

        return $feedback
            ->setFlushDescription('Le bâtiment a été supprimé avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }
}
