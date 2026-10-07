import type { SessionUserResponse } from '../../../../../../services/api/api.types';

export type Profile = Pick<SessionUserResponse, 'uuid' | 'email' | 'fullName' | 'phone' | 'profilePhoto' | 'platformRole' | 'roles' | 'organizations' | 'cities' | 'isActive' | 'lastLoginAt'>;
