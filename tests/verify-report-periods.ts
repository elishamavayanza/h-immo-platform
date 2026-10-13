/**
 * Vérifie la logique pure des périodes rapides des rapports.
 *
 * Le module `periodPresets.ts` n'a aucune dépendance navigateur et peut être
 * exécuté directement par Node (>= 20, support natif des types TS).
 *
 *   node tests/verify-report-periods.ts
 */

import assert from 'node:assert/strict';
import {
    PERIOD_PRESETS,
    computePeriodPreset,
    formatIsoDate,
    formatPeriodLabel,
    findMatchingPreset,
    isValidPeriodRange,
} from '../assets/react/features/shared/reports/periodPresets.ts';

let checks = 0;
const failures: string[] = [];

function check(label: string, ok: boolean, detail = ''): void {
    checks += 1;

    if (ok) {
        console.log(`  [OK]   ${label}`);
        return;
    }

    failures.push(label + (detail ? ` (${detail})` : ''));
    console.log(`  [FAIL] ${label}${detail ? ` -> ${detail}` : ''}`);
}

function deepEqual(actual: unknown, expected: unknown, label: string): void {
    const ok = JSON.stringify(actual) === JSON.stringify(expected);
    check(label, ok, ok ? '' : `got ${JSON.stringify(actual)}, expected ${JSON.stringify(expected)}`);
}

console.log('\n=== verify-report-periods.ts ===\n');

// --- today / this_week ---
deepEqual(
    computePeriodPreset('today', new Date(2026, 9, 13)),
    { from: '2026-10-13', to: '2026-10-13' },
    'today : même borne début et fin'
);
deepEqual(
    computePeriodPreset('this_week', new Date(2026, 9, 13)), // mardi
    { from: '2026-10-12', to: '2026-10-18' },
    'this_week : semaine lundi-dimanche'
);
deepEqual(
    computePeriodPreset('this_week', new Date(2027, 0, 1)), // vendredi, traversée année
    { from: '2026-12-28', to: '2027-01-03' },
    'this_week : semaine traversant une année'
);

// --- computePeriodPreset : this_month ---
deepEqual(
    computePeriodPreset('this_month', new Date(2026, 10, 15)), // 15 nov 2026
    { from: '2026-11-01', to: '2026-11-30' },
    'this_month (novembre 2026) : bornes 1er-30'
);
deepEqual(
    computePeriodPreset('this_month', new Date(2026, 1, 10)), // 10 fév 2026 (non bissextile)
    { from: '2026-02-01', to: '2026-02-28' },
    'this_month (février 2026 non bissextile) : 1er-28'
);
deepEqual(
    computePeriodPreset('this_month', new Date(2028, 1, 10)), // 10 fév 2028 (bissextile)
    { from: '2028-02-01', to: '2028-02-29' },
    'this_month (février 2028 bissextile) : 1er-29'
);

// --- last_month ---
deepEqual(
    computePeriodPreset('last_month', new Date(2026, 0, 15)), // 15 jan 2026 -> déc 2025
    { from: '2025-12-01', to: '2025-12-31' },
    'last_month (janvier 2026) : roulage année -> déc 2025'
);
deepEqual(
    computePeriodPreset('last_month', new Date(2026, 2, 15)), // 15 mars 2026 -> fév 2026
    { from: '2026-02-01', to: '2026-02-28' },
    'last_month (mars 2026) : fév 2026 non bissextile'
);

// --- this_quarter ---
deepEqual(
    computePeriodPreset('this_quarter', new Date(2026, 4, 20)), // 20 mai 2026 -> Q2 (avr-jun)
    { from: '2026-04-01', to: '2026-06-30' },
    'this_quarter (mai 2026) : Q2 avr-jun'
);
deepEqual(
    computePeriodPreset('this_quarter', new Date(2026, 10, 2)), // 2 nov 2026 -> Q4 (oct-déc)
    { from: '2026-10-01', to: '2026-12-31' },
    'this_quarter (nov 2026) : Q4 oct-déc'
);

// --- this_year ---
deepEqual(
    computePeriodPreset('this_year', new Date(2026, 5, 6)), // 6 juin 2026
    { from: '2026-01-01', to: '2026-12-31' },
    'this_year : 1er jan - 31 déc'
);

// --- formatIsoDate ---
check('formatIsoDate : 2026-09-01 -> 01/09/2026', formatIsoDate('2026-09-01') === '01/09/2026');
check('formatIsoDate : chaîne invalide inchangée', formatIsoDate('invalid') === 'invalid');
check('formatIsoDate : 2026-01-31 -> 31/01/2026', formatIsoDate('2026-01-31') === '31/01/2026');

// --- formatPeriodLabel ---
check('formatPeriodLabel : bornes complètes', formatPeriodLabel({ from: '2026-09-01', to: '2026-09-30' }) === '01/09/2026 → 30/09/2026');
check('formatPeriodLabel : seule borne basse', formatPeriodLabel({ from: '2026-09-01', to: '' }) === '01/09/2026 → …');
check('formatPeriodLabel : seule borne haute', formatPeriodLabel({ from: '', to: '2026-09-30' }) === '… → 30/09/2026');
check('formatPeriodLabel : aucune borne', formatPeriodLabel({ from: '', to: '' }) === 'Période complète');

// --- export PDF : sélection de période obligatoire ---
check('isValidPeriodRange : dates valides et ordonnées', isValidPeriodRange({ from: '2026-01-01', to: '2026-12-31' }));
check('isValidPeriodRange : période partielle refusée', !isValidPeriodRange({ from: '2026-01-01', to: '' }));
check('isValidPeriodRange : période inversée refusée', !isValidPeriodRange({ from: '2026-12-31', to: '2026-01-01' }));
check('isValidPeriodRange : date impossible refusée', !isValidPeriodRange({ from: '2026-02-30', to: '2026-03-01' }));

// --- findMatchingPreset ---
const refNov = new Date(2026, 10, 15); // 15 nov 2026
check('findMatchingPreset : this_month détecté', findMatchingPreset('2026-11-01', '2026-11-30', refNov) === 'this_month');
check('findMatchingPreset : this_year détecté', findMatchingPreset('2026-01-01', '2026-12-31', refNov) === 'this_year');
check('findMatchingPreset : intervalle personnalisé -> null', findMatchingPreset('2026-03-05', '2026-04-10', refNov) === null);
check('findMatchingPreset : vides -> null', findMatchingPreset('', '', refNov) === null);

// --- PERIOD_PRESETS array ---
check('PERIOD_PRESETS : 6 presets définis', PERIOD_PRESETS.length === 6);
check('PERIOD_PRESETS : today inclus', PERIOD_PRESETS.some(p => p.id === 'today'));
check('PERIOD_PRESETS : this_week inclus', PERIOD_PRESETS.some(p => p.id === 'this_week'));
check('PERIOD_PRESETS : this_month inclus', PERIOD_PRESETS.some(p => p.id === 'this_month'));
check('PERIOD_PRESETS : last_month inclus', PERIOD_PRESETS.some(p => p.id === 'last_month'));
check('PERIOD_PRESETS : this_quarter inclus', PERIOD_PRESETS.some(p => p.id === 'this_quarter'));
check('PERIOD_PRESETS : this_year inclus', PERIOD_PRESETS.some(p => p.id === 'this_year'));

console.log('\n' + '-'.repeat(60) + '\n');

if (failures.length > 0) {
    console.log(`ECHEC : ${failures.length} / ${checks} contrôles en échec`);

    for (const failure of failures) {
        console.log(`  - ${failure}`);
    }

    process.exit(1);
}

console.log(`SUCCES : ${checks} contrôles passés`);
