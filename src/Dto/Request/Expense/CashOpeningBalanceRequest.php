<?php

declare(strict_types=1);

namespace App\Dto\Request\Expense;

use App\Enum\Currency;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CashOpeningBalanceRequest
{
    public function __construct(
        #[Assert\Choice(choices: ['USD', 'CDF'])]
        public string $currency,
        #[Assert\Regex('/^\d{1,10}(?:\.\d{1,2})?$/', message: 'Saisissez un montant positif avec deux décimales au maximum.')]
        public string $openingBalance,
        #[Assert\Date]
        public string $openingDate,
    ) {
    }
}
