<?php

declare(strict_types=1);

namespace App\Dto\Request\System;

use Symfony\Component\Validator\Constraints as Assert;

final class PrivateDocumentFilterDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $organizationUuid = '',
        #[Assert\Uuid]
        public ?string $cityUuid = null,
        #[Assert\Choice(choices: ['lease', 'payment', 'expense', 'parcel', 'building', 'unit'])]
        public ?string $relatedType = null,
        #[Assert\Uuid]
        public ?string $relatedUuid = null,
    ) {
    }
}
