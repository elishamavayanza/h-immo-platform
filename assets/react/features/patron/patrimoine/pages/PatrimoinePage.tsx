import { PatrimoinePage as AdminImmobilierPatrimoinePage } from '../../../admin_immobilier/patrimoine/pages/PatrimoinePage';

/**
 * Page Patrimoine de l'espace PATRON.
 *
 * Le Patron consulte son patrimoine sans l'opérer : la page réutilise l'écran
 * ADMIN_IMMOBILIER `PatrimoinePage` avec `readOnly`, qui masque toute écriture
 * (création, modification, suppression, publication, photos) au niveau des
 * onglets Villes / Parcelles / Bâtiments / Unités, tout en conservant la
 * navigation en lecture et les liens associés (Locataires, Loyers, Dépenses).
 *
 * Décision D-P2 (`docs/decisions-patron.md`) : la lecture seule est une règle
 * d'interface (`readOnly`), jamais un durcissement backend — le Patron reste
 * propriétaire de son organisation.
 */
export function PatrimoinePage() {
    return <AdminImmobilierPatrimoinePage readOnly />;
}
