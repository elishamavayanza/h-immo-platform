<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Currency
 *
 * Devise utilisée pour les montants (loyers, dépôts, paiements)
 * dans le contexte de la RDC : Dollar américain et Franc congolais.
 */
enum Currency: string
{
    case USD = 'USD';
    case CDF = 'CDF';
}
