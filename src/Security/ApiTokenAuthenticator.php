<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Identity\User;
use App\Repository\Identity\RevokedTokenRepository;
use App\Repository\Identity\UserRepository;
use App\Service\Identity\TokenManager;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Uid\Uuid;
use App\Security\Exception\InvalidApiTokenException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * ApiTokenAuthenticator
 *
 * Authentifie une requête à partir de l'en-tête
 * `Authorization: Bearer <jeton>`.
 *
 * Le pare-feu est `stateless` : aucun cookie de session n'est créé ni lu.
 * Chaque requête prouve son accès par le jeton qu'elle présente.
 *
 * Le compte est systématiquement rechargé depuis la base (UserBadge via
 * le provider) plutôt que déduit des revendications du jeton. C'est ce
 * qui rend le contenu du jeton non critique pour l'autorisation : un jeton
 * émis avant une désactivation de compte cesse de fonctionner dès la
 * requête suivante, sans attendre son expiration. Un compte supprimé
 * échoue de la même façon.
 *
 * `CityAccessScope` et les rôles métier ne sont pas relus ici : ils sont
 * reconstruits à la demande par `SecurityService` lorsqu'une route exige
 * un rôle, donc sans dette d'affichage à l'échelle du jeton.
 */
final class ApiTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly TokenManager $tokenManager,
        private readonly RevokedTokenRepository $revokedTokenRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // `false` et non `null` : une route publique qui reçoit un
        // en-tête `Authorization` ne doit pas être rejetée d'emblée.
        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $token = $this->extractToken($request);

        try {
            $claims = $this->tokenManager->parse($token);
        } catch (\Throwable $e) {
            // Signature invalide, expiration, format cassé : le client doit
            // savoir qu'il doit se reconnecter. On ne propage pas le
            // détail technique, qui ne lui sert à rien.
            throw new InvalidApiTokenException('Le jeton est invalide ou a expiré.', previous: $e);
        }

        $jti = $claims['jti'] ?? null;
        $sub = $claims['sub'] ?? null;

        if (!\is_string($jti) || !\is_string($sub)) {
            throw new InvalidApiTokenException('Le jeton est incomplet.');
        }

        if ($this->revokedTokenRepository->isRevoked($jti)) {
            throw new InvalidApiTokenException('Le jeton a été révoqué.');
        }

        // Le compte est rechargé en base à chaque requête, et résolu par
        // UUID et non par email : `app_user_provider` indexe sur l'email,
        // alors que `sub` est l'UUID. L'UUID est l'identifiant stable
        // (l'email peut être modifié), le resolver par UUID est donc le
        // seul qui tienne dans la durée.
        //
        // Ce chargement en base est ce qui referme le risque des droits
        // embarqués : un jeton émis avant une désactivation cesse de
        // fonctionner dès la requête suivante, sans attendre l'expiration.
        return new SelfValidatingPassport(new UserBadge($sub, $this->loadUserByUuid(...)));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return $this->unauthorized($exception->getMessageKey());
    }

    /**
     * Réponse à une requête protégée sans jeton valide.
     *
     * `WWW-Authenticate: Bearer` n'est pas décoratif : c'est ce qui
     * indique au client qu'il doit s'authentifier par jeton plutôt que
     * de provoquer une redirection vers un formulaire.
     */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->unauthorized('Authentification requise.');
    }

    /**
     * Réponse 401 de l'authentificateur.
     *
     * Le corps reprend exactement la forme de `HttpErrorResponsePayload`,
     * celle que produit `ApiExceptionListener` pour les autres refus.
     * Un client ne doit pas avoir à traiter deux contrats d'erreur
     * distincts selon que l'échec vient d'ici ou du pare-feu.
     */
    private function unauthorized(string $message): JsonResponse
    {
        return new JsonResponse(
            [
                'status' => Response::HTTP_UNAUTHORIZED,
                'error' => Response::$statusTexts[Response::HTTP_UNAUTHORIZED],
                'message' => $message,
                'details' => null,
            ],
            Response::HTTP_UNAUTHORIZED,
            ['WWW-Authenticate' => 'Bearer']
        );
    }

    /**
     * Résout un compte par UUID, en refusant les comptes désactivés.
     *
     * Un compte absent ET un compte désactivé lèvent la même exception,
     * avec le même message : les distinguer permettrait d'énumérer les
     * comptes existants. `UserBadge` masque de toute façon ce message en
     * « Invalid credentials. » avant la réponse.
     */
    private function loadUserByUuid(string $identifier): User
    {
        try {
            $uuid = Uuid::fromString($identifier);
        } catch (\InvalidArgumentException) {
            // `sub` malformé : un jeton produit par ce serveur est toujours
            // un UUID, donc celui-ci a été fabriqué.
            throw new UserNotFoundException();
        }

        $user = $this->userRepository->findOneByUuid($uuid);

        if ($user === null || !$user->isActive()) {
            throw new UserNotFoundException();
        }

        return $user;
    }

    /**
     * Extrait le jeton de l'en-tête `Authorization`.
     *
     * Le schéma est exigé : un en-tête `Authorization` sans préfixe
     * `Bearer` (ce que produit un oubli classique côté client) doit être
     * rejeté explicitement plutôt que traité comme un jeton brut.
     */
    private function extractToken(Request $request): string
    {
        $header = (string) $request->headers->get('Authorization');

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            throw new InvalidApiTokenException('En-tête Authorization absent ou mal formé.');
        }

        $token = trim($matches[1]);

        if ($token === '') {
            throw new InvalidApiTokenException('Jeton vide.');
        }

        return $token;
    }
}
