<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PublicOrganizationSlugFilterQuery
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 100,
    ) {
    }
}
