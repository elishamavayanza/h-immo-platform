<?php

declare(strict_types=1);

namespace App\Dto\Request\Auth;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'ResetPasswordRequest',
    description: 'Réinitialisation du mot de passe avec le jeton reçu par email.'
)]
final readonly class ResetPasswordRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le jeton de réinitialisation est requis.')]
        #[Assert\Length(min: 64, max: 64, exactMessage: 'Le jeton doit faire 64 caractères.')]
        #[OA\Property(description: 'Jeton de réinitialisation reçu par email (64 caractères hexadécimaux)', example: '9f8c2b1a7d6e5f4c3b2a1908f7e6d5c4b3a29180f7e6d5c4b3a29180f7e6d5c4')]
        public string $token,

        #[Assert\NotBlank(message: 'Le nouveau mot de passe est requis.')]
        #[Assert\Length(min: 8, minMessage: 'Le mot de passe doit faire au moins 8 caractères.')]
        #[OA\Property(description: 'Nouveau mot de passe (min. 8 caractères)', example: 'NouveauMotDePasse123!')]
        public string $newPassword,
    ) {
    }
}