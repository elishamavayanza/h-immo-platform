<?php

declare(strict_types=1);

namespace App\Dto\Request\System;

use Symfony\Component\Validator\Constraints as Assert;

final class PublicationDraftDto
{
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 180)] public string $title = '',
        #[Assert\NotBlank, Assert\Length(max: 20000)] public string $content = '',
        #[Assert\Choice(choices: ['feature', 'improvement', 'correction', 'maintenance', 'important'])] public string $category = 'feature',
        #[Assert\Choice(choices: ['all', 'roles', 'organization'])] public string $audience = 'all',
        #[Assert\Count(max: 3)]
        #[Assert\All([new Assert\Choice(choices: ['patron', 'admin_immobilier', 'admin_ville'])])]
        public array $audienceRoles = [],
        #[Assert\Uuid] public ?string $organizationUuid = null,
        #[Assert\DateTime]
        public ?string $scheduledAt = null,
        #[Assert\DateTime]
        public ?string $endsAt = null,
    ) {}
}
