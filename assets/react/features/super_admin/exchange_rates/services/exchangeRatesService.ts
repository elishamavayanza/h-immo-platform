import type { ExchangeRateRecord } from '../types/exchangeRate.types';

/** Historique fictif de la maquette. Les taux ne sont pas utilisés pour calculer des montants métier. */
export const INITIAL_RATES: ExchangeRateRecord[] = [
    { id: 'rate-1', pair: 'USD / CDF', rate: '2 850,00', effectiveAt: '07 oct. 2026 · 09:30', source: 'Saisie manuelle', updatedBy: 'Sarah Mbala' },
    { id: 'rate-2', pair: 'USD / CDF', rate: '2 825,00', effectiveAt: '01 oct. 2026 · 08:00', source: 'Saisie manuelle', updatedBy: 'Sarah Mbala' },
    { id: 'rate-3', pair: 'USD / CDF', rate: '2 810,00', effectiveAt: '24 sept. 2026 · 10:15', source: 'Saisie manuelle', updatedBy: 'David Kalu' },
    { id: 'rate-4', pair: 'USD / CDF', rate: '2 790,00', effectiveAt: '17 sept. 2026 · 08:20', source: 'Saisie manuelle', updatedBy: 'Sarah Mbala' },
    { id: 'rate-5', pair: 'USD / CDF', rate: '2 775,00', effectiveAt: '10 sept. 2026 · 08:00', source: 'Saisie manuelle', updatedBy: 'Sarah Mbala' },
];
