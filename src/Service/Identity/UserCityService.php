<?php

declare(strict_types=1);

namespace App\Service\Identity;

use App\Dto\Feedback;
use App\Dto\Request\Identity\UserCityRequest;
use App\Dto\Request\PaginationQuery;
use App\Entity\Identity\UserCity;
use App\Mapper\Identity\UserCityMapper;
use App\Repository\Identity\UserCityRepository;
use App\Repository\Identity\UserRepository;
use App\Repository\Property\CityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * UserCityService
 *
 * Package : Identity & Access — Service Métier
 *
 * Gère l'attribution et la révocation des zones géographiques (villes)
 * attribuées aux administrateurs locaux (ADMIN_VILLE).
 */
final readonly class UserCityService
{
    /**
     * Configure les dépendances pour la gestion du périmètre géographique des utilisateurs.
     * Injecte les repositories nécessaires, l'EntityManager, le mapper et le validateur.
     */
    public function __construct(
        private EntityManagerInterface $em,
        private UserCityRepository $userCityRepository,
        private UserRepository $userRepository,
        private CityRepository $cityRepository,
        private UserCityMapper $mapper,
        private ValidatorInterface $validator
    ) {
    }

    /**
     * Liste l'ensemble des villes attribuées à un utilisateur donné.
     * Retourne la pagination des affectations géographiques sous forme de Feedback.
     */
    public function listByUser(string $userUuid, PaginationQuery $query): Feedback
    {
        $feedback = new Feedback();
        $user = $this->userRepository->findOneBy(['uuid' => $userUuid]);

        if (!$user) {
            return $feedback
                ->addError('userUuid', 'Utilisateur introuvable.')
                ->setErrorFlushDescription('Impossible de récupérer la liste des villes.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $paginatedResult = $this->userCityRepository->findPaginatedByUser($user, $query);

        $data = [
            'items' => array_map([$this->mapper, 'toResponse'], $paginatedResult['items']),
            'total' => $paginatedResult['total'],
            'page' => $query->page,
            'limit' => $query->limit,
        ];

        return $feedback
            ->setData($data)
            ->setFlushDescription('Villes attribuées récupérées avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Attribue une nouvelle ville à un utilisateur administrateur.
     * Garantit l'absence de doublons d'attribution pour le couple (User, City).
     */
    public function assignCity(UserCityRequest $request): Feedback
    {
        $feedback = new Feedback();
        $violations = $this->validator->validate($request, groups: ['create']);

        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setErrorFlushDescription('Données d\'attribution de ville invalides.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $user = $this->userRepository->findOneBy(['uuid' => $request->userUuid]);
        if (!$user) {
            return $feedback
                ->addError('userUuid', 'Utilisateur spécifié introuvable.')
                ->setErrorFlushDescription('Attribution impossible.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $city = $this->cityRepository->findOneBy(['uuid' => $request->cityUuid]);
        if (!$city) {
            return $feedback
                ->addError('cityUuid', 'Ville spécifiée introuvable.')
                ->setErrorFlushDescription('Attribution impossible.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $existing = $this->userCityRepository->findOneBy(['user' => $user, 'city' => $city]);
        if ($existing) {
            return $feedback
                ->addError('cityUuid', 'Cette ville est déjà attribuée à cet utilisateur.')
                ->setErrorFlushDescription('Attribution déjà existante.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $userCity = new UserCity();
        $userCity->setUser($user);
        $userCity->setCity($city);

        $this->em->persist($userCity);
        $this->em->flush();

        return $feedback
            ->setData($this->mapper->toResponse($userCity))
            ->setFlushDescription('La ville a été attribuée à l\'utilisateur avec succès.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    /**
     * Révoque l'accès d'un utilisateur à une ville spécifique via l'UUID de liaison.
     * Supprime physiquement la relation d'attribution enregistrée dans user_city.
     */
    public function revokeCity(string $uuid): Feedback
    {
        $feedback = new Feedback();
        $userCity = $this->userCityRepository->findOneBy(['uuid' => $uuid]);

        if (!$userCity) {
            return $feedback
                ->addError('uuid', 'Attribution de ville introuvable.')
                ->setErrorFlushDescription('Révocation impossible.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $this->em->remove($userCity);
        $this->em->flush();

        return $feedback
            ->setFlushDescription('Attribution de la ville révoquée avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }
}
