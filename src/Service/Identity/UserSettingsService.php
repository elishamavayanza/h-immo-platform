<?php

declare(strict_types=1);

namespace App\Service\Identity;

use App\Entity\Identity\User;
use App\Repository\Identity\UserRepository;
use App\Security\SecurityServiceInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class UserSettingsService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SecurityServiceInterface $security,
    ) {
    }

    public function getSettings(User $user): array
    {
        return $user->getSettings() ?? [];
    }

    /**
     * Met à jour les préférences utilisateur.
     * L'utilisateur ne peut modifier que SES propres paramètres.
     * Les paramètres SUPER_ADMIN sont restreints.
     */
    public function updateSettings(User $currentUser, User $targetUser, array $payload): array
    {
        // Vérification : l'utilisateur ne peut modifier que ses propres paramètres
        if ($currentUser->getId() !== $targetUser->getId()) {
            $this->security->requirePlatformRole(\App\Enum\PlatformRole::SUPER_ADMIN);
        }

        $currentSettings = $targetUser->getSettings() ?? [];
        $newSettings = array_merge($currentSettings, $payload);

        // Filtrer les paramètres SUPER_ADMIN si l'utilisateur n'en a pas le droit
        if (!$this->security->isSuperAdmin()) {
            unset($newSettings['auditLogRetentionDays'], $newSettings['defaultPageSize']);
        }

        $targetUser->setSettings($newSettings);
        $this->userRepository->save($targetUser, flush: true);

        return $newSettings;
    }
}