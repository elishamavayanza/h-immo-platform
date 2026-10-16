<?php

declare(strict_types=1);

namespace App\Dto\Response\Property;

use App\Entity\Property\AccessPoint;
use OpenApi\Attributes as OA;

#[OA\Schema(title: 'AccessPointResponse', description: 'Accès physique, distinct des locaux et éventuellement partagé.')]
final readonly class AccessPointResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid')] public string $id,
        #[OA\Property(format: 'uuid')] public string $parcelId,
        #[OA\Property(format: 'uuid', nullable: true)] public ?string $buildingId,
        public string $reference,
        public string $label,
        public string $type,
        public string $accessMode,
        public ?string $locationDescription,
        public bool $enabled,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'string', format: 'uuid'))] public array $unitIds,
    ) {
    }

    public static function fromEntity(AccessPoint $access): self
    {
        return new self((string) $access->getUuid(), (string) $access->getParcel()->getUuid(), $access->getBuilding() !== null ? (string) $access->getBuilding()->getUuid() : null, $access->getReference(), $access->getLabel(), $access->getType(), $access->getAccessMode(), $access->getLocationDescription(), $access->isEnabled(), array_values(array_map(static fn ($unit): string => (string) $unit->getUuid(), $access->getUnits()->toArray())));
    }
}
