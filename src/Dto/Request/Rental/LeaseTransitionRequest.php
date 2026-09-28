<?php

declare(strict_types=1);

namespace App\Dto\Request\Rental;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * LeaseTransitionRequest
 *
 * Package : Rental Management — DTO de requête
 *
 * Payload commun aux transitions de bail qui portent une justification
 * (résiliation, annulation). L'activation n'en a pas besoin et se
 * déclenche sans corps.
 *
 * Le motif est facultatif côté validation mais conservé tel qu'il a été
 * saisi quand il est fourni : il est la trace de pourquoi un bail a
 * cessé, information qu'aucune autre donnée ne restitue ensuite.
 */
#[OA\Schema(
    title: 'LeaseTransitionRequest',
    description: 'Justification d\'une transition de bail (résiliation, annulation).'
)]
final readonly class LeaseTransitionRequest
{
    public function __construct(
        #[OA\Property(
            description: 'Motif de la transition, conservé comme trace dans le dossier du bail',
            example: 'Rupture anticipée à la demande du locataire',
            nullable: true,
            maxLength: 255
        )]
        #[Assert\Length(max: 255)]
        public ?string $reason = null,
    ) {
    }
}
