import { useCallback, useEffect, useMemo, useState } from 'react';

import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { ApiError } from '../../../../../services/api/api.types';
import { auditService } from '../services/auditService';
import { organizationsService } from '../../organizations/services/organizationsService';
import type { AuditEntry, AuditListParams, AuditListResponse } from '../types/audit.types';
import type { OrganizationRow } from '../../organizations/types/organization.types';
import { readAuditLogDisplayDays } from '../../../../services/userPreferences';

function errorMessage(cause: unknown): string {
    return cause instanceof ApiError ? cause.message : 'Une erreur inattendue est survenue. Réessayez.';
}

export function useAuditLog() {
    const { push } = useToast();

    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [data, setData] = useState<AuditListResponse>({ items: [], total: 0, page: 1, pages: 1 });
    const [selectedEntry, setSelectedEntry] = useState<AuditEntry | null>(null);

    const [organizationOptions, setOrganizationOptions] = useState<OrganizationRow[]>([]);
    const [orgLoading, setOrgLoading] = useState(true);

    const [filters, setFilters] = useState<AuditListParams>({
        page: 1,
        itemsPerPage: 20,
        organizationUuid: undefined,
        action: undefined,
        entityType: undefined,
        from: new Date(Date.now() - readAuditLogDisplayDays() * 24 * 60 * 60 * 1000),
        to: undefined,
    });

    const reload = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await auditService.list(filters);
            setData(response);
        } catch (cause) {
            setError(errorMessage(cause));
        } finally {
            setLoading(false);
        }
    }, [filters]);

    useEffect(() => {
        void reload();
    }, [reload]);

    const loadOrganizations = useCallback(async () => {
        setOrgLoading(true);
        try {
            const orgs = await organizationsService.list({ limit: 1000 });
            setOrganizationOptions(orgs);
        } catch {
            // Ignore, just won't have org names
        } finally {
            setOrgLoading(false);
        }
    }, []);

    useEffect(() => {
        loadOrganizations();
    }, [loadOrganizations]);

    const handleFilterChange = useCallback((key: keyof AuditListParams, value: AuditListParams[keyof AuditListParams]) => {
        setFilters((prev) => {
            const next = { ...prev, [key]: value, page: 1 };
            return next;
        });
    }, []);

    const handlePageChange = useCallback((page: number) => {
        setFilters((prev) => ({ ...prev, page }));
    }, []);

    const handleSort = useCallback(() => {
        // Backend sorts by createdAt DESC only; no-op
    }, []);

    const selectEntry = useCallback((entry: AuditEntry) => {
        setSelectedEntry(entry);
    }, []);

    const clearSelection = useCallback(() => {
        setSelectedEntry(null);
    }, []);

    const actionOptions = useMemo(() => [
        { value: '', label: 'Toutes les actions' },
        ...auditService.getActions().map((action) => ({ value: action, label: action.replace(/_/g, ' ') })),
    ], []);

    const orgOptions = useMemo(() => [
        { value: '', label: 'Toutes les organisations (plateforme)' },
        ...organizationOptions.map((org) => ({ value: org.id, label: org.name })),
    ], [organizationOptions]);

    return {
        entries: data.items,
        total: data.total,
        page: data.page,
        pages: data.pages,
        loading,
        error,
        filters,
        selectedEntry,
        actionOptions,
        orgOptions,
        orgLoading,
        reload,
        handleFilterChange,
        handlePageChange,
        handleSort,
        selectEntry,
        clearSelection,
        organizationOptions,
    };
}
