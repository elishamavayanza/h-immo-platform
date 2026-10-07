import { useState } from 'react';

import { INITIAL_RATES } from '../services/exchangeRatesService';

export function useExchangeRates() {
    const [rates, setRates] = useState(INITIAL_RATES);
    const updateRate = (rate: string) => setRates((current) => [{
        id: `local-${Date.now()}`,
        pair: 'USD / CDF',
        rate: Number(rate).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
        effectiveAt: 'À l’instant',
        source: 'Saisie manuelle',
        updatedBy: 'Sarah Mbala',
    }, ...current]);
    return { rates, updateRate };
}
