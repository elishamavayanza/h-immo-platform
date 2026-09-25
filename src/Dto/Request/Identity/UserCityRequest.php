<?php

declare(strict_types=1);

namespace App\Dto\Request\Identity;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * UserCityRequest
 *
 * Package : Identity & Access — DTO de requête
 *
 * Données entrantes pour attribuer une ville à un utilisateur
 * ADMIN_VILLE. Association immuable : uniquement le groupe 'create'.
 */
#[OA\Schema(
    title: 'UserCityRequest',
    description: 'Payload pour assigner la gestion d\'une ville spécifique à un administrateur local.'
)]
final readonly class UserCityRequest
{
    public function __construct(
        #[OA\Property(
            description: 'UUID public de l\'utilisateur (Admin Ville)',
            format: 'uuid',
            example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110'
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Uuid(groups: ['create'])]
        public ?string $userUuid = null,

        #[OA\Property(
            description: 'UUID public de la ville à attribuer',
            format: 'uuid',
            example: 'f81d4fae-7dec-11d0-a765-00a0c91e6bf6'
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Uuid(groups: ['create'])]
        public ?string $cityUuid = null,
    ) {
    }
}
