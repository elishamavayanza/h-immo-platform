import { Card } from '../../../../components/UI/Card';
import { Badge } from '../../../../components/UI/Badge';
import { InlineSummary } from '../../../shared/components/InlineSummary';
import { UpcomingLeaseExpirations } from '../../../shared/components/UpcomingLeaseExpirations';
import { formatInteger, formatMoney } from '../../../../../utils/format.utils';
import { absoluteCents, amountBarPercent } from '../../../shared/reports/utils/amountChart';
import type { ArrearsItem, ExpenseSummaryItem, FinancialSummaryItem, OccupancyItem, PatronReportsData } from '../types';
import { OccupancyDonut } from '../../../../components/Data/OccupancyDonut';
import { ChartDisplayToggle } from '../../../../components/Data/ChartDisplayToggle';

/** Libellé court d'un mois `YYYY-MM` fourni par le backend. */
function periodLabel(period: string): string {
    if (/^\d{4}-\d{2}$/.test(period)) {
        const [year, month] = period.split('-').map(Number);
        return new Date(year, month - 1, 1).toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' });
    }

    return period;
}

/** Ligne d'occupation d'une ville : taux et compteurs occupés/vacants. */
function OccupancyRow({ item }: { item: OccupancyItem }) {
    return (
        <div className="reports-breakdown__item">
            <div className="reports-breakdown__copy">
                <strong>{item.label}</strong>
                <small>{item.occupiedUnits} / {item.totalUnits} unités occupées</small>
            </div>
            <OccupancyDonut size="small" occupied={item.occupiedUnits} total={item.totalUnits} label={item.label} />
        </div>
    );
}

/** Ligne d'impayé : locataire, unité, montant, retard. */
function ArrearsRow({ item }: { item: ArrearsItem }) {
    const overdue = item.daysOverdue > 0;

    return (
        <li>
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

/** Ligne de dépense agrégée (par catégorie ou par ville). */
function ExpenseRow({ item }: { item: ExpenseSummaryItem }) {
    return (
        <div className="reports-breakdown__item">
            <div className="reports-breakdown__copy">
                <strong>{item.category === 'ALL' ? item.levelLabel : item.category}</strong>
                <small>{formatInteger(item.count)} écriture(s)</small>
            </div>
            <span className="reports-breakdown__amount">{formatMoney(item.totalAmount, item.currency)}</span>
        </div>
    );
}

/** Ligne du résumé financier : revenus / dépenses / résultat net d'un mois. */
function FinancialTrend({ items, currency }: { items: FinancialSummaryItem[]; currency: string }) {
    const points = items.slice(-12);
    const maximum = points.reduce((largest, item) => {
        const revenues = absoluteCents(item.revenues);
        const expenses = absoluteCents(item.expenses);
        const largestPoint = revenues > expenses ? revenues : expenses;

        return largestPoint > largest ? largestPoint : largest;
    }, 0n);

    if (points.length === 0) return <p className="reports-empty">Aucune activité financière sur la période.</p>;

    return (
        <>
            <div className="reports-legend" aria-hidden="true">
                <span><i className="reports-legend__revenue" /> Revenus encaissés</span>
                <span><i className="reports-legend__expenses" /> Dépenses</span>
            </div>
            <div
                className="reports-chart reports-chart--financial"
                role="img"
                aria-label={`Revenus encaissés et dépenses mensuels en ${currency}, sur ${points.length} périodes`}
                style={{ gridTemplateColumns: `repeat(${points.length}, minmax(0, 1fr))` }}
            >
                {points.map((item) => (
                    <div className="reports-chart__group" key={item.period}>
                        <div className="reports-chart__bars">
                            <div
                                className="reports-chart__bar reports-chart__bar--revenue"
                                style={{ height: `${Math.max(2, amountBarPercent(item.revenues, maximum))}%` }}
                                title={`${periodLabel(item.period)} · Revenus : ${formatMoney(item.revenues, item.currency)}`}
                            />
                            <div
                                className="reports-chart__bar reports-chart__bar--expenses"
                                style={{ height: `${Math.max(2, amountBarPercent(item.expenses, maximum))}%` }}
                                title={`${periodLabel(item.period)} · Dépenses : ${formatMoney(item.expenses, item.currency)}`}
                            />
                        </div>
                        <span>{periodLabel(item.period)}</span>
                    </div>
                ))}
            </div>
            <div className="reports-chart__footnote">
                <span>Montants convertis en {currency} · valeurs mensuelles réelles.</span>
                {items.length > points.length && <span>12 périodes au plus sont affichées.</span>}
            </div>
        </>
    );
}

/** Corps du rapport Patron, affiché sous l'en-tête de la page. */
export function ReportsOverview({ data }: { data: PatronReportsData }) {
    return (
        <div className="reports-page__content">
            <InlineSummary items={data.metrics.map((metric) => ({ label: metric.label, value: metric.value, detail: metric.helper }))} />
            <UpcomingLeaseExpirations items={data.upcomingLeaseExpirations} />

            <div className="reports-insights-grid">
                <div className="reports-insights-column">
                <Card className="reports-panel reports-breakdown reports-breakdown--occupancy" padding="medium">
                    <div className="reports-panel__heading">
                        <div><h2>Occupation par ville</h2><p>Taux d’occupation du patrimoine par ville.</p></div>
                        <ChartDisplayToggle />
                    </div>
                    <div className="reports-breakdown__list" role="region" aria-label="Taux d’occupation par ville" tabIndex={0}>
                        {data.cities.length > 0
                            ? data.cities.map((item) => <OccupancyRow key={item.levelUuid ?? item.label} item={item} />)
                            : <p className="reports-empty">Aucune ville à afficher.</p>}
                    </div>
                </Card>
                <Card className="reports-panel reports-breakdown" padding="medium">
                    <div className="reports-panel__heading">
                        <div><h2>Résultat net par période</h2><p>Revenus encaissés moins dépenses · {data.currency}</p></div>
                    </div>
                    <FinancialTrend items={data.financialSummary} currency={data.currency} />
                </Card>

                <Card className="reports-panel reports-breakdown" padding="medium">
                    <div className="reports-panel__heading">
                        <div><h2>Dépenses par ville</h2><p>Répartition géographique des dépenses.</p></div>
                    </div>
                    <div className="reports-breakdown__list">
                        {data.expensesByCity.length > 0
                            ? data.expensesByCity.map((item) => <ExpenseRow key={`${item.levelUuid ?? item.levelLabel}-${item.category}-${item.currency}`} item={item} />)
                            : <p className="reports-empty">Aucune dépense par ville sur la période.</p>}
                    </div>
                </Card>
                </div>

                <div className="reports-insights-column">
                <Card className="reports-panel reports-attention" padding="medium">
                    <div className="reports-panel__heading">
                        <div><h2>Impayés à recouvrer</h2><p>Échéances en retard par bail.</p></div>
                    </div>
                    <ul className="reports-attention__list">
                        {data.arrears.length > 0
                            ? data.arrears.map((item, index) => <ArrearsRow key={`${item.leaseUuid}-${index}`} item={item} />)
                            : <li className="reports-empty">Aucun impayé à signaler.</li>}
                    </ul>
                </Card>
                <Card className="reports-panel reports-breakdown" padding="medium">
                    <div className="reports-panel__heading">
                        <div><h2>Dépenses par catégorie</h2><p>Répartition des dépenses de l’organisation.</p></div>
                    </div>
                    <div className="reports-breakdown__list">
                        {data.expensesByCategory.length > 0
                            ? data.expensesByCategory.map((item) => <ExpenseRow key={`${item.category}-${item.levelLabel}`} item={item} />)
                            : <p className="reports-empty">Aucune dépense enregistrée sur la période.</p>}
                    </div>
                </Card>
                </div>
            </div>
        </div>
    );
}
