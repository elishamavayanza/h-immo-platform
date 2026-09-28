<?php

declare(strict_types=1);

namespace App\Dto\Response\Identity;

use App\Enum\CityStatus;
use OpenApi\Attributes as OA;

/**
 * SessionCityAccess
 *
 * Package : Identity & Access — DTO de réponse
 *
 * Ville accessible à un compte portant le rôle `ADMIN_VILLE`.
 *
 * Le statut est exposé parce qu'il conditionne l'accès réel : une ville
 * `INACTIVE` reste listée (le compte y est rattaché) mais l'API refusera
 * les opérations. Filtrer côté client évite une dépendance inutile.
 */
#[OA\Schema(
    title: 'SessionCityAccess',
    description: 'Ville accessible à un compte ADMIN_VILLE.'
)]
final readonly class SessionCityAccess
{
    public function __construct(
        #[OA\Property(description: 'UUID public de la ville', format: 'uuid', example: '1f9c2a3b-4d5e-4f60-8712-3456789abcde')]
        public string $uuid,

        #[OA\Property(description: 'Nom de la ville', example: 'Gombe')]
        public string $name,

        #[OA\Property(description: 'Province de rattachement', example: 'Kinshasa', nullable: true)]
        public ?string $province,

        #[OA\Property(description: 'Statut de la ville', type: 'string', enum: CityStatus::class, example: CityStatus::ACTIVE)]
        public CityStatus $status,
    ) {
    }
}
