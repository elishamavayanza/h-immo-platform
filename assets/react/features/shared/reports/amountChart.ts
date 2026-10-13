/** Convertit un montant décimal en centimes entiers pour dimensionner un graphique. */
export function decimalMagnitudeInCents(amount: string): bigint {
    const normalized = amount.trim();
    const negative = normalized.startsWith('-');
    const unsigned = normalized.replace(/^[+-]/, '');
    const [whole = '0', fraction = ''] = unsigned.split('.');
    const cents = BigInt(whole || '0') * 100n + BigInt(fraction.padEnd(2, '0').slice(0, 2) || '0');

    return negative ? -cents : cents;
}

/** Part entière entre 0 et 100, sans convertir les montants en nombres flottants. */
export function amountBarPercent(amount: string, maximum: bigint): number {
    if (maximum <= 0n) return 0;
    const value = decimalMagnitudeInCents(amount);
    const magnitude = value < 0n ? -value : value;

    return Number((magnitude * 100n) / maximum);
}

export function absoluteCents(amount: string): bigint {
    const value = decimalMagnitudeInCents(amount);

    return value < 0n ? -value : value;
}
