<?php

declare(strict_types=1);

namespace App\Dto\Request\Auth;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'ForgotPasswordRequest',
    description: 'Demande de réinitialisation de mot de passe (mot de passe oublié).'
)]
final readonly class ForgotPasswordRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'L\'adresse email est requise.')]
        #[Assert\Email(message: 'L\'adresse email n\'est pas valide.')]
        #[OA\Property(description: 'Adresse email du compte à réinitialiser', example: 'user@example.com')]
        public string $email,
    ) {
    }
}