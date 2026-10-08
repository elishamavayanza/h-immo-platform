import { apiClient } from '../../../../../services/api/client';
import type { AuditEntry, AuditListParams, AuditListResponse } from '../types/audit.types';

const ACTIONS = [
    'CREATE_ADMIN', 'CREATE_ORGANIZATION', 'CREATE_LEASE', 'CREATE_RENT', 'CREATE_PAYMENT',
    'CREATE_EXPENSE', 'CREATE_WORKER', 'CREATE_WORKER_ASSIGNMENT',
    'UPDATE', 'UPDATE_LEASE', 'UPDATE_RENT', 'UPDATE_EXPENSE', 'UPDATE_WORKER',
    'ACTIVATE_LEASE', 'ACTIVATE_ORGANIZATION',
    'SUSPEND_USER', 'SUSPEND_ORGANIZATION',
    'TERMINATE_LEASE', 'CANCEL_LEASE', 'CANCEL_PAYMENT', 'CANCEL_EXPENSE',
    'RESET_PASSWORD', 'FORGOT_PASSWORD',
    'LOGIN', 'LOGOUT', 'LOGIN_FAILED',
] as const;

export const auditService = {
    async list(params: AuditListParams = {}): Promise<AuditListResponse> {
        const query: Record<string, string | number> = {
            page: params.page ?? 1,
            itemsPerPage: params.itemsPerPage ?? 20,
            ...(params.organizationUuid ? { organizationUuid: params.organizationUuid } : {}),
            ...(params.action ? { action: params.action } : {}),
            ...(params.entityType ? { entityType: params.entityType } : {}),
            ...(params.from ? { from: params.from.toISOString() } : {}),
            ...(params.to ? { to: params.to.toISOString() } : {}),
        };
        const { data } = await apiClient.get<AuditListResponse>('/v1/system/audit-logs', { params: query });
        return data;
    },

    async get(uuid: string): Promise<AuditEntry> {
        const { data } = await apiClient.get<AuditEntry>(`/v1/system/audit-logs/${uuid}`);
        return data;
    },

    getActions(): string[] {
        return [...ACTIONS];
    },
};