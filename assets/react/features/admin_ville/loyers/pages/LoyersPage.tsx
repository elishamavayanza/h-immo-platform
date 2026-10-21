/**
 * Page Loyers de l'espace ADMIN_VILLE.
 *
 * Réutilise l'écran ADMIN_IMMOBILIER `LoyersPage` : chaque action (créer un
 * paiement ou modifier une échéance) est gatée
 * par `canDo(...)`. Sous admin_ville, `update_payment` est absent : l'écran
 * n'expose que créer/annuler un paiement, et le backend applique la même
 * matrice.
 */
export { LoyersPage } from '../../../admin_immobilier/loyers/pages/LoyersPage';
