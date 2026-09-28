<?php

declare(strict_types=1);

namespace App\Dto\Response\Report;

use App\Enum\Currency;
use OpenApi\Attributes as OA;

/**
 * FinancialSummaryItem
 *
 * Résumé financier par période (mois/année).
 */
#[OA\Schema(title: 'FinancialSummaryItem')]
final class FinancialSummaryItem
{
    public function __construct(
        #[OA\Property(description: 'Période (YYYY-MM)', example: '2026-01')]
        public string $period,

        #[OA\Property(description: 'Revenus (loyers encaissés)', type: 'number', format: 'decimal')]
        public string $revenues,

        #[OA\Property(description: 'Dépenses', type: 'number', format: 'decimal')]
        public string $expenses,

        #[OA\Property(description: 'Loyers attendus (somme des échéances générées)', type: 'number', format: 'decimal')]
        public string $expected,

        #[OA\Property(description: 'Résultat net (revenus - dépenses)', type: 'number', format: 'decimal')]
        public string $netResult,

        #[OA\Property(description: 'Devise', type: 'string', example: 'USD', enum: Currency::class)]
        public Currency $currency,
    ) {
    }
}