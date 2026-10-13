<?php

declare(strict_types=1);

namespace App\Dto\Request\Identity;

use App\Enum\OrganizationRole;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(description: 'Affecter un rôle supplémentaire à un compte déjà membre de l’organisation.')]
final readonly class AssignExistingRoleRequest
{
    /** @param list<string> $cityUuids */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $organizationUuid,
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $userUuid,
        #[Assert\NotNull]
        public OrganizationRole $role,
        #[Assert\All([new Assert\Uuid])]
        public array $cityUuids = [],
    ) {
    }
}
