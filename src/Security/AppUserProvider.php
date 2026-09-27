<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Identity\User;
use Symfony\Bridge\Doctrine\Security\User\EntityUserProvider;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Provider d'authentification qui refuse les comptes désactivés.
 *
 * Le `user_checker` de Symfony n'est pas appelé par l'authentificateur
 * `json_login` : un compte désactivé par un administrateur parviendrait
 * encore à se connecter.
 *
 * Le contrôle est placé ici, et non au seul moment du login, pour une
 * seconde raison : une session déjà ouverte resterait valide si le compte
 * était désactivé entre-temps. En refusant de résoudre un compte inactif,
 * la session en cours cesse d'être reconnue dès la requête suivante.
 *
 * Le message reste identique à celui d'un échec d'authentification, sans
 * quoi la réponse révélerait l'existence d'un compte désactivé.
 */
final class AppUserProvider extends EntityUserProvider
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = parent::loadUserByIdentifier($identifier);

        return $this->assertUsable($user);
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
