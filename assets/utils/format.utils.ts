/**
 * format.utils.ts
 *
 * Mise en forme des montants et pourcentages hors composants React.
 * Les montants arrivent du backend en chaînes décimales (`NUMERIC(12,2)`,
 * jamais en `float`) : on les réaffiche sans traitement numérique, la
 * conversion en `Number` n'étant qu'une étape de formatage locale.
 */
const CURRENCY_SYMBOLS: Record<string, string> = {
    USD: '$',
    CDF: 'FC',
};

/** Symbole d'affichage d'un code devise ISO 4217 (fallback : le code lui-même). */
export function currencySymbol(currency: string): string {
    return CURRENCY_SYMBOLS[currency] ?? currency;
}

const amountFormatter = new Intl.NumberFormat('fr-FR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

/** Formate une chaîne décimale en montant lisible (ex. "18450.50" → "18 450,50"). */
export function formatAmount(amount: string): string {
    const parsed = Number(amount);
    return Number.isFinite(parsed) ? amountFormatter.format(parsed) : amount;
}

/** Formate un montant avec DEVISE et montant : `formatMoney("18450.50", "USD")` → "18 450,50 $". */
export function formatMoney(amount: string, currency: string): string {
    return `${formatAmount(amount)} ${currencySymbol(currency)}`;
}

const percentFormatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 });

/** Formate un taux (0-100) en pourcentage lisible (ex. 87.52 → "87,5 %"). */
export function formatPercent(value: number): string {
    return `${percentFormatter.format(value)} %`;
}

/** Formate un entier avec séparateur de milliers (ex. 593 → "593"). */
export function formatInteger(value: number): string {
    return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(value);
}