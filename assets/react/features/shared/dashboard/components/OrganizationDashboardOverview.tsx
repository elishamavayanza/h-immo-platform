import { formatInteger, formatMoney } from '../../../../../utils/format.utils';
import { Card } from '../../../../components/UI/Card';
import { OccupancyDonut } from '../../../../components/Data/OccupancyDonut';
import { ChartDisplayToggle } from '../../../../components/Data/ChartDisplayToggle';
import { UpcomingLeaseExpirations } from '../../components/UpcomingLeaseExpirations';
import { DashboardStatChart } from '../../components/DashboardStatChart';
import type { LeaseExpiryItem } from '../../../admin_immobilier/reports/types';

interface DashboardMetric {
    id: string;
    label: string;
    value: string;
    detail: string;
    tone: 'primary' | 'success' | 'warning' | 'info';
}

interface DashboardArrearsItem {
    leaseUuid: string;
    tenantName: string;
    unitLabel: string;
    leaseReference: string;
    arrears: string;
    daysOverdue: number;
    currency: string;
}

interface DashboardChartItem {
    id: string;
    label: string;
    detail: string;
    value: string;
    share: number;
    occupied?: number;
    total?: number;
}

interface OrganizationDashboardOverviewProps {
    metrics: DashboardMetric[];
    upcomingLeaseExpirations: LeaseExpiryItem[];
    occupancy: {
        title: string;
        summary: string;
        donutLabel: string;
        occupied: number;
        total: number;
        footer: string;
    };
    occupancyChart: {
        title: string;
        description: string;
        emptyMessage: string;
        items: DashboardChartItem[];
    };
    arrears: {
        title: string;
        emptyLabel: string;
        items: DashboardArrearsItem[];
    };
    expenses: {
        title: string;
        description: string;
        emptyMessage: string;
        items: DashboardChartItem[];
    };
}

/** Trame visuelle commune des tableaux de bord organisationnels. */
export function OrganizationDashboardOverview({
    metrics,
    upcomingLeaseExpirations,
    occupancy,
    occupancyChart,
    arrears,
    expenses,
}: OrganizationDashboardOverviewProps) {
    return (
        <>
            <section className="organization-kpis">
                {metrics.map((metric) => (
                    <Card key={metric.id} className={`organization-kpi organization-kpi--${metric.tone}`} padding="medium">
                        <span>{metric.label}</span>
                        <strong>{metric.value}</strong>
                        <small>{metric.detail}</small>
                    </Card>
                ))}
            </section>

            <section className="organization-dashboard-grid organization-dashboard-grid--single">
                <UpcomingLeaseExpirations items={upcomingLeaseExpirations} />
            </section>

            <section className="organization-dashboard-insights-grid">
                <div className="organization-dashboard-insights-column">
                    <Card
                        className="organization-panel"
                        padding="medium"
                        header={<div className="organization-panel__chart-heading"><div><h2>{occupancy.title}</h2><p>{occupancy.summary}</p></div><ChartDisplayToggle /></div>}
                    >
                        <div className="organization-collection">
                            <OccupancyDonut occupied={occupancy.occupied} total={occupancy.total} label={occupancy.donutLabel} />
                            <small>{occupancy.footer}</small>
                        </div>
                    </Card>

                    <DashboardStatChart
                        title={occupancyChart.title}
                        description={occupancyChart.description}
                        emptyMessage={occupancyChart.emptyMessage}
                        items={occupancyChart.items}
                    />
                </div>

                <div className="organization-dashboard-insights-column">
                    <Card
                        className="organization-panel"
                        padding="medium"
                        header={<div><h2>{arrears.title}</h2><p>Échéances en retard par bail</p></div>}
                    >
                        <ul className="organization-activity" aria-label={`${arrears.title} : liste déroulante`} tabIndex={0}>
                            {arrears.items.length > 0 ? arrears.items.map((item, index) => (
                                <li key={`${item.leaseUuid}-${index}`} className="organization-activity__item organization-activity__item--warning">
                                    <span className="organization-activity__dot" />
                                    <div>
                                        <strong>{item.tenantName}</strong>
                                        <small>{item.unitLabel} · {item.leaseReference} · {formatMoney(item.arrears, item.currency)} en retard</small>
                                    </div>
                                    <time>{item.daysOverdue > 0 ? `${formatInteger(item.daysOverdue)} j` : 'Récent'}</time>
                                </li>
                            )) : (
                                <li className="organization-activity__item organization-activity__item--success">
                                    <span className="organization-activity__dot" />
                                    <div>
                                        <strong>Aucun impayé {arrears.emptyLabel}</strong>
                                        <small>Toutes les échéances à jour</small>
                                    </div>
                                    <time>—</time>
                                </li>
                            )}
                        </ul>
                    </Card>

                    <DashboardStatChart
                        title={expenses.title}
                        description={expenses.description}
                        emptyMessage={expenses.emptyMessage}
                        items={expenses.items}
                    />
                </div>
            </section>
        </>
    );
}
