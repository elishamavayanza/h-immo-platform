import type { SessionUserResponse } from '../../../../../../services/api/api.types';

export type Profile = Pick<SessionUserResponse, 'uuid' | 'email' | 'fullName' | 'phone' | 'profilePhoto' | 'platformRole' | 'roles' | 'organizations' | 'cities' | 'isActive' | 'lastLoginAt'>;

export interface ProfileUpdatePayload {
    firstName?: string;
    lastName?: string;
    phone?: string;
    profilePhoto?: string;
    email?: string;
}