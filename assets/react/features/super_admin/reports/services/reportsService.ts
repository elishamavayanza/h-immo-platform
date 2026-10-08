import { apiClient } from '../../../../../services/api/client';
import { tokenStorage } from '../../../../../services/storage/storage.service';
import type {
    SuperAdminReportResponse,
    OrganizationSummaryItem,
} from '../types/superAdminReport.types';

interface SuperAdminReportFilters {
    periodFrom?: string;
    periodTo?: string;
    format?: 'json' | 'pdf';
}

export async function fetchSuperAdminReport(filters: SuperAdminReportFilters = {}): Promise<SuperAdminReportResponse> {
    const query: Record<string, string> = {
        ...(filters.periodFrom ? { periodFrom: filters.periodFrom } : {}),
        ...(filters.periodTo ? { periodTo: filters.periodTo } : {}),
    };
    const { data } = await apiClient.get<SuperAdminReportResponse>('/v1/reports/super-admin', { params: query });
    return data;
}

export async function downloadSuperAdminReportPdf(filters: SuperAdminReportFilters = {}): Promise<void> {
    const accessToken = tokenStorage.getAccessToken();
    if (!accessToken) {
        throw new Error('Non authentifié : jeton d\'accès manquant');
    }

    const query: Record<string, string> = {
        format: 'pdf',
        ...(filters.periodFrom ? { periodFrom: filters.periodFrom } : {}),
        ...(filters.periodTo ? { periodTo: filters.periodTo } : {}),
    };
    const qs = new URLSearchParams(query).toString();
    const url = `/api/v1/reports/super-admin?${qs}`;

    const response = await fetch(url, {
        method: 'GET',
        headers: {
            'Accept': 'application/pdf',
            'X-Requested-With': 'XMLHttpRequest',
            'Authorization': `Bearer ${accessToken}`,
        },
    });

    if (!response.ok) {
        throw new Error(`Échec du téléchargement (${response.status})`);
    }

    const blob = await response.blob();
    const urlBlob = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = urlBlob;
    link.download = `rapport-super-admin-${new Date().toISOString().split('T')[0]}.pdf`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(urlBlob);
}

export function mapToReportsData(response: SuperAdminReportResponse): import('../../../shared/reports/types/report.types').ReportsData {
    const orgSummaries = response.organizations as OrganizationSummaryItem[];

    const metrics = [
        {
            id: 'orgs',
            label: 'Organisations',
            value: `${response.totalOrganizations}`,
            helper: `Actives: ${response.activeOrganizations}`,
            tone: 'primary' as const,
        },
        {
            id: 'users',
            label: 'Utilisateurs plateforme',
            value: `${response.totalUsers}`,
            helper: 'Tous rôles confondus',
            tone: 'info' as const,
        },
    ];

    const breakdown = orgSummaries.map((org) => ({
        id: org.uuid,
        label: org.name,
        detail: `${org.cityCount} villes · ${org.unitCount} unités`,
        amount: `${org.occupancyRate} %`,
        share: org.occupancyRate,
    }));

    const attention = orgSummaries
        .filter((org) => org.status === 'suspended' || org.status === 'inactive' || (org.arrears && parseFloat(org.arrears) > 0))
        .map((org) => ({
            id: org.uuid,
            label: org.status === 'suspended' ? 'Organisation suspendue' : org.status === 'inactive' ? 'Organisation inactive' : 'Impayés détectés',
            detail: org.name,
            amount: org.arrears ? `${org.arrears} $` : '—',
            status: org.status === 'suspended' ? 'Action requise' : org.status === 'inactive' ? 'À réactiver' : 'À recouvrer',
        }));

    return {
        title: 'Rapport de la plateforme',
        description: 'Suivez la croissance et la santé globale des organisations H-Immo.',
        scope: 'Ensemble de la plateforme',
        periodLabel: response.periodCovered,
        currency: 'USD',
        metrics,
        trend: [],
        breakdownTitle: 'Organisations par activité',
        breakdown,
        attentionTitle: 'Organisations à surveiller',
        attention,
    };
}