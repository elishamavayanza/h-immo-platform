<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * ExpenseCategory
 *
 * Nature d'une dépense enregistrée dans le patrimoine.
 *
 * La catégorie est la seule information nécessaire pour agréger les
 * dépenses par nature. `OTHER` reste disponible pour les cas non prévus,
 * la libre description restant portée par le champ `notes`.
 */
enum ExpenseCategory: string
{
    /** Salaire, prime ou indemnité versée à un travailleur affecté. */
    case SALARY = 'salary';

    /** Taxe foncière, taxe municipale, taxe d'habitation ou autre impôt. */
    case TAX = 'tax';

    /** Travaux de maintenance, réparation ou rénovation. */
    case MAINTENANCE = 'maintenance';

    /** Eau, électricité, gaz ou autre charge courante. */
    case UTILITY = 'utility';

    /** Prime d'assurance. */
    case INSURANCE = 'insurance';

    /** Frais de gestion, commission ou honoraires de gestion locative. */
    case MANAGEMENT_FEE = 'management_fee';

    /** Produit et matériel d'entretien. */
    case SUPPLY = 'supply';

    /** Prestation de nettoyage. */
    case CLEANING = 'cleaning';

    /** Frais de gardiennage ou de sécurité. */
    case SECURITY = 'security';

    /** Redevance d'un service public : voirie, ordures ménagères, assainissement. */
    case PUBLIC_SERVICE = 'public_service';

    /** Droit de mutation, frais de notaire ou frais d'acquisition. */
    case NOTARY_FEE = 'notary_fee';

    /** Dépense non prévue par l'énumération. */
    case OTHER = 'other';
}
