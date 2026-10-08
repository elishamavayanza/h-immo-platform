<?php

declare(strict_types=1);

namespace App\Dto\Response\Identity;

use OpenApi\Attributes as OA;

/**
 * UserSettingsResponse
 *
 * Package : Identity & Access — DTO de réponse
 *
 * Représente les préférences utilisateur.
 */
#[OA\Schema(title: 'UserSettingsResponse')]
final class UserSettingsResponse
{
    public function __construct(
        #[OA\Property(description: 'Thème de l\'interface', example: 'dark', nullable: true)]
        public ?string $theme = null,

        #[OA\Property(description: 'Langue de l\'interface', example: 'fr', nullable: true)]
        public ?string $locale = null,

        #[OA\Property(description: 'Format de date', example: 'DD/MM/YYYY', nullable: true)]
        public ?string $dateFormat = null,

        #[OA\Property(description: 'Notifications par e-mail', example: true, nullable: true)]
        public ?bool $emailNotifications = null,

        #[OA\Property(description: 'Notifications in-app', example: true, nullable: true)]
        public ?bool $inAppNotifications = null,

        #[OA\Property(description: 'Tableaux compacts', example: false, nullable: true)]
        public ?bool $compactTables = null,

        #[OA\Property(description: 'Animations réduites', example: false, nullable: true)]
        public ?bool $reducedMotion = null,

        // SUPER_ADMIN only
        #[OA\Property(description: 'Délai de rétention des logs d\'audit (jours)', example: 365, nullable: true)]
        public ?int $auditLogRetentionDays = null,

        #[OA\Property(description: 'Taille de page par défaut pour les listes', example: 20, nullable: true)]
        public ?int $defaultPageSize = null,
    ) {
    }

    public static function fromUser(\App\Entity\Identity\User $user): self
    {
        $settings = $user->getSettings() ?? [];

        return new self(
            theme: $settings['theme'] ?? 'system',
            locale: $settings['locale'] ?? 'fr',
            dateFormat: $settings['dateFormat'] ?? 'DD/MM/YYYY',
            emailNotifications: $settings['emailNotifications'] ?? true,
            inAppNotifications: $settings['inAppNotifications'] ?? true,
            compactTables: $settings['compactTables'] ?? false,
            reducedMotion: $settings['reducedMotion'] ?? false,
            auditLogRetentionDays: $settings['auditLogRetentionDays'] ?? null,
            defaultPageSize: $settings['defaultPageSize'] ?? 20,
        );
    }
}