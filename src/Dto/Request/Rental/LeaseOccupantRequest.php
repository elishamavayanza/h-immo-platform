<?php

declare(strict_types=1);

namespace App\Dto\Request\Rental;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(title: 'LeaseOccupantRequest', description: 'Ajout historique d’un cotitulaire ou occupant à un bail.')]
final readonly class LeaseOccupantRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $tenantUuid,
        #[Assert\Choice(choices: ['co_tenant', 'occupant'])]
        public string $role,
        #[Assert\NotBlank]
        #[Assert\Date]
        public string $startDate,
        #[Assert\Date]
        public ?string $endDate = null,
    ) {
    }
}
