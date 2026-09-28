<?php

declare(strict_types=1);

namespace App\Dto\Response\Report;

use App\Enum\Currency;
use OpenApi\Attributes as OA;

/**
 * ExpenseSummaryItem
 *
 * Résumé des dépenses par catégorie/niveau.
 */
#[OA\Schema(title: 'ExpenseSummaryItem')]
final class ExpenseSummaryItem
{
    public function __construct(
        #[OA\Property(description: 'Catégorie', example: 'MAINTENANCE')]
        public string $category,

        #[OA\Property(description: 'Niveau : city|parcel|building|unit|organization', example: 'building')]
        public string $level,

        #[OA\Property(description: 'Libellé du niveau', example: 'Immeuble Central')]
        public string $levelLabel,

        #[OA\Property(description: 'Nombre de dépenses')]
        public int $count,

        #[OA\Property(description: 'Montant total', type: 'number', format: 'decimal')]
        public string $totalAmount,

        #[OA\Property(description: 'Devise', type: 'string', example: 'USD', enum: Currency::class)]
        public Currency $currency,

        // Paramètre optionnel placed en DERNIER : un paramètre optionnel
        // suivi d'un obligatoire oblige l'appelant à fournir les deux, même
        // en arguments nommés..buildExpensesByCategory() ne fournit pas
        // levelUuid, et la construction échouait sur « Argument #3
        // not passed ». Les arguments nommés rendent ce réordonnancement
        // sans effet sur les appelants.
        #[OA\Property(description: 'UUID du niveau', format: 'uuid', nullable: true)]
        public ?string $levelUuid = null,
    ) {
    }
}