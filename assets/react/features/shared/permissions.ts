import type { OrganizationRole } from '../../../services/api/api.types';

/**
 * permissions.ts — Miroir front de la matrice d'autorisation backend.
 *
 * Cette table reproduit `SecurityService::checkPatronAction()`,
 * `checkAdminImmobilierAction()` et `checkAdminVilleAction()` : elle sert
 * UNIQUEMENT à décider de l'affichage des boutons. Le frontend n'est pas une
 * frontière de sécurité — l'API reste l'autorité et refuse toute action non
 * autorisée, quelle que soit la condition React. Toute évolution de la matrice
 * backend doit être répercutée ici, sinon l'UI montrerait un bouton que l'API
 * rejette (403) ou en cacherait un d'autorisé.
 */
export type SecurityAction =
    | 'view' | 'create' | 'update' | 'delete'
    | 'manage_organization' | 'manage_showcase' | 'suspend_organization' | 'activate_organization' | 'view_organization' | 'manage_organization_logo' | 'manage_exchange_rate'
    | 'view_user' | 'update_user' | 'delete_user' | 'manage_users' | 'create_city_admin' | 'suspend_user' | 'activate_user' | 'assign_user_city' | 'revoke_user_city'
    | 'view_city' | 'create_city' | 'update_city' | 'delete_city' | 'activate_city' | 'deactivate_city'
    | 'view_parcel' | 'create_parcel' | 'update_parcel' | 'delete_parcel'
    | 'view_building' | 'create_building' | 'update_building' | 'delete_building'
    | 'view_unit' | 'create_unit' | 'update_unit' | 'delete_unit'
    | 'publish_listing' | 'view_listing' | 'create_listing' | 'update_listing' | 'submit_listing' | 'review_listing' | 'manage_listing_media'
    | 'view_customer_inquiry' | 'manage_customer_inquiry' | 'manage_customer_visit'
    | 'view_tenant' | 'create_tenant' | 'update_tenant' | 'delete_tenant' | 'archive_tenant'
    | 'view_lease' | 'create_lease' | 'update_lease' | 'delete_lease' | 'activate_lease' | 'terminate_lease' | 'cancel_lease'
    | 'view_rent' | 'create_rent' | 'update_rent' | 'delete_rent' | 'mark_rent_overdue'
    | 'view_payment' | 'create_payment' | 'update_payment' | 'delete_payment' | 'cancel_payment'
    | 'view_document' | 'create_document' | 'delete_document'
    | 'view_worker' | 'create_worker' | 'update_worker' | 'delete_worker'
    | 'view_worker_assignment' | 'create_worker_assignment' | 'update_worker_assignment' | 'delete_worker_assignment'
    | 'view_expense' | 'create_expense' | 'update_expense' | 'delete_expense' | 'manage_treasury_balance'
    | 'view_report' | 'export_report'
    | 'view_audit_log' | 'export_audit_log'
    | 'view_maintenance' | 'create_maintenance' | 'update_maintenance';

const PATRON_ACTIONS: ReadonlyArray<SecurityAction> = [
    'view', 'view_organization', 'manage_showcase', 'view_user', 'manage_organization_logo', 'manage_exchange_rate',
    'view_city', 'view_parcel', 'view_building', 'view_unit', 'view_listing', 'view_customer_inquiry', 'view_tenant', 'view_lease',
    'view_rent', 'view_payment', 'view_worker', 'view_worker_assignment', 'view_expense',
    'view_maintenance', 'view_document', 'view_report', 'export_report', 'view_audit_log', 'export_audit_log',
];

const ADMIN_IMMOBILIER_ACTIONS: ReadonlyArray<SecurityAction> = [
    'view', 'view_organization',
    'manage_organization_logo', 'manage_exchange_rate', 'create_city_admin', 'assign_user_city', 'revoke_user_city',
    'view_city', 'create_city', 'update_city', 'delete_city', 'activate_city', 'deactivate_city',
    'view_parcel', 'create_parcel', 'update_parcel', 'delete_parcel',
    'view_building', 'create_building', 'update_building', 'delete_building',
    'view_unit', 'create_unit', 'update_unit', 'delete_unit',
    'view_listing', 'review_listing', 'publish_listing', 'manage_listing_media', 'view_customer_inquiry', 'manage_customer_inquiry', 'manage_customer_visit',
    'view_tenant', 'view_lease', 'view_rent', 'view_payment',
    'view_worker', 'create_worker', 'update_worker', 'delete_worker', 'view_worker_assignment', 'view_expense', 'view_document',
    'view_report', 'export_report',
    'view_audit_log', 'export_audit_log',
    'view_maintenance',
];

const ADMIN_VILLE_ACTIONS: ReadonlyArray<SecurityAction> = [
    'view', 'view_organization', 'view_city',
    'view_parcel', 'create_parcel', 'update_parcel', 'delete_parcel',
    'view_building', 'create_building', 'update_building', 'delete_building',
    'view_unit', 'create_unit', 'update_unit', 'delete_unit',
    'view_listing', 'create_listing', 'update_listing', 'submit_listing', 'manage_listing_media', 'view_customer_inquiry', 'manage_customer_visit',
    'view_tenant', 'create_tenant', 'update_tenant', 'archive_tenant',
    'view_lease', 'create_lease', 'update_lease', 'activate_lease', 'terminate_lease', 'cancel_lease',
    'view_rent', 'create_rent', 'update_rent', 'mark_rent_overdue',
    'view_payment', 'create_payment', 'cancel_payment',
    'view_worker',
    'view_worker_assignment', 'create_worker_assignment', 'update_worker_assignment', 'delete_worker_assignment',
    'view_expense', 'create_expense', 'update_expense', 'create_document', 'manage_treasury_balance',
    'view_report', 'export_report',
    'view_maintenance', 'create_maintenance', 'update_maintenance',
];

const ROLE_ACTIONS: Record<OrganizationRole, 'all' | ReadonlySet<SecurityAction>> = {
    patron: new Set(PATRON_ACTIONS),
    admin_immobilier: new Set(ADMIN_IMMOBILIER_ACTIONS),
    admin_ville: new Set(ADMIN_VILLE_ACTIONS),
};

/** Vrai si le rôle peut exécuter l'action donnée sur l'organization active. */
export function canDo(role: OrganizationRole | null | undefined, action: SecurityAction): boolean {
    if (!role) return false;
    const allowed = ROLE_ACTIONS[role];

    return allowed === 'all' || allowed.has(action);
}

/** Vrai si le rôle peut exécuter AU MOINS une des actions données. */
export function canDoAny(role: OrganizationRole | null | undefined, actions: ReadonlyArray<SecurityAction>): boolean {
    return actions.some((action) => canDo(role, action));
}
