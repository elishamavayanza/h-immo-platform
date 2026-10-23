<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/** Motif obligatoire des demandes de correction, rejets et retraits. */
#[OA\Schema(title: 'ListingDecisionRequest')]
final readonly class ListingDecisionRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 1000)]
        #[OA\Property(example: 'Merci de compléter la description et d’ajouter une photo de façade.')]
        public string $reason,
    ) {
    }
}
