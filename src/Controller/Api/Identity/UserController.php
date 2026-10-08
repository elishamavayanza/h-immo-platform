<?php

declare(strict_types=1);

namespace App\Controller\Api\Identity;

use App\Dto\Feedback;
use App\Dto\Request\Identity\UserRequest;
use App\Dto\Request\Identity\UserSettingsRequest;
use App\Dto\Request\Identity\UserSuspendRequest;
use App\Dto\Request\PaginationQuery;
use App\Dto\Response\Identity\UserSettingsResponse;
use App\Service\Identity\UserSettingsService;
use App\Service\Identity\UserService;
use App\Trait\FeedbackTrait;
use Nelmio\ApiDocBundle\Attribute\Model;
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
        private readonly UserService $userService,
        private readonly UserSettingsService $userSettingsService
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

    /**
     * Suspend un compte utilisateur et notifie l'intéressé par email.
     *
     * Désactive le compte (`isActive` → false), archive l'événement dans
     * l'audit (`SUSPEND_USER`) avec le motif fourni le cas échéant, puis
     * envoie un email à l'utilisateur (échec non bloquant → `warning`).
     * Un compte déjà désactivé (422) et l'auto-suspension (422) sont refusées.
     */
    #[Route('/{uuid}/suspend', name: 'suspend', methods: ['POST'])]
    #[OA\Post(
        summary: 'Suspendre un compte utilisateur',
        description: 'Désactive `isActive`, archive l\'événement `SUSPEND_USER` dans l\'audit (motif le cas échéant) et notifie l\'utilisateur par email. Un compte déjà désactivé ou son propre compte sont refusés.',
        security: [['bearer' => []]],
    )]
    #[OA\Parameter(
        name: 'uuid',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'uuid'),
        example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110'
    )]
    #[OA\RequestBody(
        required: false,
        content: new OA\JsonContent(ref: new Model(type: UserSuspendRequest::class))
    )]
    #[OA\Response(
        response: 200,
        description: 'Compte suspendu, utilisateur notifié',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Utilisateur introuvable'
    )]
    #[OA\Response(
        response: 422,
        description: 'Compte déjà désactivé, auto-suspension ou motif invalide'
    )]
    public function suspend(
        string $uuid,
        #[MapRequestPayload] UserSuspendRequest $request
    ): JsonResponse {
        $feedback = $this->userService->suspend($uuid, $request);

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Récupère les préférences de l'utilisateur courant.
     */
    #[Route('/me/settings', name: 'me_settings', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/identity/users/me/settings',
        summary: 'Obtenir mes préférences utilisateur',
        description: 'Retourne les préférences de l\'utilisateur authentifié (thème, langue, notifications, etc.).',
        security: [['bearer' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Préférences récupérées', content: new OA\JsonContent(ref: new Model(type: UserSettingsResponse::class))),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function getMySettings(): JsonResponse
    {
        $user = $this->getUser();

        return $this->json(
            UserSettingsResponse::fromUser($user),
            200
        );
    }

    /**
     * Met à jour les préférences de l'utilisateur courant.
     */
    #[Route('/me/settings', name: 'me_settings_update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(
        path: '/api/v1/identity/users/me/settings',
        summary: 'Mettre à jour mes préférences utilisateur',
        description: 'Met à jour partiellement les préférences de l\'utilisateur authentifié. Les paramètres SUPER_ADMIN sont ignorés si l\'utilisateur n\'a pas le rôle.',
        security: [['bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: UserSettingsRequest::class))
        ),
        responses: [
            new OA\Response(response: 200, description: 'Préférences mises à jour', content: new OA\JsonContent(ref: new Model(type: UserSettingsResponse::class))),
            new OA\Response(response: 401, description: 'Non authentifié', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Données invalides', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function updateMySettings(#[MapRequestPayload] UserSettingsRequest $request): JsonResponse
    {
        $user = $this->getUser();

        $payload = array_filter(
            (array) $request,
            fn($value) => $value !== null,
            ARRAY_FILTER_USE_BOTH
        );

        $settings = $this->userSettingsService->updateSettings($user, $user, $payload);

        return $this->json(UserSettingsResponse::fromUser($user));
    }
}
