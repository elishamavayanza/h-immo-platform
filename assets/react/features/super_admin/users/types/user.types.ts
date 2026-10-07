import type { OrganizationRole } from '../../../../../services/api/api.types';

export type UserStatus = 'active' | 'inactive';

export interface UserRow {
    id: string;
    name: string;
    email: string;
    phone: string | null;
    organization: string;
    organizationUuid: string | null;
    organizationUserUuid: string | null;
    role: OrganizationRole | 'super_admin';
    status: UserStatus;
    lastLoginAt: string | null;
}
