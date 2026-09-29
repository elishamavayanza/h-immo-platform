<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Identity\User;
use App\Service\System\DateTimeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Horodate la dernière connexion réussie.
 *
 * `User::$lastLoginAt` existe et possède son setter, mais aucun code ne
 * l'alimentait : le champ restait `null` en permanence. Il est désormais
 * exposé dans la réponse de session, où une valeur toujours nulle
 * n'apprendrait rien au client et donnerait l'illusion d'une
 * functionality inexistante.
 *
 * L'événement `LoginSuccessEvent` est déclenché par l'authenticator
 * pendant l'authentification, donc avant que le contrôleur ne compose sa
 * réponse : la date retournée au client est déjà celle du moment de la
 * connexion, pas une date approximative lue plus tard.
 *
 * Un `UPDATE` par connexion assumé : c'est le prix d'un historique de
 * connexion exploitable, et la requête est indexée sur la clé
 * primaire.
 */
final class LastLoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DateTimeService $dateTime,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $user->setLastLoginAt($this->dateTime->now());
        $this->entityManager->flush();
    }
}
