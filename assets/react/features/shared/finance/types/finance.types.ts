export type FinancialMovementKind = 'income' | 'expense';

/** Mouvement affiché en valeur signée : une contre-écriture inverse l'opération d'origine. */
export interface FinancialMovement {
    id: string;
    relatedType: 'payment' | 'expense';
    relatedUuid: string;
    cityUuid?: string;
    date: string;
    kind: FinancialMovementKind;
    type: string;
    detail: string;
    city: string;
    method: string | null;
    amount: string;
    currency: string;
    isReversal: boolean;
    isReversed: boolean;
    reversalOfUuid?: string | null;
}

export interface PaymentDetails {
    id: string;
    rentId: string;
    cityUuid: string;
    cityName: string;
    createdByName: string;
    amount: string;
    currency: string;
    exchangeRate: string | null;
    originalAmount: string | null;
    originalCurrency: string | null;
    paymentDate: string;
    method: string;
    reference: string | null;
    receiptNumber: string | null;
    notes: string | null;
    reversalOfUuid: string | null;
    isReversal: boolean;
    isReversed: boolean;
}

export interface TreasuryData {
    movements: FinancialMovement[];
    expenseTotal: number;
    paymentTotal: number;
    note: string | null;
}
