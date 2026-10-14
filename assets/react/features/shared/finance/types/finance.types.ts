export type FinancialMovementKind = 'income' | 'expense';

/** Mouvement affiché en valeur signée : une contre-écriture inverse l'opération d'origine. */
export interface FinancialMovement {
    id: string;
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
}

export interface TreasuryData {
    movements: FinancialMovement[];
    expenseTotal: number;
    paymentTotal: number;
    note: string | null;
}
