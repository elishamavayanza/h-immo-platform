export interface ReportMetric {
    id: string;
    label: string;
    value: string;
    helper: string;
    tone: 'primary' | 'success' | 'warning' | 'info';
}

export interface ReportPeriodPoint {
    label: string;
    revenue: string;
    expenses: string;
    revenueShare: number;
    expenseShare: number;
}

export interface ReportBreakdownItem {
    id: string;
    label: string;
    detail: string;
    amount: string;
    share: number;
}

export interface ReportAttentionItem {
    id: string;
    label: string;
    detail: string;
    amount: string;
    status: string;
}

export interface ReportsData {
    title: string;
    description: string;
    scope: string;
    periodLabel: string;
    currency: string;
    metrics: ReportMetric[];
    trend: ReportPeriodPoint[];
    breakdownTitle: string;
    breakdown: ReportBreakdownItem[];
    attentionTitle: string;
    attention: ReportAttentionItem[];
}

export type ReportsPeriod = 'month' | 'quarter' | 'year';
