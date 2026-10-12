<?php

declare(strict_types=1);

namespace App\Dto\Request\Financial;

use App\Enum\Currency;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ExchangeRateRequest
{
    public function __construct(
        #[Assert\NotNull]
        public ?Currency $baseCurrency = null,
        #[Assert\NotNull]
        public ?Currency $quoteCurrency = null,
        #[Assert\NotBlank]
        #[Assert\Regex('/^\d{1,10}(\.\d{1,8})?$/')]
        public ?string $rate = null,
    ) {
    }
}
