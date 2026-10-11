import { Card } from '../../../../components/UI/Card';
import { Badge } from '../../../../components/UI/Badge';
import { InlineSummary } from '../../../shared/components/InlineSummary';
import { formatInteger, formatMoney, formatPercent } from '../../../../../utils/format.utils';
import type { ArrearsItem, ExpenseSummaryItem, FinancialSummaryItem, OccupancyItem, PatronReportsData } from '../types';

/** Libellé court d'un mois `YYYY-MM` fourni par le backend. */
function periodLabel(period: string): string {
    if (/^\d{4}-\d{2}$/.test(period)) {
        const [year, month] = period.split('-').map(Number);
        return new Date(year, month - 1, 1).toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' });
    }

    return period;
}

/** Ligne d'occupation d'une ville : taux + barre de progression. */
function OccupancyRow({ item }: { item: OccupancyItem }) {
    return (
        <div className="reports-breakdown__item">
            <div className="reports-breakdown__copy">
                <strong>{item.label}</strong>
                <small>{item.occupiedUnits} / {item.totalUnits} unités occupées</small>
            </div>
            <span className="reports-breakdown__amount">{formatPercent(item.occupancyRate)}</span>
            <progress value={item.occupancyRate} max={100} aria-label={`${item.label} : ${formatPercent(item.occupancyRate)}`} />
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
function FinancialRow({ item }: { item: FinancialSummaryItem }) {
    const positive = !item.netResult.startsWith('-');

    return (
        <div className="reports-breakdown__item">
            <div className="reports-breakdown__copy">
                <strong>{periodLabel(item.period)}</strong>
                <small>Attendu {formatMoney(item.expected, item.currency)}</small>
            </div>
            <span className={`reports-breakdown__amount${positive ? '' : ' reports-breakdown__amount--negative'}`}>
                {formatMoney(item.netResult, item.currency)}
            </span>
        </div>
    );
}

/** Corps du rapport Patron, affiché sous l'en-tête de la page. */
export function ReportsOverview({ data }: { data: PatronReportsData }) {
    return (
        <div className="reports-page__content">
            <InlineSummary items={data.metrics.map((metric) => ({ label: metric.label, value: metric.value, detail: metric.helper }))} />

            <section className="reports-lower-grid">
                <Card className="reports-panel reports-breakdown" padding="medium">
                    <div className="reports-panel__heading">
                        <div><h2>Occupation par ville</h2><p>Taux d’occupation du patrimoine par ville.</p></div>
                    </div>
                    <div className="reports-breakdown__list">
                        {data.cities.length > 0
                            ? data.cities.map((item) => <OccupancyRow key={item.levelUuid ?? item.label} item={item} />)
                            : <p className="reports-empty">Aucune ville à afficher.</p>}
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
                <Card className="reports-panel reports-breakdown" padding="medium">
                    <div className="reports-panel__heading">
                        <div><h2>Résultat net par période</h2><p>Revenus encaissés moins dépenses · {data.currency}</p></div>
                    </div>
                    <div className="reports-breakdown__list">
                        {data.financialSummary.length > 0
                            ? data.financialSummary.map((item) => <FinancialRow key={item.period} item={item} />)
                            : <p className="reports-empty">Aucune donnée financière sur la période.</p>}
                    </div>
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
            </section>

            <section className="reports-lower-grid">
                <Card className="reports-panel reports-breakdown" padding="medium">
                    <div className="reports-panel__heading">
                        <div><h2>Dépenses par ville</h2><p>Répartition géographique des dépenses.</p></div>
                    </div>
                    <div className="reports-breakdown__list">
                        {data.expensesByCity.length > 0
                            ? data.expensesByCity.map((item) => <ExpenseRow key={`${item.levelUuid ?? item.levelLabel}`} item={item} />)
                            : <p className="reports-empty">Aucune dépense par ville sur la période.</p>}
                    </div>
                </Card>
            </section>
        </div>
    );
}
