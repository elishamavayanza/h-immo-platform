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
        public array $audienceRoles = [],
        #[Assert\Uuid] public ?string $organizationUuid = null,
        public ?string $scheduledAt = null,
        public ?string $endsAt = null,
    ) {}
}
