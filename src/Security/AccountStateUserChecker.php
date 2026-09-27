<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Identity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Vérifie qu'un compte est utilisable avant de l'authentifier.
 *
 * Le vérificateur livré par Symfony (`InMemoryUserChecker`) ne s'applique
 * qu'aux utilisateurs en mémoire : avec un provider Doctrine, un compte
 * désactivé par un administrateur parviendrait donc à se connecter.
 *
 * Le message d'erreur est volontairement identique à celui d'un mot de
 * passe erroné, sinon la réponse permettrait de révéler l'existence d'un
 * compte désactivé.
 */
final class AccountStateUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive()) {
            $exception = new CustomUserMessageAccountStatusException('Invalid credentials.');
            $exception->setUser($user);

            throw $exception;
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
