<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Feedback;
use App\Dto\Request\Auth\ForgotPasswordRequest;
use App\Dto\Request\Auth\ResetPasswordRequest;
use App\Dto\Response\HttpErrorResponsePayload;
use App\Entity\Identity\User;
use App\Service\Identity\PasswordResetService;
use App\Dto\Response\Identity\SessionUserResponse;
use App\Service\Identity\SessionUserResponseFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use OpenApi\Attributes as OA;
use Nelmio\ApiDocBundle\Attribute\Model;

/**
 * Authentification de l'API.
 *
 * Le contrôle d'authentification lui-même est délégué à
 * `json_login` (configuré dans `config/packages/security.yaml`) : ces
 * routes ne font que décrire la requête et la réponse. Le succès et
 * l'échec sont traités par le firewall, pas ici — c'est ce qui évite
 * d'avoir deux implémentations du même contrôle, inevitably divergentes.
 *
 * Endpoints publics (sans authentification) :
 * - POST /api/auth/forgot-password : demande de réinitialisation
 * - POST /api/auth/reset-password  : validation jeton + nouveau mot de passe
 *
 * Endpoints protégés (firewall json_login) :
 * - POST /api/auth/login           : authentification (firewall)
 * - POST /api/auth/logout          : déconnexion
 * - GET  /api/auth/me              : utilisateur courant
 */
#[OA\Tag(name: 'Auth', description: 'Authentification, session et gestion des mots de passe.')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly PasswordResetService $passwordResetService,
        private readonly SessionUserResponseFactory $sessionUserResponseFactory,
    ) {
    }

    /**
     * Authentification par email et mot de passe.
     *
     * Le firewall `json_login` consomme le corps de la requête (email + password).
     * Cette action n'est atteinte qu'en cas de succès d'authentification.
     *
     * @param array{email: string, password: string} Corps de la requête
     */
    #[Route(
        path: '/api/auth/login',
        name: 'api_login',
        methods: ['POST'],
    )]
    #[OA\Post(
        path: '/api/auth/login',
        summary: 'Connexion par email et mot de passe',
        description: 'Authentifie l\'utilisateur et retourne ses informations de session. Le jeton est un cookie de session `HIMMOMPA` (HttpOnly) émis dans l\'en-tête `Set-Cookie`, volontairement absent du corps JSON pour n\'être pas exposé au JavaScript de la page. Pour voir le cookie dans Swagger UI : onglet Application > Cookies du navigateur, Swagger ne peut pas afficher `Set-Cookie`.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'MonMotDePasse123!')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Connexion réussie. Le cookie de session `HIMMOMPA` est émis dans `Set-Cookie`.', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'user', ref: new Model(type: SessionUserResponse::class, name: 'SessionUserResponse')),
                ]
            )),
            new OA\Response(response: 401, description: 'Identifiants invalides', content: new OA\JsonContent(ref: '#/components/schemas/Feedback')),
        ]
    )]
    public function login(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            // Inatteignable en pratique : le firewall n'aboutit pas ici sans
            // utilisateur. On renvoie malgré tout 401 plutôt qu'un 200
            // trompeur, au cas où la configuration evoluerait.
            return $this->failure();
        }

        return new JsonResponse([
            'user' => $this->sessionUserResponseFactory->create($user),
        ]);
    }

    #[Route(
        path: '/api/auth/logout',
        name: 'api_logout',
        methods: ['POST'],
    )]
    public function logout(): JsonResponse
    {
        $this->tokenStorage->setToken(null);

        return new JsonResponse(['message' => 'Déconnexion effectuée.']);
    }

    /**
     * Permet au client de vérifier la validité de sa session sans
     * déclencher d'écriture.
     */
    #[Route(
        path: '/api/auth/me',
        name: 'api_me',
        methods: ['GET'],
    )]
    #[OA\Get(
        path: '/api/auth/me',
        summary: 'Utilisateur de la session courante',
        description: 'Retourne l\'identité et les droits de l\'utilisateur authentifié par le cookie de session. Permet à un client de restaurer son état au rechargement de la page sans relancer une connexion.',
        security: [['sessionCookie' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Session courante', content: new OA\JsonContent(ref: new Model(type: SessionUserResponse::class, name: 'SessionUserResponse'))),
            new OA\Response(response: 401, description: 'Session absente ou expirée', content: new OA\JsonContent(ref: '#/components/schemas/Feedback')),
        ]
    )]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->failure();
        }

        return new JsonResponse($this->sessionUserResponseFactory->create($user));
    }

    private function failure(): JsonResponse
    {
        $payload = new HttpErrorResponsePayload(
            status: Response::HTTP_UNAUTHORIZED,
            error: Response::$statusTexts[Response::HTTP_UNAUTHORIZED] ?? 'Unauthorized',
            message: 'Authentification requise.',
        );

        return new JsonResponse(
            ['status' => $payload->status, 'error' => $payload->error, 'message' => $payload->message],
            $payload->status
        );
    }

    /**
     * Demande de réinitialisation de mot de passe (mot de passe oublié).
     *
     * Ne révèle PAS si l'email existe : répond toujours 200 avec le même
     * message pour éviter l'énumération d'emails.
     *
     * Si l'utilisateur existe et est actif, un jeton de réinitialisation
     * à usage unique (validité 1h) est généré et envoyé par email.
     */
    #[Route(
        path: '/api/auth/forgot-password',
        name: 'api_forgot_password',
        methods: ['POST'],
    )]
    #[OA\Post(
        path: '/api/auth/forgot-password',
        summary: 'Demander la réinitialisation de son mot de passe',
        description: 'Envoie un lien de réinitialisation par email si le compte existe et est actif. Ne révèle pas si l\'email existe.',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: '#/components/schemas/ForgotPasswordRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Si l\'email existe, un lien a été envoyé', content: new OA\JsonContent(ref: '#/components/schemas/Feedback')),
            new OA\Response(response: 422, description: 'Données invalides', content: new OA\JsonContent(ref: '#/components/schemas/Feedback')),
        ]
    )]
    public function forgotPassword(
        #[MapRequestPayload] ForgotPasswordRequest $request
    ): JsonResponse {
        $feedback = $this->passwordResetService->requestReset($request->email);

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Réinitialisation du mot de passe via le jeton reçu par email.
     *
     * Valide le jeton (non expiré, non consommé, hash correct),
     * hache le nouveau mot de passe, marque le jeton consommé.
     */
    #[Route(
        path: '/api/auth/reset-password',
        name: 'api_reset_password',
        methods: ['POST'],
    )]
    #[OA\Post(
        path: '/api/auth/reset-password',
        summary: 'Réinitialiser son mot de passe avec le jeton reçu par email',
        description: 'Valide le jeton reçu par email, définit le nouveau mot de passe, marque le jeton comme consommé.',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: '#/components/schemas/ResetPasswordRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Mot de passe réinitialisé', content: new OA\JsonContent(ref: '#/components/schemas/Feedback')),
            new OA\Response(response: 422, description: 'Jeton invalide ou expiré', content: new OA\JsonContent(ref: '#/components/schemas/Feedback')),
        ]
    )]
    public function resetPassword(
        #[MapRequestPayload] ResetPasswordRequest $request
    ): JsonResponse {
        $feedback = $this->passwordResetService->resetPassword($request->token, $request->newPassword);

        return $this->json($feedback, $feedback->getStatus());
    }
}
