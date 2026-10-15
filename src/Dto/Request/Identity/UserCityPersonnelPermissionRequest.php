<?php

declare(strict_types=1);

namespace App\Dto\Request\Identity;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(title: 'UserCityPersonnelPermissionRequest')]
final readonly class UserCityPersonnelPermissionRequest
{
    public function __construct(
        #[OA\Property(description: 'Autoriser la création de personnel dans cette ville')]
        #[Assert\NotNull]
        public ?bool $canManagePersonnel = null,
    ) {
    }
}
