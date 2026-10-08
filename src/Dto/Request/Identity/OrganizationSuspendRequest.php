<?php

declare(strict_types=1);

namespace App\Dto\Request\Identity;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * OrganizationSuspendRequest
 *
 * Package : Identity & Access — DTO de requête
 *
 * Payload de suspension d'une Organization. Le motif est obligatoire : il
 * est envoyé aux membres par email et conservé dans le journal d'audit. Ce
 * n'est pas un simple changement de statut sans trace : suspendre un tenant
 * coupe l'accès de toute l'équipe, la raison doit donc être explicite.
 */
#[OA\Schema(
    title: 'OrganizationSuspendRequest',
    description: 'Motif obligatoire pour suspendre une organisation (envoyé aux membres par email et audité).'
)]
final readonly class OrganizationSuspendRequest
{
    public function __construct(
        #[OA\Property(
            description: 'Motif de la suspension, communiqué aux membres de l\'organisation et archivé dans l\'audit',
            example: 'Non-conformité contractuelle : impayés depuis 3 mois.',
            maxLength: 1000
        )]
        #[Assert\NotBlank(message: 'Le motif de suspension est obligatoire.')]
        #[Assert\Length(max: 1000, maxMessage: 'Le motif ne peut pas dépasser {{ limit }} caractères.')]
        public string $reason = ''
    ) {
    }
}