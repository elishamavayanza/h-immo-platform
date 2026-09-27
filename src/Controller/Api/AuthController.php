<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Response\HttpErrorResponsePayload;
use App\Entity\Identity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Authentification de l'API.
 *
 * Le contrôle d'authentification lui-même est délégué à
 * `json_login` (configuré dans `config/packages/security.yaml`) : ces
 * routes ne font que décrire la requête et la réponse. Le succès et
 * l'échec sont traités par le firewall, pas ici — c'est ce qui évite
 * d'avoir deux implémentations du même contrôle, inevitably divergentes.
 */
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * Point d'entrée du firewall `json_login` : le corps de la requête est
     * consommé par l'authentificateur, cette action n'est atteinte qu'en
     * cas de succès.
     */
    #[Route(
        path: '/api/auth/login',
        name: 'api_login',
        methods: ['POST'],
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
            'user' => [
                'uuid' => $user->getUuid()->toRfc4122(),
                'email' => $user->getEmail(),
                'fullName' => $user->getFullName(),
                'platformRole' => $user->getPlatformRole()?->value,
            ],
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
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->failure();
        }

        return new JsonResponse([
            'uuid' => $user->getUuid()->toRfc4122(),
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'platformRole' => $user->getPlatformRole()?->value,
        ]);
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
}
