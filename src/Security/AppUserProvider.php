<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Identity\User;
use Symfony\Bridge\Doctrine\Security\User\EntityUserProvider;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Provider d'authentification qui refuse les comptes désactivés lors du rechargement.
 *
 * Le chargement par identifiant laisse le user checker signaler une suspension
 * après validation du mot de passe. Le rechargement des requêtes protégées,
 * lui, continue de refuser tout compte désactivé.
 *
 * Le message générique du rechargement évite de révéler l'état du compte via
 * un ancien jeton.
 */
final class AppUserProvider extends EntityUserProvider
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Le compte doit être chargé pour que Symfony puisse vérifier le mot
        // de passe avant de révéler un éventuel motif de suspension.
        return parent::loadUserByIdentifier($identifier);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        //Appelé à chaque requête pour une session déjà ouverte : c'est ce
        // qui fait expirer immédiatement une session après désactivation.
        $user = parent::refreshUser($user);

        return $this->assertUsable($user);
    }

    private function assertUsable(UserInterface $user): UserInterface
    {
        if ($user instanceof User && !$user->isActive()) {
            $exception = new CustomUserMessageAccountStatusException('Invalid credentials.');
            $exception->setUser($user);

            throw $exception;
        }

        return $user;
    }
}
