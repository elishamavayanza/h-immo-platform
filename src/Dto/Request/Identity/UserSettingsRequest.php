<?php

declare(strict_types=1);

namespace App\Dto\Request\Identity;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * UserSettingsRequest
 *
 * Package : Identity & Access — DTO de requête
 *
 * Représente les préférences utilisateur modifiables.
 */
#[OA\Schema(title: 'UserSettingsRequest')]
final class UserSettingsRequest
{
    public function __construct(
        #[OA\Property(description: 'Thème de l\'interface', example: 'dark', nullable: true)]
        #[Assert\Choice(choices: ['light', 'dark', 'system'], groups: ['update'])]
        public ?string $theme = null,

        #[OA\Property(description: 'Langue de l\'interface', example: 'fr', nullable: true)]
        #[Assert\Choice(choices: ['fr', 'en'], groups: ['update'])]
        public ?string $locale = null,

        #[OA\Property(description: 'Format de date', example: 'DD/MM/YYYY', nullable: true)]
        #[Assert\Choice(choices: ['DD/MM/YYYY', 'MM/DD/YYYY', 'YYYY-MM-DD'], groups: ['update'])]
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
        #[Assert\Range(min: 30, max: 2555, groups: ['update'])]
        public ?int $auditLogRetentionDays = null,

        #[OA\Property(description: 'Taille de page par défaut pour les listes', example: 20, nullable: true)]
        #[Assert\Range(min: 10, max: 100, groups: ['update'])]
        public ?int $defaultPageSize = null,
    ) {
    }
}