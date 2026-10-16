<?php

declare(strict_types=1);

namespace App\Dto\Response\Property;

use App\Entity\Property\BuildingLevel;
use OpenApi\Attributes as OA;

#[OA\Schema(title: 'BuildingLevelResponse', description: 'Niveau physique déclaré dans un bâtiment.')]
final readonly class BuildingLevelResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid')] public string $id,
        #[OA\Property(format: 'uuid')] public string $buildingId,
        public string $reference,
        public string $label,
        public int $displayOrder,
        public ?string $description,
    ) {}

    public static function fromEntity(BuildingLevel $level): self
    {
        return new self((string) $level->getUuid(), (string) $level->getBuilding()->getUuid(), $level->getReference(), $level->getLabel(), $level->getDisplayOrder(), $level->getDescription());
    }
}
