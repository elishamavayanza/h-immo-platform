<?php

declare(strict_types=1);

namespace App\Security\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Jeton d'API absent, mal formé, expiré ou révoqué.
 *
 * Étend `AuthenticationException` pour que Symfony traite le cas comme
 * n'importe quel autre échec d'authentification : 401, en-tête
 * `WWW-Authenticate: Bearer`, et aucune trace de l'échec dans les logs
 * d'audit de sécurité.
 *
 * `getMessageKey()` est renvoyé au client dans la réponse JSON : c'est
 * donc le SEUL texte exposé, et il reste volontairement générique. Un
 * message détaillant « signature invalide » ou « jeton révoqué » aiderait
 * un attaquant à distinguer un jeton forgé d'un jeton expiré.
 */
final class InvalidApiTokenException extends AuthenticationException
{
    public function getMessageKey(): string
    {
        return 'Jeton invalide ou expiré. Veuillez vous reconnecter.';
    }
}
