<?php

declare(strict_types=1);

namespace App\Dto\Request\Auth;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(title: 'RefreshTokenRequest', description: 'Rotation du jeton de renouvellement de session.')]
final readonly class RefreshTokenRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le jeton de renouvellement est requis.')]
        #[Assert\Length(max: 4096)]
        #[OA\Property(description: 'Jeton de renouvellement à usage unique.')]
        public string $refreshToken,
    ) {
    }
}
