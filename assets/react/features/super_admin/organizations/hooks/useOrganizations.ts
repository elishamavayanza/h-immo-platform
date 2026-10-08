import { useCallback, useEffect, useMemo, useState } from 'react';

import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { ApiError } from '../../../../../services/api/api.types';
import { organizationsService } from '../services/organizationsService';
import type {
    OrganizationCreatePayload,
    OrganizationCreateResult,
    OrganizationRow,
    OrganizationStatusFilter,
    OrganizationTransitionResult,
    OrganizationUpdatePayload,
} from '../types/organization.types';

function actionErrorMessage(cause: unknown): string {
    return cause instanceof ApiError ? cause.message : 'Une erreur inattendue est survenue. Réessayez.';
}

export function useOrganizations() {
    const { push } = useToast();
    const [organizations, setOrganizations] = useState<OrganizationRow[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState<OrganizationStatusFilter>('all');

    const reload = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const rows = await organizationsService.list();
            setOrganizations(rows);
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger les organisations.');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => { void reload(); }, [reload]);

    const filteredOrganizations = useMemo(() => organizations.filter((organization) => {
        const query = search.trim().toLocaleLowerCase('fr');
        const matchesSearch = !query || [organization.name, organization.code, organization.city].some((value) => value.toLocaleLowerCase('fr').includes(query));
        return matchesSearch && (statusFilter === 'all' || organization.status === statusFilter);
    }), [organizations, search, statusFilter]);

    const addOrganization = async (payload: OrganizationCreatePayload): Promise<OrganizationCreateResult> => {
        try {
            const result = await organizationsService.create(payload);
            let row = result.organization;

            // Le logo est uploadé après la création atomique (le tenant doit
            // exister pour le recevoir). Un échec n'annule pas la création :
            // il devient un avertissement, la persistance reste le verdict.
            if (payload.logoFile) {
                try {
                    const url = await organizationsService.uploadLogo(row.id, payload.logoFile);
                    row = { ...row, logo: url };
                } catch {
                    push('warning', 'L’organisation a été créée, mais le logo n’a pas pu être envoyé.');
                }
            }

            result.organization = row;
            setOrganizations((current) => [row, ...current]);
            push('success', result.flushDescription ?? 'L’organisation et son PATRON ont été créés.');
            if (result.warnings.patronEmail) {
                push('warning', result.warnings.patronEmail);
            }
            return result;
        } catch (cause) {
            push('error', actionErrorMessage(cause));
            throw cause;
        }
    };

    const updateOrganization = async (id: string, changes: OrganizationUpdatePayload): Promise<OrganizationRow> => {
        try {
            const updated = await organizationsService.update(id, changes);
            let row = updated;

            if (changes.removeLogo) {
                try {
                    await organizationsService.deleteLogo(id);
                    row = { ...updated, logo: null };
                } catch {
                    push('warning', 'Les informations ont été mises à jour, mais le logo n’a pas pu être supprimé.');
                }
            } else if (changes.logoFile) {
                try {
                    const url = await organizationsService.uploadLogo(id, changes.logoFile);
                    row = { ...updated, logo: url };
                } catch {
                    push('warning', 'Les informations ont été mises à jour, mais le logo n’a pas pu être envoyé.');
                }
            }

            setOrganizations((current) => current.map((organization) => organization.id === id ? row : organization));
            push('success', 'L’organisation a été mise à jour.');
            return row;
        } catch (cause) {
            push('error', actionErrorMessage(cause));
            throw cause;
        }
    };

    const deleteOrganization = async (id: string): Promise<void> => {
        try {
            await organizationsService.remove(id);
            setOrganizations((current) => current.filter((organization) => organization.id !== id));
            push('success', 'L’organisation a été supprimée.');
        } catch (cause) {
            push('error', actionErrorMessage(cause));
            throw cause;
        }
    };

    const suspendOrganization = async (id: string, reason: string): Promise<OrganizationTransitionResult> => {
        try {
            const result = await organizationsService.suspend(id, reason);
            setOrganizations((current) => current.map((organization) => organization.id === id ? result.organization : organization));
            push('success', result.flushDescription ?? 'L’organisation a été suspendue.');
            if (result.warnings.emails) {
                push('warning', result.warnings.emails);
            }
            return result;
        } catch (cause) {
            push('error', actionErrorMessage(cause));
            throw cause;
        }
    };

    const activateOrganization = async (id: string): Promise<OrganizationTransitionResult> => {
        try {
            const result = await organizationsService.reactivate(id);
            setOrganizations((current) => current.map((organization) => organization.id === id ? result.organization : organization));
            push('success', result.flushDescription ?? 'L’organisation a été réactivée.');
            return result;
        } catch (cause) {
            push('error', actionErrorMessage(cause));
            throw cause;
        }
    };

    return { organizations, filteredOrganizations, loading, error, reload, search, setSearch, statusFilter, setStatusFilter, addOrganization, updateOrganization, deleteOrganization, suspendOrganization, activateOrganization };
}