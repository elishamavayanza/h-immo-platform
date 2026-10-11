/**
 * Page Dépenses de l'espace ADMIN_VILLE.
 *
 * Réutilise l'écran ADMIN_IMMOBILIER `DepensesPage` : les actions sont gatées
 * par `canDo(...)`. Sous admin_ville, `create_expense` et `update_expense`
 * sont accordés, mais `delete_expense` — dont dépend l'annulation — ne l'est
 * pas (décision D-V2 / constat B-V3) : le bouton d'annulation est donc masqué
 * et le backend refuserait l'appel.
 */
export { DepensesPage } from '../../../admin_immobilier/depenses/pages/DepensesPage';
