<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Utilisateur non authentifié (pas de session, pas de jeton valide,
 * compte rendu inactif au moment de l'appel) → HTTP 401.
 *
 * Distinguer ce cas d'un 403 est important : un 401 invite le client à
 * s'authentifier, alors qu'un 403 signifie « authentifié mais pas
 * autorisé ». Renvoyer 403 pour un utilisateur non authentifié donne au
 * client l'impression d'un problème de droits et le pousse à boucler sur
 * des demande de droits sans jamais s'authentifier.
 */
class UnauthenticatedException extends UnauthorizedHttpException
{
    public static function create(string $message = 'Authentification requise.'): self
    {
        return new self($message);
    }
}
