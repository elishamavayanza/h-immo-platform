import { Card } from '../../../../components/UI/Card';
import { formatInteger, formatMoney, formatPercent } from '../../../../../utils/format.utils';
import type { PatronDashboardData } from '../types/patronDashboard.types';
import { UpcomingLeaseExpirations } from '../../../shared/components/UpcomingLeaseExpirations';
import { DashboardStatChart } from '../../../shared/components/DashboardStatChart';
import { absoluteCents, amountBarPercent } from '../../../shared/reports/utils/amountChart';
import { OccupancyDonut } from '../../../../components/Data/OccupancyDonut';

/** Vue d'ensemble du Patron : indicateurs + occupation par ville + impayés + dépenses. */
export function PatronOverview({ data }: { data: PatronDashboardData }) {
    const occupied = data.cities.reduce((total, item) => total + item.occupiedUnits, 0);
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

            <section className="organization-dashboard-grid organization-dashboard-grid--single">
                <UpcomingLeaseExpirations items={data.upcomingLeaseExpirations} />
            </section>

            <section className="organization-dashboard-insights-grid">
                <div className="organization-dashboard-insights-column">
                <Card
                    className="organization-panel"
                    padding="medium"
                    header={<div><h2>Occupation du patrimoine</h2><p>{formatInteger(data.totalUnits)} unités sur le périmètre</p></div>}
                >
                    <div className="organization-collection">
                        <OccupancyDonut occupied={occupied} total={data.totalUnits} label="Patrimoine de l’organisation" />
                        <small>Devise de référence : {data.currency} · {data.periodCovered.replace(' to ', ' → ')}</small>
                    </div>
                </Card>

                <DashboardStatChart
                    title="Occupation par ville"
                    description="Part des unités occupées dans chaque ville."
                    emptyMessage="Aucune ville à afficher."
                    items={data.cities.map((item) => ({
                        id: item.levelUuid ?? item.label,
                        label: item.label,
                        detail: `${formatInteger(item.occupiedUnits)} / ${formatInteger(item.totalUnits)} unités occupées`,
                        value: formatPercent(item.occupancyRate),
                        share: item.occupancyRate,
                        occupied: item.occupiedUnits,
                        total: item.totalUnits,
                    }))}
                />
                </div>

                <div className="organization-dashboard-insights-column">
                <Card
                    className="organization-panel"
                    padding="medium"
                    header={<div><h2>Impayés à recouvrer</h2><p>Échéances en retard par bail</p></div>}
                >
                    <ul className="organization-activity" aria-label="Impayés à recouvrer : liste déroulante" tabIndex={0}>
                        {data.arrears.length > 0 ? data.arrears.map((item, index) => (
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
                                    <strong>Aucun impayé à traiter</strong>
                                    <small>Toutes les échéances à jour</small>
                                </div>
                                <time>—</time>
                            </li>
                        )}
                    </ul>
                </Card>

                <DashboardStatChart
                    title="Dépenses par ville"
                    description={`Répartition sur la période · ${data.currency}`}
                    emptyMessage="Aucune dépense dans la devise de référence sur cette période."
                    items={expenses.map((item) => ({
                        id: `${item.levelUuid ?? item.levelLabel}-${item.category}`,
                        label: item.levelLabel,
                        detail: item.category === 'ALL' ? 'Toutes catégories' : item.category,
                        value: formatMoney(item.totalAmount, item.currency),
                        share: amountBarPercent(item.totalAmount, maximumExpense),
                    }))}
                />
                </div>
            </section>
        </>
    );
}
