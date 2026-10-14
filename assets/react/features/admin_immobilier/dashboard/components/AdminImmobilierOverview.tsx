import { Card } from '../../../../components/UI/Card';
import { formatInteger, formatMoney, formatPercent } from '../../../../../utils/format.utils';
import type { AdminImmobilierDashboardData } from '../types/adminImmobilierDashboard.types';
import { UpcomingLeaseExpirations } from '../../../shared/components/UpcomingLeaseExpirations';
import { DashboardStatChart } from '../../../shared/components/DashboardStatChart';
import { absoluteCents, amountBarPercent } from '../../../shared/reports/utils/amountChart';

/** Vue d'ensemble : indicateurs + occupation du patrimoine + impayés à traiter. */
export function AdminImmobilierOverview({ data }: { data: AdminImmobilierDashboardData }) {
    const occupied = data.buildings.reduce((total, item) => total + item.occupiedUnits, 0);
    const expenses = data.expenses.filter((item) => item.currency === data.currency);
    const maximumExpense = expenses.reduce((maximum, item) => {
        const amount = absoluteCents(item.totalAmount);
        return amount > maximum ? amount : maximum;
    }, 0n);

    return (
        <>
            <section className="organization-kpis">
                {data.metrics.map((metric) => (
                    <Card key={metric.id} className={`organization-kpi organization-kpi--${metric.tone}`} padding="medium">
                        <span>{metric.label}</span>
                        <strong>{metric.value}</strong>
                        <small>{metric.detail}</small>
                    </Card>
                ))}
            </section>

            <section className="organization-dashboard-grid">
                <UpcomingLeaseExpirations items={data.upcomingLeaseExpirations} />
            </section>

            <section className="organization-dashboard-grid">
                <Card
                    className="organization-panel"
                    padding="medium"
                    header={<div><h2>Occupation du patrimoine</h2><p>{formatInteger(occupied)} unités occupées sur {formatInteger(data.totalUnits)}</p></div>}
                >
                    <div className="organization-collection">
                        <strong>{formatPercent(data.globalOccupancyRate)}</strong>
                        <span>des unités sont occupées</span>
                        <div className="organization-progress"><span style={{ width: `${Math.min(Math.max(data.globalOccupancyRate, 0), 100)}%` }} /></div>
                        <small>Devise de référence : {data.currency} · {data.periodCovered.replace(' to ', ' → ')}</small>
                    </div>
                </Card>

                <Card
                    className="organization-panel"
                    padding="medium"
                    header={<div><h2>Impayés à traiter</h2><p>Échéances en retard par bail</p></div>}
                >
                    <ul className="organization-activity">
                        {data.arrears.length > 0 ? data.arrears.map((item) => (
                            <li key={item.leaseUuid} className="organization-activity__item organization-activity__item--warning">
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
                                    <strong>Aucun impayé à traiter</strong>
                                    <small>Toutes les échéances à jour</small>
                                </div>
                                <time>—</time>
                            </li>
                        )}
                    </ul>
                </Card>
            </section>

            <section className="organization-dashboard-grid">
                <DashboardStatChart
                    title="Occupation par immeuble"
                    description="Part des unités occupées sur le périmètre immobilier."
                    emptyMessage="Aucun immeuble à afficher."
                    items={data.buildings.map((item) => ({
                        id: item.levelUuid ?? item.label,
                        label: item.label,
                        detail: `${formatInteger(item.occupiedUnits)} / ${formatInteger(item.totalUnits)} unités occupées`,
                        value: formatPercent(item.occupancyRate),
                        share: item.occupancyRate,
                    }))}
                />

                <DashboardStatChart
                    title="Dépenses liées aux biens"
                    description={`Répartition par niveau · ${data.currency}`}
                    emptyMessage="Aucune dépense dans la devise de référence sur cette période."
                    items={expenses.map((item) => ({
                        id: item.levelUuid ?? `${item.level}-${item.levelLabel}-${item.category}`,
                        label: item.levelLabel,
                        detail: item.category === 'ALL' ? 'Toutes catégories' : item.category,
                        value: formatMoney(item.totalAmount, item.currency),
                        share: amountBarPercent(item.totalAmount, maximumExpense),
                    }))}
                />
            </section>
        </>
    );
}
