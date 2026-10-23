<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/** Contenu contrôlé d'une annonce de la vitrine. */
#[OA\Schema(title: 'ListingDraftRequest')]
final readonly class ListingDraftRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 180)]
        #[OA\Property(example: 'Appartement lumineux à Goma')]
        public string $title,
        #[Assert\NotBlank]
        #[Assert\Length(max: 5000)]
        #[OA\Property(example: 'Appartement proche des commerces et transports.')]
        public string $description,
        #[Assert\Choice(choices: ['rent', 'sale'])]
        #[OA\Property(enum: ['rent', 'sale'])]
        public string $offerType,
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^(?:0|[1-9]\d{0,9})(?:\.\d{1,2})?$/')]
        #[Assert\GreaterThan(value: 0)]
        #[OA\Property(type: 'string', format: 'decimal', example: '450.00')]
        public string $price,
        #[Assert\Choice(choices: ['USD', 'CDF'])]
        #[OA\Property(enum: ['USD', 'CDF'])]
        public string $currency,
    ) {
    }
}
