import { Card } from '../../../../components/UI/Card';
import { Badge } from '../../../../components/UI/Badge';
import { InlineSummary } from '../../../shared/components/InlineSummary';
import { formatInteger, formatMoney, formatPercent } from '../../../../../utils/format.utils';
import type { AdminImmobilierReportsData, ArrearsItem, ExpenseSummaryItem, OccupancyItem } from '../types';

/** Libellé court d'un mois en français : le backend fournit `YYYY-MM`. */
function monthLabel(point: OccupancyItem): string {
    const value = point.levelUuid;
    if (value && /^\d{4}-\d{2}$/.test(value)) {
        const [year, month] = value.split('-').map(Number);
        return new Date(year, month - 1, 1).toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' });
    }

    return point.label;
}

/** Ligne d'occupation d'un immeuble : taux en pourcentage + barre de progression. */
function BuildingRow({ item }: { item: OccupancyItem }) {
    return (
        <div className="reports-breakdown__item" key={item.levelUuid ?? item.label}>
            <div className="reports-breakdown__copy">
                <strong>{item.label}</strong>
                <small>{item.occupiedUnits} / {item.totalUnits} unités occupées</small>
            </div>
            <span className="reports-breakdown__amount">{formatPercent(item.occupancyRate)}</span>
            <progress value={item.occupancyRate} max={100} aria-label={`${item.label} : ${formatPercent(item.occupancyRate)}`} />
        </div>
    );
}

/** Ligne d'impayé : locataire, unité, montant et jours de retard. */
function ArrearsRow({ item }: { item: ArrearsItem }) {
    const overdue = item.daysOverdue > 0;

    return (
        <li key={item.leaseUuid}>
            <div>
                <strong>{item.tenantName}</strong>
                <small>{item.unitLabel} · {item.leaseReference} · dû {formatMoney(item.amountDue, item.currency)}</small>
            </div>
            <span className="reports-attention__amount">{formatMoney(item.arrears, item.currency)}</span>
            <Badge variant={overdue ? 'warning' : 'info'} size="small">
                {overdue ? `${formatInteger(item.daysOverdue)} j de retard` : 'À recouvrer'}
            </Badge>
        </li>
    );
}

/** Ligne de dépense agrégée par niveau (ville), montant déjà formaté. */
function ExpenseRow({ item }: { item: ExpenseSummaryItem }) {
    return (
        <div className="reports-breakdown__item" key={`${item.level}-${item.levelLabel}`}>
            <div className="reports-breakdown__copy">
                <strong>{item.levelLabel}</strong>
                <small>{item.category === 'ALL' ? 'Toutes catégories' : item.category}</small>
            </div>
            <span className="reports-breakdown__amount">{formatMoney(item.totalAmount, item.currency)}</span>
        </div>
    );
}

/** Corps du rapport, affiché sous l'en-tête de la page. */
export function ReportsOverview({ data }: { data: AdminImmobilierReportsData }) {
    return (
        <div className="reports-page__content">
            <InlineSummary items={data.metrics.map((metric) => ({ label: metric.label, value: metric.value, detail: metric.helper }))} />

            <section className="reports-lower-grid">
                <Card className="reports-panel reports-breakdown" padding="medium">
                    <div className="reports-panel__heading">
                        <div><h2>Occupation par immeuble</h2><p>Taux d’occupation actuel du patrimoine.</p></div>
                    </div>
                    <div className="reports-breakdown__list">
                        {data.buildings.length > 0
                            ? data.buildings.map((item) => <BuildingRow key={item.levelUuid ?? item.label} item={item} />)
                            : <p className="reports-empty">Aucun immeuble à afficher.</p>}
                    </div>
                </Card>

                <Card className="reports-panel reports-attention" padding="medium">
                    <div className="reports-panel__heading">
                        <div><h2>Impayés à recouvrer</h2><p>Échéances en retard par bail.</p></div>
                    </div>
                    <ul className="reports-attention__list">
                        {data.arrears.length > 0
                            ? data.arrears.map((item) => <ArrearsRow key={item.leaseUuid} item={item} />)
                            : <li className="reports-empty">Aucun impayé à signaler.</li>}
                    </ul>
                </Card>
            </section>

            <section className="reports-lower-grid">
                <Card className="reports-panel" padding="medium">
                    <div className="reports-panel__heading">
                        <div><h2>Évolution de l’occupation</h2><p>Taux d’occupation des 6 derniers mois · {data.currency}</p></div>
                    </div>
                    <div className="reports-chart" role="img" aria-label="Évolution du taux d’occupation sur les 6 derniers mois">
                        {data.evolution.map((point) => (
                            <div className="reports-chart__group" key={point.levelUuid ?? point.label}>
                                <div className="reports-chart__bars">
                                    <div
                                        className="reports-chart__bar reports-chart__bar--revenue"
                                        style={{ height: `${Math.max(point.occupancyRate, 2)}%` }}
                                        title={`${monthLabel(point)} : ${formatPercent(point.occupancyRate)}`}
                                    />
                                </div>
                                <span>{monthLabel(point)}</span>
                            </div>
                        ))}
                    </div>
                    <div className="reports-chart__footnote">
                        <span>Période : {data.periodCovered.replace(' to ', ' → ')}</span>
                        <span>Source : rapport immobilier de l’organisation.</span>
                    </div>
                </Card>

                <Card className="reports-panel reports-breakdown" padding="medium">
                    <div className="reports-panel__heading">
                        <div><h2>Dépenses liées aux biens</h2><p>Par niveau du patrimoine sur la période.</p></div>
                    </div>
                    <div className="reports-breakdown__list">
                        {data.expenses.length > 0
                            ? data.expenses.map((item) => <ExpenseRow key={`${item.level}-${item.levelLabel}`} item={item} />)
                            : <p className="reports-empty">Aucune dépense enregistrée sur la période.</p>}
                    </div>
                </Card>
            </section>
        </div>
    );
}