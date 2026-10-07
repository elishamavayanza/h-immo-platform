import type { OrganizationStatus } from '../../dashbord/types';

export interface OrganizationRow {
    id: string;
    name: string;
    code: string;
    city: string;
    plan: string;
    members: number;
    properties: number;
    status: OrganizationStatus;
    createdAt: string;
}

export type OrganizationStatusFilter = OrganizationStatus | 'all';
