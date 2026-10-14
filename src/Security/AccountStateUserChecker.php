<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Identity\User;
use App\Enum\OrganizationStatus;
use App\Repository\Identity\OrganizationUserRepository;
use App\Service\System\DateTimeService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Fournit un motif de suspension après vérification des identifiants.
 *
 * Le vérificateur livré par Symfony (`InMemoryUserChecker`) ne s'applique
 * qu'aux utilisateurs en mémoire : avec un provider Doctrine, un compte
 * désactivé par un administrateur parviendrait donc à se connecter.
 *
 * Les réponses restent génériques avant validation du mot de passe afin de
 * ne pas révéler l'existence ni l'état d'un compte.
 */
final class AccountStateUserChecker implements UserCheckerInterface
{
    public function __construct(
        private readonly OrganizationUserRepository $organizationUserRepository,
        private readonly RequestStack $requestStack,
        private readonly DateTimeService $dateTimeService,
    ) {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        // Le motif ne doit être révélé qu'après vérification du mot de passe.
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User || $this->requestStack->getCurrentRequest()?->getPathInfo() !== '/api/auth/login') {
            return;
        }

        if (!$user->isActive()) {
            $message = 'Ce compte est suspendu. Contactez l’administrateur pour rétablir votre accès.';
            if ($user->getSuspensionEndsAt() !== null) {
                $date = $this->dateTimeService->format($user->getSuspensionEndsAt(), 'd/m/Y à H:i', DateTimeService::PRESENTATION_TIMEZONE);
                $message = sprintf('Ce compte est suspendu jusqu’au %s. Contactez l’administrateur si vous avez besoin d’un accès immédiat.', $date);
            }

            $this->reject($user, $message);
        }

        $memberships = $this->organizationUserRepository->findByUser($user);
        $activeOrganizationExists = false;
        $suspendedOrganizations = [];
        foreach ($memberships as $membership) {
            $organization = $membership->getOrganization();
            if ($organization->getStatus() === OrganizationStatus::ACTIVE) {
                $activeOrganizationExists = true;
            } elseif ($organization->getStatus() === OrganizationStatus::SUSPENDED) {
                $suspendedOrganizations[] = $organization;
            }
        }

        // Un utilisateur multi-organisation garde l'accès dès qu'une de ses
        // organisations est active. Les organisations inactives ne sont pas
        // qualifiées de suspendues dans le message.
        if (!$activeOrganizationExists && $suspendedOrganizations !== []) {
            $organization = $suspendedOrganizations[0];
            $message = sprintf('L’organisation « %s » est suspendue. Contactez son administrateur pour rétablir l’accès.', $organization->getName());
            if ($organization->getSuspensionEndsAt() !== null) {
                $date = $this->dateTimeService->format($organization->getSuspensionEndsAt(), 'd/m/Y à H:i', DateTimeService::PRESENTATION_TIMEZONE);
                $message = sprintf('L’organisation « %s » est suspendue jusqu’au %s. Contactez son administrateur pour rétablir l’accès.', $organization->getName(), $date);
            }

            $this->reject($user, $message);
        }
    }

    private function reject(User $user, string $message): never
    {
        $exception = new CustomUserMessageAccountStatusException($message);
        $exception->setUser($user);

        throw $exception;
    }
}
