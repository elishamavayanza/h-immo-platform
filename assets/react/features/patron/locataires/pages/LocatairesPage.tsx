import { LocatairesPage as AdminImmobilierLocatairesPage } from '../../../admin_immobilier/locataires/pages/LocatairesPage';

/**
 * Page Locataires de l'espace PATRON.
 *
 * Réutilise l'écran ADMIN_IMMOBILIER `LocatairesPage` avec `readOnly` : le
 * Patron consulte les occupants, leur bien et l'état de leur bail (actif,
 * brouillon, ou sans bail) sans pouvoir créer, modifier, archiver ni activer
 * un bail (décision D-P2).
 */
export function LocatairesPage() {
    return <AdminImmobilierLocatairesPage readOnly />;
}
