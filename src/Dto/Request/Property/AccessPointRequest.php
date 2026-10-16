<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(title: 'AccessPointRequest', description: 'Accès bâtiment pouvant desservir plusieurs unités.')]
final readonly class AccessPointRequest
{
    public function __construct(
        #[Assert\NotBlank] #[Assert\Length(max: 50)] public string $reference,
        #[Assert\NotBlank] #[Assert\Length(max: 120)] public string $label,
        #[Assert\Choice(choices: ['main_entry', 'private_entry', 'shared_entry', 'rear', 'service', 'staircase', 'parking', 'technical', 'other'])]
        public string $type,
        #[Assert\Choice(choices: ['private', 'shared', 'restricted'])]
        public string $accessMode = 'shared',
        #[Assert\Length(max: 255)] public ?string $locationDescription = null,
        #[Assert\All([new Assert\Uuid()])] public array $unitUuids = [],
        #[Assert\Uuid] public ?string $buildingUuid = null,
        #[Assert\Uuid] public ?string $parcelUuid = null,
    ) {
    }
}
