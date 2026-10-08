/** Types alignés sur SuperAdminReportResponse du backend. */
export interface OrganizationSummaryItem {
    uuid: string;
    name: string;
    code: string;
    status: string;
    cityCount: number;
    unitCount: number;
    occupancyRate: number;
    revenues: string;
    expenses: string;
    arrears: string;
}

export interface SuperAdminReportResponse {
    periodCovered: string;
    generatedAt: string;
    organizations: OrganizationSummaryItem[];
    totalOrganizations: number;
    activeOrganizations: number;
    totalUsers: number;
}