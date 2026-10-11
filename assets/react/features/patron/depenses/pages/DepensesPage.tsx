import { DepensesPage as AdminImmobilierDepensesPage } from '../../../admin_immobilier/depenses/pages/DepensesPage';

/**
 * Page Dépenses de l'espace PATRON.
 *
 * Réutilise l'écran ADMIN_IMMOBILIER `DepensesPage` avec `readOnly` : le
 * Patron consulte les coûts par bien et par ville, sans enregistrer ni
 * annuler de dépense (décision D-P2).
 */
export function DepensesPage() {
    return <AdminImmobilierDepensesPage readOnly />;
}
