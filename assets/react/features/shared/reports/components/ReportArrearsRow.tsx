import { Badge } from '../../../../components/UI/Badge';
import { formatInteger, formatMoney } from '../../../../../utils/format.utils';

interface ReportArrearsItem {
    tenantName: string;
    unitLabel: string;
    leaseReference: string;
    amountDue: string;
    arrears: string;
    daysOverdue: number;
    currency: string;
}

/** Présentation commune d'un impayé dans les rapports par rôle. */
export function ReportArrearsRow({ item }: { item: ReportArrearsItem }) {
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
