import { Card } from '../../../../components/UI/Card';
import { formatInteger, formatMoney, formatPercent } from '../../../../../utils/format.utils';
import type { AdminImmobilierDashboardData } from '../types/adminImmobilierDashboard.types';

/** Vue d'ensemble : indicateurs + occupation du patrimoine + impayés à traiter. */
export function AdminImmobilierOverview({ data }: { data: AdminImmobilierDashboardData }) {
    const occupied = data.buildings.reduce((total, item) => total + item.occupiedUnits, 0);

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
                <Card
                    className="organization-panel"
                    padding="medium"
                    header={<div><h2>Occupation par immeuble</h2><p>Répartition actuelle du patrimoine occupé</p></div>}
                >
                    <ul className="organization-activity">
                        {data.buildings.length > 0 ? data.buildings.map((item) => (
                            <li key={item.levelUuid ?? item.label} className="organization-activity__item organization-activity__item--info">
                                <span className="organization-activity__dot" />
                                <div>
                                    <strong>{item.label}</strong>
                                    <small>{item.occupiedUnits} / {item.totalUnits} unités occupées</small>
                                </div>
                                <time>{formatPercent(item.occupancyRate)}</time>
                            </li>
                        )) : (
                            <li className="organization-activity__item">
                                <span className="organization-activity__dot" />
                                <div>
                                    <strong>Aucun immeuble</strong>
                                    <small>Le patrimoine est vide sur ce périmètre</small>
                                </div>
                                <time>—</time>
                            </li>
                        )}
                    </ul>
                </Card>

                <Card
                    className="organization-panel"
                    padding="medium"
                    header={<div><h2>Dépenses liées aux biens</h2><p>Par niveau du patrimoine sur la période</p></div>}
                >
                    <ul className="organization-activity">
                        {data.expenses.length > 0 ? data.expenses.map((item) => (
                            <li key={`${item.level}-${item.levelLabel}`} className="organization-activity__item organization-activity__item--info">
                                <span className="organization-activity__dot" />
                                <div>
                                    <strong>{item.levelLabel}</strong>
                                    <small>{item.category === 'ALL' ? 'Toutes catégories' : item.category}</small>
                                </div>
                                <time>{formatMoney(item.totalAmount, item.currency)}</time>
                            </li>
                        )) : (
                            <li className="organization-activity__item">
                                <span className="organization-activity__dot" />
                                <div>
                                    <strong>Aucune dépense</strong>
                                    <small>Rien à afficher sur la période sélectionnée</small>
                                </div>
                                <time>—</time>
                            </li>
                        )}
                    </ul>
                </Card>
            </section>
        </>
    );
}