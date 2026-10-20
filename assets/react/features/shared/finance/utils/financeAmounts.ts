import type { FinancialMovement } from '../types/finance.types';

function cents(amount: string): bigint {
    const match = /^(-?)(\d+)(?:\.(\d{0,2})\d*)?$/.exec(amount.trim());
    if (!match) return 0n;
    const value = BigInt(match[2]) * 100n + BigInt((match[3] ?? '').padEnd(2, '0') || '0');
    return match[1] ? -value : value;
}

function decimal(value: bigint): string {
    const sign = value < 0n ? '-' : '';
    const absolute = value < 0n ? -value : value;
    return `${sign}${absolute / 100n}.${String(absolute % 100n).padStart(2, '0')}`;
}

/** Refuse une réponse monétaire mal formée au lieu de l'intégrer comme zéro. */
export function assertValidTreasuryAmount(amount: string): void {
    if (!/^-?\d{1,10}(?:\.\d{1,2})?$/.test(amount.trim())) {
        throw new Error('Un montant financier reçu est invalide. Les totaux ne peuvent pas être calculés de manière fiable.');
    }
}

/** Somme en centimes, en gardant chaque devise dans son propre total. */
export function sumMovements(movements: FinancialMovement[], predicate: (movement: FinancialMovement) => boolean): Map<string, string> {
    const totals = new Map<string, bigint>();
    for (const movement of movements) {
        if (predicate(movement)) totals.set(movement.currency, (totals.get(movement.currency) ?? 0n) + cents(movement.amount));
    }
    return new Map([...totals].map(([currency, amount]) => [currency, decimal(amount)]));
}

export function negateDecimal(amount: string): string {
    return amount.startsWith('-') ? amount.slice(1) : `-${amount}`;
}
