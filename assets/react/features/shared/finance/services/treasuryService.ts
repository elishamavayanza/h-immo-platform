import { apiClient } from '../../../../../services/api/client';
import type { Feedback } from '../../../../../services/api/api.types';
import { fetchDepenses } from '../../../admin_immobilier/depenses/services/depensesService';
import type { FinancialMovement, TreasuryData } from '../types/finance.types';

interface PaymentItem {
    id: string;
    rentId: string;
    amount: string;
    currency: string;
    paymentDate: string;
    method: string;
    reference: string | null;
    receiptNumber: string | null;
    notes: string | null;
    isReversal: boolean;
    isReversed: boolean;
}

interface PaymentPage {
    items: PaymentItem[];
    total: number;
}

export interface CashBalanceSnapshot {
    cityUuid: string;
    currency: string;
    openingBalance: string | null;
    openingDate: string | null;
    balance: string | null;
    updatedAt: string | null;
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

function toExpenseMovement(row: Awaited<ReturnType<typeof fetchDepenses>>['rows'][number]): FinancialMovement {
    const category = EXPENSE_LABELS[row.categoryCode] ?? row.category;
    return {
        id: `expense-${row.id}`,
        relatedType: 'expense',
        relatedUuid: row.id,
        cityUuid: row.cityId,
        date: row.expenseDate,
        kind: 'expense',
        type: row.isReversal ? `Correction · ${category}` : category,
        detail: row.description !== '—' ? row.description : row.property,
        city: row.city,
        method: row.method,
        amount: negate(row.amount),
        currency: row.currency,
        isReversal: row.isReversal ?? false,
        isReversed: row.isReversed ?? false,
    };
}

function toPaymentMovement(payment: PaymentItem, cityName?: string, cityUuid?: string): FinancialMovement {
    return {
        id: `payment-${payment.id}`,
        relatedType: 'payment',
        relatedUuid: payment.id,
        cityUuid,
        date: payment.paymentDate,
        kind: 'income',
        type: payment.isReversal ? 'Contre-écriture de loyer' : 'Loyer encaissé',
        detail: payment.receiptNumber || payment.reference || `Échéance ${payment.rentId.slice(0, 8)}`,
        city: cityName ?? '—',
        method: payment.method,
        amount: payment.amount,
        currency: payment.currency,
        isReversal: payment.isReversal,
        isReversed: payment.isReversed,
    };
}

/** Charge les encaissements de loyers et sorties déjà enregistrés côté métier. */
export async function fetchTreasuryData(
    organizationUuid: string,
    from: string,
    to: string,
    cityUuid?: string,
    cityName?: string,
): Promise<TreasuryData> {
    const [expenses, paymentResponse] = await Promise.all([
        fetchDepenses(organizationUuid, from, to, cityUuid),
        apiClient.get<Feedback<PaymentPage>>('/v1/payments', {
            params: {
                organizationId: organizationUuid,
                from: `${from}T00:00:00Z`,
                to: `${to}T23:59:59Z`,
                ...(cityUuid ? { cityUuid } : {}),
                page: 1,
                limit: 100,
                sortBy: 'paymentDate',
                sortOrder: 'DESC',
            },
        }),
    ]);

    const payments = paymentResponse.data.data;
    const movements = [
        ...expenses.rows.map(toExpenseMovement),
        ...payments.items.map((payment) => toPaymentMovement(payment, cityName, cityUuid)),
    ].sort((a, b) => b.date.localeCompare(a.date));
    const notes: string[] = [];
    if (expenses.note) notes.push(expenses.note);
    if (payments.items.length >= 100) notes.push('Les 100 encaissements les plus récents de la période sont affichés.');

    return {
        movements,
        expenseTotal: expenses.total,
        paymentTotal: payments.total,
        note: notes.length ? notes.join(' ') : null,
    };
}

export function formatPaymentMethod(method: string | null): string {
    const labels: Record<string, string> = {
        cash: 'Espèces', bank_transfer: 'Virement bancaire', mobile_money: 'Mobile Money',
        check: 'Chèque', card: 'Carte', other: 'Autre',
    };
    return method ? labels[method] ?? method : 'Non précisé';
}

export async function fetchCashBalance(organizationUuid: string, cityUuid: string, currency: string): Promise<CashBalanceSnapshot> {
    const response = await apiClient.get<Feedback<CashBalanceSnapshot>>('/v1/treasury/cash-balance', { params: { organizationUuid, cityUuid, currency } });
    if (!response.data.data) throw new Error('Le serveur n’a pas renvoyé le solde de caisse.');
    return response.data.data;
}

export async function saveCashOpeningBalance(organizationUuid: string, cityUuid: string, payload: { currency: string; openingBalance: string; openingDate: string }): Promise<CashBalanceSnapshot> {
    const response = await apiClient.put<Feedback<CashBalanceSnapshot>>('/v1/treasury/cash-balance', payload, { params: { organizationUuid, cityUuid } });
    if (!response.data.data) throw new Error('Le serveur n’a pas renvoyé le solde de caisse.');
    return response.data.data;
}
