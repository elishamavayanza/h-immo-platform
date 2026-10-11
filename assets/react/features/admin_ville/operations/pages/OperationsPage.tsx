import { PersonnelPage } from '../../../admin_immobilier/personnel/pages/PersonnelPage';

/**
 * Opérations ADMIN_VILLE : suivi du personnel et gestion des affectations
 * dans les seules villes attribuées. Les actions Worker restent filtrées
 * par la matrice de permissions partagée et par le backend.
 */
export function OperationsPage() {
    return <PersonnelPage />;
}
