import { LoyersPage as AdminImmobilierLoyersPage } from '../../../admin_immobilier/loyers/pages/LoyersPage';

/**
 * Page Loyers de l'espace PATRON.
 *
 * Réutilise l'écran ADMIN_IMMOBILIER `LoyersPage` avec `readOnly` : le Patron
 * suit les échéances, les paiements et les retards, sans enregistrer de
 * paiement ni modifier une échéance (décision D-P2).
 */
export function LoyersPage() {
    return <AdminImmobilierLoyersPage readOnly />;
}
