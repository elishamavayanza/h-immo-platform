import { apiClient } from '../../../../../services/api/client';
import type { Feedback } from '../../../../../services/api/api.types';
import { fetchDepenses } from '../../../admin_immobilier/depenses/services/depensesService';
import type { PaymentDetails, TreasuryData } from '../types/finance.types';
import { loadCompletePages } from '../utils/treasuryPagination';
import { toExpenseMovement, toPaymentMovement, type TreasuryPaymentItem } from '../utils/treasuryMovements';

interface PaymentPage {
    items: TreasuryPaymentItem[];
    total: number;
    page: number;
    limit: number;
}

export interface CashBalanceSnapshot {
    cityUuid: string;
    currency: string;
    openingBalance: string | null;
    openingDate: string | null;
    balance: string | null;
    updatedAt: string | null;
}

export interface ExpenseDetails {
    id: string;
    cityId: string;
    category: string;
    amount: string;
    currency: string;
    expenseDate: string;
    method: string | null;
    supplier: string | null;
    reference: string | null;
    receiptNumber: string | null;
    notes: string | null;
    isReversal: boolean;
    isReversed: boolean;
}


/** Charge les encaissements de loyers et sorties déjà enregistrés côté métier. */
export async function fetchTreasuryData(
    organizationUuid: string,
    from: string,
    to: string,
    cityUuid?: string,
): Promise<TreasuryData> {
    const paymentPage = async (page: number) => {
        const response = await apiClient.get<Feedback<PaymentPage>>('/v1/payments', {
            params: {
                organizationId: organizationUuid,
                from: `${from}T00:00:00Z`,
                to: `${to}T23:59:59Z`,
                ...(cityUuid ? { cityUuid } : {}),
                page,
                limit: 100,
                sortBy: 'paymentDate',
                sortOrder: 'DESC',
            },
        });
        if (!response.data.data) throw new Error('Le serveur n’a pas renvoyé les paiements de cette période.');
        return response.data.data;
    };

    const [firstPayments, expenses] = await Promise.all([
        paymentPage(1),
        fetchDepenses(organizationUuid, from, to, cityUuid, true),
    ]);
    const paymentItems = await loadCompletePages(firstPayments, paymentPage, 'paiements', 100);
    const movements = [
        ...expenses.rows.map(toExpenseMovement),
        ...paymentItems.map(toPaymentMovement),
    ].sort((a, b) => b.date.localeCompare(a.date));

    return {
        movements,
        expenseTotal: expenses.total,
        paymentTotal: firstPayments.total,
        note: expenses.note,
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

export async function fetchPaymentDetails(paymentUuid: string): Promise<PaymentDetails> {
    const response = await apiClient.get<Feedback<PaymentDetails>>(`/v1/payments/${paymentUuid}`);
    if (!response.data.data) throw new Error('Le serveur n’a pas renvoyé le détail du paiement.');
    return response.data.data;
}

export async function fetchExpenseDetails(expenseUuid: string): Promise<ExpenseDetails> {
    const response = await apiClient.get<Feedback<ExpenseDetails>>(`/v1/expenses/${expenseUuid}`);
    if (!response.data.data) throw new Error('Le serveur n’a pas renvoyé le détail de la dépense.');
    return response.data.data;
}

export async function saveCashOpeningBalance(organizationUuid: string, cityUuid: string, payload: { currency: string; openingBalance: string; openingDate: string }): Promise<CashBalanceSnapshot> {
    const response = await apiClient.put<Feedback<CashBalanceSnapshot>>('/v1/treasury/cash-balance', payload, { params: { organizationUuid, cityUuid } });
    if (!response.data.data) throw new Error('Le serveur n’a pas renvoyé le solde de caisse.');
    return response.data.data;
}
