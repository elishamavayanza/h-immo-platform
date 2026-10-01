<?php

declare(strict_types=1);

namespace App\Controller\Api\Identity;

use App\Dto\Request\Identity\UserRequest;
use App\Dto\Request\PaginationQuery;
use App\Service\Identity\UserService;
use App\Trait\FeedbackTrait;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UserController
 *
 * Package : Identity & Access — Contrôleur API REST
 *
 * Expose les points d'entrée d'API pour la gestion des comptes utilisateurs.
 * Traite les requêtes REST et retourne les réponses au format JSON structuré Feedback.
 */
#[Route('/api/v1/identity/users', name: 'api_users_')]
#[OA\Tag(name: 'Users', description: 'Gestion des comptes utilisateurs de la plateforme.')]
final class UserController extends AbstractController
{
    use FeedbackTrait;

    /**
     * Injecte le service métier de gestion des utilisateurs.
     * Délègue le traitement applicatif et les opérations de persistance.
     */
    public function __construct(
        private readonly UserService $userService
    ) {
    }

    /**
     * Endpoint API retournant la liste paginée des utilisateurs du système.
     * Mappe la Query String sur le DTO de pagination et retourne le Feedback.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        #[MapQueryString] ?PaginationQuery $query = null
    ): JsonResponse {
        $feedback = $this->userService->list($query ?? new PaginationQuery());

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Endpoint API restituant les détails d'un utilisateur par son UUID.
     * Recherche le profil correspondant et le retourne sous forme JSON.
     */
    #[Route('/{uuid}', name: 'show', methods: ['GET'])]
    public function show(string $uuid): JsonResponse
    {
        $feedback = $this->userService->getByUuid($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Endpoint API créant un nouvel utilisateur sur la plateforme.
     * Désérialise la requête JSON entrante et exécute la création.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] UserRequest $request
    ): JsonResponse {
        $feedback = $this->userService->create($request);

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Endpoint API permettant la modification des données d'un utilisateur.
     * Reçoit le payload de mise à jour et applique les modifications.
     *
     * Deux champs sont soumis à une autorisation propre, plus stricte que le
     * reste de la fiche : `platformRole` exige un SUPER_ADMIN (403 sinon), et
     * `isActive` un SUPER_ADMIN ou un PATRON de l'Organization concernée. Les
     * champs omis ne sont pas réécrits, en particulier `isActive` : un PUT
     * sans ce champ ne réactive donc pas un compte suspendu. Un utilisateur
     * ne peut modifier que sa propre fiche, sauf s'il est PATRON d'une
     * Organization à laquelle appartient la cible.
     */
    #[Route('/{uuid}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(
        string $uuid,
        #[MapRequestPayload] UserRequest $request
    ): JsonResponse {
        $feedback = $this->userService->update($uuid, $request);

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Endpoint API effectuant la suppression logique d'un compte utilisateur.
     * Désactive le compte spécifié tout en conservant l'historique des données.
     */
    #[Route('/{uuid}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $uuid): JsonResponse
    {
        $feedback = $this->userService->delete($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }
}
