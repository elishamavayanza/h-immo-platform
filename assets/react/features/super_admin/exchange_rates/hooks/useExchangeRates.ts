import { useState } from 'react';
import { useAuth } from '../../../../app/providers/AuthProvider';

import { INITIAL_RATES } from '../services/exchangeRatesService';

export function useExchangeRates() {
    const [rates, setRates] = useState(INITIAL_RATES);
    const { user } = useAuth();
    const updateRate = (rate: string) => setRates((current) => [{
        id: crypto.randomUUID(),
        pair: 'USD / CDF',
        rate: formatRate(rate),
        effectiveAt: 'À l’instant',
        source: 'Saisie manuelle',
        updatedBy: user?.fullName ?? 'Utilisateur connecté',
    }, ...current]);
    return { rates, updateRate };
}

/** Garde les huit décimales du DECIMAL backend sans conversion en float. */
function formatRate(value: string): string {
    const [whole, fraction = ''] = value.replace(',', '.').split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    return `${grouped},${fraction.padEnd(8, '0')}`;
}
