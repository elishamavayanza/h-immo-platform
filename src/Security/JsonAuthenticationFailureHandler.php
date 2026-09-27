<?php

declare(strict_types=1);

namespace App\Security;

use App\Dto\Response\HttpErrorResponsePayload;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Réponse d'échec d'authentification au format de l'API.
 *
 * Le gestionnaire livré par Symfony renvoie un 401 quelle que soit la
 * cause. Un client ne peut alors pas distinguer un mot de passe erroné
 * d'un blocage temporaire : il réessaie en boucle et aggrave le
 * verrouillage. Le blocage doit donc être signalé par un 429.
 */
final class JsonAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $tropEssais = $exception instanceof TooManyLoginAttemptsAuthenticationException;
        $status = $tropEssais ? Response::HTTP_TOO_MANY_REQUESTS : Response::HTTP_UNAUTHORIZED;

        $message = $tropEssais
            ? 'Trop de tentatives de connexion. Réessayez plus tard.'
            : 'Identifiants invalides.';

        $payload = new HttpErrorResponsePayload(
            $status,
            Response::$statusTexts[$status] ?? 'Error',
            $message,
        );

        $response = new JsonResponse($payload, $status);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
