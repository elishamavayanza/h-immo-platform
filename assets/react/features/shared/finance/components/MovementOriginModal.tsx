import { useEffect, useState } from 'react';
import { ApiError } from '../../../../../services/api/api.types';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { Modal } from '../../../../components/UI/Modal/Modal';
import { Spinner } from '../../../../components/UI/Spinner';
import { formatMoney } from '../../../../../utils/format.utils';
import { formatPaymentMethod, fetchExpenseDetails, fetchPaymentDetails, type ExpenseDetails } from '../services/treasuryService';
import type { PaymentDetails } from '../types/finance.types';

export interface MovementOrigin {
    type: 'payment' | 'expense';
    uuid: string;
}

interface Props {
    origin: MovementOrigin | null;
    onClose: () => void;
}

type OriginDetails = PaymentDetails | ExpenseDetails;

export function MovementOriginModal({ origin, onClose }: Props) {
    const [item, setItem] = useState<OriginDetails | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!origin) { setItem(null); return; }
        let active = true;
        setLoading(true);
        setError(null);
        setItem(null);
        const request = origin.type === 'payment' ? fetchPaymentDetails(origin.uuid) : fetchExpenseDetails(origin.uuid);
        request.then((details) => { if (active) setItem(details); })
            .catch((cause: unknown) => { if (active) setError(cause instanceof ApiError || cause instanceof Error ? cause.message : 'Impossible de charger l’opération d’origine.'); })
            .finally(() => { if (active) setLoading(false); });
        return () => { active = false; };
    }, [origin]);

    const payment = item && 'rentId' in item ? item : null;
    const expense = item && 'category' in item ? item : null;
    return <Modal isOpen={origin !== null} onClose={onClose} title={`Opération d’origine · ${origin?.type === 'payment' ? 'paiement' : 'dépense'}`} size="small">
        {loading ? <EmptyState icon={<Spinner size="large" />} title="Chargement de l’opération…" />
            : error ? <EmptyState title="Opération indisponible" description={error} />
                : payment ? <dl className="treasury-payment-details">
                    <div><dt>État</dt><dd>{payment.isReversal ? 'Contre-écriture' : payment.isReversed ? 'Paiement contre-passé' : 'Paiement enregistré'}</dd></div>
                    <div><dt>Montant</dt><dd>{formatMoney(payment.amount, payment.currency)}</dd></div>
                    <div><dt>Date</dt><dd>{payment.paymentDate}</dd></div>
                    <div><dt>Ville</dt><dd>{payment.cityName}</dd></div>
                    <div><dt>Mode</dt><dd>{formatPaymentMethod(payment.method)}</dd></div>
                    <div><dt>Référence</dt><dd>{payment.receiptNumber || payment.reference || '—'}</dd></div>
                    <div><dt>Enregistré par</dt><dd>{payment.createdByName}</dd></div>
                    {payment.notes && <div><dt>Note</dt><dd>{payment.notes}</dd></div>}
                </dl>
                : expense ? <dl className="treasury-payment-details">
                    <div><dt>État</dt><dd>{expense.isReversal ? 'Contre-écriture' : expense.isReversed ? 'Dépense contre-passée' : 'Dépense enregistrée'}</dd></div>
                    <div><dt>Montant</dt><dd>{formatMoney(expense.amount, expense.currency)}</dd></div>
                    <div><dt>Date</dt><dd>{expense.expenseDate}</dd></div>
                    <div><dt>Catégorie</dt><dd>{expense.category}</dd></div>
                    <div><dt>Mode</dt><dd>{formatPaymentMethod(expense.method)}</dd></div>
                    <div><dt>Référence</dt><dd>{expense.receiptNumber || expense.reference || '—'}</dd></div>
                    {expense.supplier && <div><dt>Fournisseur</dt><dd>{expense.supplier}</dd></div>}
                    {expense.notes && <div><dt>Note</dt><dd>{expense.notes}</dd></div>}
                </dl> : null}
    </Modal>;
}
