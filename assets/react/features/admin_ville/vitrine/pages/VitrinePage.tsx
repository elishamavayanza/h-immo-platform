/**
 * Page Vitrine de l'espace ADMIN_VILLE.
 *
 * Réutilise l'écran ADMIN_IMMOBILIER `VitrinePage` : la liste reprend les
 * unités et chaque action (`publish_listing`, `update_unit`) est gatée par
 * `canDo(...)`, que le backend recoupe via `SecurityService`.
 */
export { VitrinePage } from '../../../admin_immobilier/vitrine/pages/VitrinePage';
