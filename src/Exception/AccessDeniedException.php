<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Accès refusé : l'utilisateur est authentifié mais n'a pas le droit
 * d'effectuer l'opération demandée → HTTP 403.
 *
 * Elle hérite de `AccessDeniedHttpException`, donc de
 * `HttpExceptionInterface`, pour que `ApiExceptionListener` la traduise
 * automatiquement. Tant qu'elle héritait de `\Exception` seulement, elle
 * tombait dans la branche 500 : un refus d'autorisation, cas normal d'un
 * contrôle d'accès, était renvoyé au client comme une erreur serveur.
 */
class AccessDeniedException extends AccessDeniedHttpException
{
    public static function create(string $message): self
    {
        return new self($message);
    }
}
