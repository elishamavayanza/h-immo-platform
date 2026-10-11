/**
 * Page Patrimoine de l'espace ADMIN_VILLE.
 *
 * Réutilise l'écran ADMIN_IMMOBILIER `PatrimoinePage` (stratégie retenue
 * pour V2) : il est déjà paramétré par le rôle actif
 * (`useOrganization().organizationRole`) et gate chaque action via
 * `canDo(...)`, miroir front de `SecurityService`. Sous le rôle admin_ville,
 * l'écran ne propose donc que les actions autorisées — villes en lecture,
 * parcelles/bâtiments/unités en CRUD + publication + photos — et le backend
 * borne de toute façon chaque requête aux villes affectées (`UserCity`).
 */
export { PatrimoinePage } from '../../../admin_immobilier/patrimoine/pages/PatrimoinePage';
