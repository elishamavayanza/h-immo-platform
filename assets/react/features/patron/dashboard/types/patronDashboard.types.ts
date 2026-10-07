export interface PatronMetric { id: string; label: string; value: string; detail: string; tone: 'primary' | 'success' | 'warning' | 'info'; }
export interface PatronActivity { id: string; title: string; detail: string; date: string; status: 'success' | 'info' | 'warning'; }
export interface PatronDashboardData { metrics: PatronMetric[]; activities: PatronActivity[]; occupancy: number; rentCollected: string; }
