<?php

declare(strict_types=1);

namespace App\Dto\Request\Identity;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * UserSuspendRequest
 *
 * Package : Identity & Access — DTO de requête
 *
 * Motif de suspension d'un compte utilisateur. À la différence de la
 * suspension d'organisation (motif obligatoire), le motif d'un compte est
 * facultatif : renseigné, il est joint à l'email de notification et archivé
 * dans l'audit ; absent, seule la désactivation est tracée.
 */
final readonly class UserSuspendRequest
{
    public function __construct(
        #[OA\Property(
            description: 'Motif de la suspension, joint à l\'email de notification et archivé dans l\'audit (facultatif)',
            example: 'Compte dormant depuis plusieurs mois.',
            nullable: true,
            maxLength: 1000
        )]
        #[Assert\Length(max: 1000, maxMessage: 'Le motif ne peut pas dépasser {{ limit }} caractères.')]
        public ?string $reason = null
    ) {
    }
}