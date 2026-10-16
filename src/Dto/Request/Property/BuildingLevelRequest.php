<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(title: 'BuildingLevelRequest', description: 'Niveau ordonné et décrit d’un bâtiment.')]
final readonly class BuildingLevelRequest
{
    public function __construct(
        #[Assert\NotBlank] #[Assert\Length(max: 50)] public string $reference,
        #[Assert\NotBlank] #[Assert\Length(max: 120)] public string $label,
        public int $displayOrder,
        #[Assert\Length(max: 2000)] public ?string $description = null,
    ) {}
}
