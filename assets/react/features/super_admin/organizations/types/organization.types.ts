import type { OrganizationStatus } from '../../dashbord/types';

export interface OrganizationRow {
    id: string;
    name: string;
    code: string;
    email: string;
    phone: string;
    address: string;
    city: string;
    status: OrganizationStatus;
    createdAt: string;
}

export type OrganizationStatusFilter = OrganizationStatus | 'all';
