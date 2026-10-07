export interface AdminImmobilierMetric { id: string; label: string; value: string; detail: string; tone: 'primary' | 'success' | 'warning' | 'info'; }
export interface AdminImmobilierDashboardData { metrics: AdminImmobilierMetric[]; operations: Array<{ id: string; title: string; detail: string; time: string; status: 'success' | 'warning' | 'info' }>; collectionRate: string; openIssues: number; }
