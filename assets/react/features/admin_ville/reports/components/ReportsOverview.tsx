import { Card } from '../../../../components/UI/Card';
import { Badge } from '../../../../components/UI/Badge';
import { InlineSummary } from '../../../shared/components/InlineSummary';
import { formatInteger, formatMoney, formatPercent } from '../../../../../utils/format.utils';
import type {
    AdminVilleReportsData,
    ArrearsItem,
    ExpenseSummaryItem,
    OccupancyItem,
    WorkerActivityItem,
} from '../types';
import { absoluteCents, amountBarPercent } from '../../../shared/reports/amountChart';

/** Ligne d'occupation : taux en pourcentage + barre de progression. */
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

/** Ligne d'impayé : locataire, unité, montant et jours de retard. */
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

/** Ligne de dépense agrégée par niveau (ville), montant déjà formaté. */
function ExpenseRow({ item, maximum }: { item: ExpenseSummaryItem; maximum: bigint }) {
    return (
        <div className="reports-expense-chart__item">
            <div className="reports-breakdown__item">
                <div className="reports-breakdown__copy">
                    <strong>{item.category === 'ALL' ? 'Toutes catégories' : item.category}</strong>
                    <small>{formatInteger(item.count)} écriture(s) · {item.levelLabel}</small>
                </div>
                <span className="reports-breakdown__amount">{formatMoney(item.totalAmount, item.currency)}</span>
            </div>
            <div className="reports-expense-chart__track" aria-hidden="true">
                <span style={{ width: `${amountBarPercent(item.totalAmount, maximum)}%` }} />
            </div>
        </div>
    );
}

/** Ligne de personnel affecté : rôle, affectation et salaire. */
function WorkerRow({ item }: { item: WorkerActivityItem }) {
    return (
        <div className="reports-breakdown__item">
            <div className="reports-breakdown__copy">
                <strong>{item.fullName}</strong>
                <small>{item.assignmentLabel} · {item.role}</small>
            </div>
            <span className="reports-breakdown__amount">{formatMoney(item.monthlySalary, item.currency)}</span>
        </div>
    );
}

/** Corps du rapport, affiché sous l'en-tête de la page. */
export function ReportsOverview({ data }: { data: AdminVilleReportsData }) {
    const maximumExpense = data.expenses.reduce((largest, item) => {
        const amount = absoluteCents(item.totalAmount);

        return amount > largest ? amount : largest;
    }, 0n);

    return (
        <div className="reports-page__content">
            <InlineSummary items={data.metrics.map((metric) => ({ label: metric.label, value: metric.value, detail: metric.helper }))} />

            <section className="reports-lower-grid">
                <Card className="reports-panel reports-breakdown" padding="medium">
                    <div className="reports-panel__heading">
                        <div><h2>Occupation par immeuble</h2><p>Taux d’occupation du patrimoine de {data.cityName}.</p></div>
                    </div>
                    <div className="reports-breakdown__list">
                        {data.buildings.length > 0
                            ? data.buildings.map((item) => <OccupancyRow key={item.levelUuid ?? item.label} item={item} />)
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
                <Card className="reports-panel reports-breakdown" padding="medium">
                    <div className="reports-panel__heading">
                        <div><h2>Dépenses de la ville</h2><p>Par niveau du patrimoine sur la période.</p></div>
                    </div>
                    <div className="reports-breakdown__list">
                        {data.expenses.length > 0
                            ? data.expenses.map((item) => <ExpenseRow key={`${item.category}-${item.levelLabel}`} item={item} maximum={maximumExpense} />)
                            : <p className="reports-empty">Aucune dépense enregistrée sur la période.</p>}
                    </div>
                </Card>

                <Card className="reports-panel reports-breakdown" padding="medium">
                    <div className="reports-panel__heading">
                        <div><h2>Personnel affecté</h2><p>Ouvriers rattachés à la ville.</p></div>
                    </div>
                    <div className="reports-breakdown__list">
                        {data.workers.length > 0
                            ? data.workers.map((item) => <WorkerRow key={item.workerUuid} item={item} />)
                            : <p className="reports-empty">Aucun ouvrier affecté sur la période.</p>}
                    </div>
                </Card>
            </section>
        </div>
    );
}
