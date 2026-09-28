<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Feedback;
use App\Dto\Request\Auth\ForgotPasswordRequest;
use App\Dto\Request\Auth\ResetPasswordRequest;
use App\Dto\Response\HttpErrorResponsePayload;
use App\Entity\Identity\User;
use App\Repository\Identity\RevokedTokenRepository;
use App\Service\Identity\PasswordResetService;
use App\Dto\Response\Identity\SessionUserResponse;
use App\Service\Identity\SessionUserResponseFactory;
use App\Service\Identity\TokenManager;
use App\Service\System\AuditLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
 * Endpoints protégés (en-tête `Authorization: Bearer <jeton>`) :
 * - POST /api/auth/login           : authentification (firewall `json_login`)
 * - POST /api/auth/logout          : déconnexion (révoque le jeton)
 * - GET  /api/auth/me              : utilisateur courant
 */
#[OA\Tag(name: 'Auth', description: 'Authentification, session et gestion des mots de passe.')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly PasswordResetService $passwordResetService,
        private readonly SessionUserResponseFactory $sessionUserResponseFactory,
        private readonly TokenManager $tokenManager,
        private readonly RevokedTokenRepository $revokedTokenRepository,
        private readonly AuditLogService $auditLogService,
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
        description: 'Authentifie l\'utilisateur et retourne un jeton d\'API (JWT HS256) ainsi que ses informations de session. Le jeton se transmet ensuite dans l\'en-tête `Authorization: Bearer <token>` ; il n\'est stocké ni dans un cookie ni dans une session serveur.',
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
            new OA\Response(response: 200, description: 'Connexion réussie. Le jeton est renvoyé dans le corps de la réponse.', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'accessToken', type: 'string', description: 'Jeton à envoyer dans l\'en-tête Authorization: Bearer.'),
                    new OA\Property(property: 'tokenType', type: 'string', example: 'Bearer'),
                    new OA\Property(property: 'expiresIn', type: 'integer', description: 'Durée de validité en secondes.', example: 3600),
                    new OA\Property(property: 'user', ref: new Model(type: SessionUserResponse::class, name: 'SessionUserResponse')),
                ]
            )),
            new OA\Response(response: 401, description: 'Identifiants invalides', content: new OA\JsonContent(ref: '#/components/schemas/Feedback')),
            new OA\Response(response: 429, description: 'Trop de tentatives', content: new OA\JsonContent(ref: '#/components/schemas/Feedback')),
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

        $sessionUser = $this->sessionUserResponseFactory->create($user);
        $issued = $this->tokenManager->issue($sessionUser);

        // Log d'audit : connexion utilisateur
        $this->auditLogService->log(
            action: 'LOGIN',
            entityType: User::class,
            entityId: $user->getId(),
            organization: null,
            user: $user,
            oldValues: null,
            newValues: [
                'email' => $user->getEmail(),
            ],
        );

        // Aucun cookie : le pare-feu est `stateless`. Le jeton ne transite
        // que dans le corps de cette réponse.
        return new JsonResponse([
            'accessToken' => $issued['token'],
            'tokenType' => 'Bearer',
            'expiresIn' => $this->tokenManager->ttl(),
            'user' => $sessionUser,
        ]);
    }

    #[Route(
        path: '/api/auth/logout',
        name: 'api_logout',
        methods: ['POST'],
    )]
    #[OA\Post(
        path: '/api/auth/logout',
        summary: 'Déconnexion',
        description: 'Révoque le jeton présenté : son `jti` est inscrit en table de révocation, ce qui le rend inutilisable même s\'il a été intercepté. Sans cet appel, un jeton volé resterait valide jusqu\'à son expiration.',
        security: [['bearer' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Déconnexion effectuée, jeton révoqué.'),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $user = $this->getUser();

        $this->tokenStorage->setToken(null);

        // Le pare-feu est `stateless` : il n'y a aucune session à
        // invalider. La révocation du jeton est le seul moyen de rendre
        // la déconnexion réelle, et elle repose sur le `jti` porté par le
        // jeton que le client présente.
        $revoked = $this->revokePresentedToken($request);

        // Log d'audit : déconnexion utilisateur
        if ($user instanceof User) {
            $this->auditLogService->log(
                action: 'LOGOUT',
                entityType: User::class,
                entityId: $user->getId(),
                organization: null,
                user: $user,
                oldValues: null,
                newValues: [
                    'tokenRevoked' => $revoked,
                ],
            );
        }

        return new JsonResponse([
            'message' => 'Déconnexion effectuée.',
            'tokenRevoked' => $revoked,
        ]);
    }

    /**
     * Inscrit le `jti` du jeton présenté dans la table de révocation.
     *
     * Un jeton absent, expiré ou déjà révoqué n'est pas une erreur de
     * déconnexion : le client est déjà dans l'état voulu. Renvoie
     * simplement `false`.
     */
    private function revokePresentedToken(Request $request): bool
    {
        $authorization = (string) $request->headers->get('Authorization');

        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return false;
        }

        try {
            $claims = $this->tokenManager->parse(trim($matches[1]));
        } catch (\Throwable) {
            return false;
        }

        $jti = $claims['jti'] ?? null;
        $exp = $claims['exp'] ?? null;

        if (!\is_string($jti) || !\is_int($exp)) {
            return false;
        }

        return $this->revokedTokenRepository->revoke(
            $jti,
            (new \DateTimeImmutable())->setTimestamp($exp)
        );
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
        security: [['bearer' => []]],
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

        // Log d'audit : demande de réinitialisation de mot de passe
        $this->auditLogService->log(
            action: 'FORGOT_PASSWORD',
            entityType: User::class,
            entityId: 0, // L'utilisateur peut ne pas exister (anti-énumération)
            organization: null,
            user: null,
            oldValues: null,
            newValues: [
                'email' => $request->email,
            ],
        );

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

        // Log d'audit : réinitialisation de mot de passe
        // Note: on ne peut pas récupérer l'utilisateur ici car le service ne le retourne pas
        $this->auditLogService->log(
            action: 'RESET_PASSWORD',
            entityType: User::class,
            entityId: 0,
            organization: null,
            user: null,
            oldValues: null,
            newValues: [
                'tokenUsed' => true,
            ],
        );

        return $this->json($feedback, $feedback->getStatus());
    }
}
