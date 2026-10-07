import type { OrganizationRole } from '../../../../../services/api/api.types';

export type UserStatus = 'active' | 'invited' | 'suspended';

export interface UserRow {
    id: string;
    name: string;
    email: string;
    organization: string;
    role: OrganizationRole | 'super_admin';
    status: UserStatus;
    lastActivity: string;
}
