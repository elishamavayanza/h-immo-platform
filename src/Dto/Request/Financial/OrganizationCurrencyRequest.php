<?php

declare(strict_types=1);

namespace App\Dto\Request\Financial;

use App\Enum\Currency;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class OrganizationCurrencyRequest
{
    public function __construct(
        #[Assert\NotNull]
        public ?Currency $currency = null,
    ) {
    }
}
