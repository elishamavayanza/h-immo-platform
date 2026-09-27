<?php

namespace App\Security;

enum SecurityAction: string
{
    // Général
    case VIEW = 'view';
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';

    // Organization
    case MANAGE_ORGANIZATION = 'manage_organization';
    case SUSPEND_ORGANIZATION = 'suspend_organization';
    case ACTIVATE_ORGANIZATION = 'activate_organization';
    case VIEW_ORGANIZATION = 'view_organization';

    // Utilisateurs
    case VIEW_USER = 'view_user';
    case UPDATE_USER = 'update_user';
    case DELETE_USER = 'delete_user';
    case MANAGE_USERS = 'manage_users';
    case SUSPEND_USER = 'suspend_user';
    case ACTIVATE_USER = 'activate_user';
    case ASSIGN_USER_CITY = 'assign_user_city';
    case REVOKE_USER_CITY = 'revoke_user_city';

    // City
    case VIEW_CITY = 'view_city';
    case CREATE_CITY = 'create_city';
    case UPDATE_CITY = 'update_city';
    case DELETE_CITY = 'delete_city';
    case ACTIVATE_CITY = 'activate_city';
    case DEACTIVATE_CITY = 'deactivate_city';

    // Parcel
    case VIEW_PARCEL = 'view_parcel';
    case CREATE_PARCEL = 'create_parcel';
    case UPDATE_PARCEL = 'update_parcel';
    case DELETE_PARCEL = 'delete_parcel';

    // Building
    case VIEW_BUILDING = 'view_building';
    case CREATE_BUILDING = 'create_building';
    case UPDATE_BUILDING = 'update_building';
    case DELETE_BUILDING = 'delete_building';

    // Unit
    case VIEW_UNIT = 'view_unit';
    case CREATE_UNIT = 'create_unit';
    case UPDATE_UNIT = 'update_unit';
    case DELETE_UNIT = 'delete_unit';

    // Tenant
    case VIEW_TENANT = 'view_tenant';
    case CREATE_TENANT = 'create_tenant';
    case UPDATE_TENANT = 'update_tenant';
    case DELETE_TENANT = 'delete_tenant';
    case ARCHIVE_TENANT = 'archive_tenant';

    // Lease
    case VIEW_LEASE = 'view_lease';
    case CREATE_LEASE = 'create_lease';
    case UPDATE_LEASE = 'update_lease';
    case DELETE_LEASE = 'delete_lease';
    case ACTIVATE_LEASE = 'activate_lease';
    case TERMINATE_LEASE = 'terminate_lease';
    case CANCEL_LEASE = 'cancel_lease';

    // Rent
    case VIEW_RENT = 'view_rent';
    case CREATE_RENT = 'create_rent';
    case UPDATE_RENT = 'update_rent';
    case DELETE_RENT = 'delete_rent';
    case MARK_RENT_OVERDUE = 'mark_rent_overdue';

    // Payment
    case VIEW_PAYMENT = 'view_payment';
    case CREATE_PAYMENT = 'create_payment';
    case UPDATE_PAYMENT = 'update_payment';
    case DELETE_PAYMENT = 'delete_payment';
    case CANCEL_PAYMENT = 'cancel_payment';

    // Worker (personnel)
    case VIEW_WORKER = 'view_worker';
    case CREATE_WORKER = 'create_worker';
    case UPDATE_WORKER = 'update_worker';
    case DELETE_WORKER = 'delete_worker';

    // WorkerAssignment (affectation du personnel)
    case VIEW_WORKER_ASSIGNMENT = 'view_worker_assignment';
    case CREATE_WORKER_ASSIGNMENT = 'create_worker_assignment';
    case UPDATE_WORKER_ASSIGNMENT = 'update_worker_assignment';
    case DELETE_WORKER_ASSIGNMENT = 'delete_worker_assignment';

    // Expense (dépense)
    case VIEW_EXPENSE = 'view_expense';
    case CREATE_EXPENSE = 'create_expense';
    case UPDATE_EXPENSE = 'update_expense';
    case DELETE_EXPENSE = 'delete_expense';

    // Audit
    case VIEW_AUDIT_LOG = 'view_audit_log';
    case EXPORT_AUDIT_LOG = 'export_audit_log';
}
