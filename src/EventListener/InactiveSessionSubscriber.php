<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Identity\User;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Déconnecte les sessions devenues invalides.
 *
 * `AppUserProvider` bloque la connexion d'un compte désactivé, mais une
 * session déjà ouverte conserve l'entité `User` sérialisée : le provider
 * n'est plus-sollicité et le compte désactivé conserve son accès
 * jusqu'à l'expiration de la session.
 *
 * Ce listener compare donc l'état en base à celui de la session à chaque
 * requête. Il s'exécute après le firewall (priorité 8) pour disposer du
 * jeton, et avant le contrôleur pour qu'aucune donnée ne soit lue.
 */
final class InactiveSessionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Priorité 0 : le firewall (8) a déjà résolu le jeton.
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();

        if ($token === null) {
            return;
        }

        $user = $token->getUser();

        if (!$user instanceof User || $user->isActive()) {
            return;
        }

        // Le stockage est vidé : la requête suivante est traitée comme
        // anonyme et l'API répond 401, sans rien divulguer du compte.
        $this->tokenStorage->setToken(null);
    }

}
