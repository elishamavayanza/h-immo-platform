import type { DepenseRow } from '../../../admin_immobilier/depenses/types/depense.types';
import type { FinancialMovement } from '../types/finance.types';
import { assertValidTreasuryAmount } from './financeAmounts.ts';

export interface TreasuryPaymentItem {
    id: string;
    rentId: string;
    cityUuid: string;
    cityName: string;
    amount: string;
    currency: string;
    paymentDate: string;
    method: string;
    reference: string | null;
    receiptNumber: string | null;
    isReversal: boolean;
    isReversed: boolean;
    reversalOfUuid?: string | null;
}

const EXPENSE_LABELS: Record<string, string> = {
    salary: 'Personnel', tax: 'Taxes et impôts', maintenance: 'Entretien et réparation',
    utility: 'Eau et électricité', insurance: 'Assurance', management_fee: 'Frais de gestion',
    supply: 'Fournitures', cleaning: 'Nettoyage', security: 'Gardiennage',
    public_service: 'Services publics', notary_fee: 'Frais notariés', other: 'Autre dépense',
};

function negate(amount: string): string {
    const value = amount.trim();
    return value.startsWith('-') ? value.slice(1) : `-${value}`;
}

export function toExpenseMovement(row: DepenseRow): FinancialMovement {
    assertValidTreasuryAmount(row.amount);
    const category = EXPENSE_LABELS[row.categoryCode] ?? row.category;
    return {
        id: `expense-${row.id}`,
        relatedType: 'expense',
        relatedUuid: row.id,
        cityUuid: row.cityId,
        date: row.expenseDate,
        kind: row.isReversal ? 'income' : 'expense',
        type: row.isReversal ? `Annulation de dépense · ${category}` : category,
        detail: row.description !== '—' ? row.description : row.property,
        city: row.city,
        method: row.method,
        amount: negate(row.amount),
        currency: row.currency,
        isReversal: row.isReversal ?? false,
        isReversed: row.isReversed ?? false,
        reversalOfUuid: row.reversalOfId ?? null,
    };
}

export function toPaymentMovement(payment: TreasuryPaymentItem): FinancialMovement {
    assertValidTreasuryAmount(payment.amount);
    return {
        id: `payment-${payment.id}`,
        relatedType: 'payment',
        relatedUuid: payment.id,
        cityUuid: payment.cityUuid,
        date: payment.paymentDate,
        kind: payment.isReversal ? 'expense' : 'income',
        type: payment.isReversal ? 'Annulation de paiement' : 'Loyer encaissé',
        detail: payment.receiptNumber || payment.reference || `Échéance ${payment.rentId.slice(0, 8)}`,
        city: payment.cityName || 'Ville indisponible',
        method: payment.method,
        amount: payment.amount,
        currency: payment.currency,
        isReversal: payment.isReversal,
        isReversed: payment.isReversed,
        reversalOfUuid: payment.reversalOfUuid ?? null,
    };
}
