/**
 * Page Locataires de l'espace ADMIN_VILLE.
 *
 * Réutilise l'écran ADMIN_IMMOBILIER `LocatairesPage` : il est paramétré par
 * le rôle actif et gate chaque action via `canDo(...)`. Sous admin_ville,
 * l'assistant « ajouter un locataire (sans bail) → créer son bail → activer
 * le bail » (décision D-V1) est identique, tandis que la suppression physique
 * (`delete_tenant`) reste hors périmètre : l'archive est la seule sortie.
 */
export { LocatairesPage } from '../../../admin_immobilier/locataires/pages/LocatairesPage';
