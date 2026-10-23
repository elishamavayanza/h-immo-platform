import { canDo } from '../assets/react/features/shared/permissions.ts';
import { assertValidTreasuryAmount, sumMovements } from '../assets/react/features/shared/finance/utils/financeAmounts.ts';
import { toExpenseMovement, toPaymentMovement } from '../assets/react/features/shared/finance/utils/treasuryMovements.ts';
import { loadCompletePages } from '../assets/react/features/shared/finance/utils/treasuryPagination.ts';
import { requiresCashOpeningConfirmation, resolveCashCityUuid } from '../assets/react/features/shared/finance/utils/treasuryUi.ts';
import type { FinancialMovement } from '../assets/react/features/shared/finance/types/finance.types.ts';

let checks = 0;
function assert(condition: unknown, description: string): asserts condition {
    checks++;
    if (!condition) throw new Error(`Échec contrôle ${checks}: ${description}`);
}

const patron = 'patron';
const immobilier = 'admin_immobilier';
const ville = 'admin_ville';
assert(canDo(patron, 'view_payment') && canDo(patron, 'view_expense'), 'PATRON conserve la consultation financière');
assert(!canDo(patron, 'manage_treasury_balance') && !canDo(patron, 'create_document') && !canDo(patron, 'delete_document'), 'PATRON ne reçoit pas les commandes d’écriture refusées');
assert(canDo(immobilier, 'view_payment') && canDo(immobilier, 'view_expense'), 'ADMIN_IMMOBILIER conserve la consultation');
assert(!canDo(immobilier, 'manage_treasury_balance') && !canDo(immobilier, 'create_document') && !canDo(immobilier, 'delete_document'), 'ADMIN_IMMOBILIER ne reçoit pas les commandes refusées');
assert(canDo(ville, 'create_payment') && canDo(ville, 'create_expense') && canDo(ville, 'update_expense'), 'ADMIN_VILLE garde les écritures financières permises');
assert(canDo(ville, 'manage_treasury_balance') && canDo(ville, 'create_document') && !canDo(ville, 'delete_document') && !canDo(ville, 'delete_expense'), 'ADMIN_VILLE garde les droits de caisse et d’ajout sans droits de suppression');

const payment = toPaymentMovement({ id: 'p-1', rentId: 'rent-12345678', cityUuid: 'city-1', cityName: 'Goma', amount: '-25.00', currency: 'USD', paymentDate: '2026-10-01', method: 'cash', reference: null, receiptNumber: null, isReversal: true, isReversed: false, reversalOfUuid: 'p-origin' });
assert(payment.kind === 'expense' && payment.type === 'Annulation de paiement' && payment.amount === '-25.00', 'la contre-écriture de paiement garde son signe et devient une sortie explicite');
assert(payment.city === 'Goma' && payment.cityUuid === 'city-1', 'la ville du paiement vient de la réponse API, pas du filtre actif');
assert(payment.reversalOfUuid === 'p-origin', 'la référence de l’écriture d’origine reste disponible pour consultation');
assertValidTreasuryAmount('-25.00');
let rejectedMalformedAmount = false;
try { assertValidTreasuryAmount('25.109'); } catch { rejectedMalformedAmount = true; }
assert(rejectedMalformedAmount, 'un montant à plus de deux décimales est refusé au lieu d’être tronqué');
rejectedMalformedAmount = false;
try { assertValidTreasuryAmount('indisponible'); } catch { rejectedMalformedAmount = true; }
assert(rejectedMalformedAmount, 'un montant non numérique empêche les totaux erronés');

const expense = toExpenseMovement({ id: 'e-1', date: '2026-10-01', city: 'Goma', cityId: 'city-1', categoryCode: 'maintenance', category: 'Entretien', property: 'Immeuble A', parcelId: null, buildingId: null, unitId: null, workerId: null, description: 'Correction', amount: '-10.00', currency: 'USD', expenseDate: '2026-10-01', method: 'cash', supplier: null, reference: null, notes: null, isReversal: true, isReversed: false, reversalOfId: 'e-original' });
assert(expense.kind === 'income' && expense.type.startsWith('Annulation de dépense') && expense.amount === '10.00', 'une contre-écriture de dépense augmente le solde de caisse avec le bon signe');
assert(expense.reversalOfUuid === 'e-original', 'la contre-écriture de dépense conserve le lien vers sa dépense source');

const movements: FinancialMovement[] = [payment, { ...payment, id: 'payment-p2', kind: 'income', amount: '50.25', currency: 'USD', isReversal: false }, { ...payment, id: 'payment-p3', amount: '100.00', currency: 'CDF', isReversal: false }];
const net = sumMovements(movements, () => true);
assert(net.get('USD') === '25.25' && net.get('CDF') === '100.00' && net.size === 2, 'les calculs en centimes conservent les devises séparées');

const allPages = await loadCompletePages({ items: [{ id: 'a' }, { id: 'b' }], total: 3, limit: 2 }, async (page) => ({ items: page === 2 ? [{ id: 'c' }] : [], total: 3, limit: 2 }), 'éléments');
assert(allPages.length === 3 && new Set(allPages.map(({ id }) => id)).size === 3, 'toutes les pages sont réunies sans doublons');
const movementsOverOnePage = Array.from({ length: 205 }, (_, index) => ({ id: `movement-${index + 1}` }));
const pagedMovements = await loadCompletePages(
    { items: movementsOverOnePage.slice(0, 100), total: movementsOverOnePage.length, limit: 100 },
    async (page) => ({ items: movementsOverOnePage.slice((page - 1) * 100, page * 100), total: movementsOverOnePage.length, limit: 100 }),
    'mouvements',
    100,
);
assert(pagedMovements.length === 205 && new Set(pagedMovements.map(({ id }) => id)).size === 205, 'plus de 100 mouvements sont chargés sur toutes les pages sans perte ni doublon');
let rejectedChangedTotal = false;
try {
    await loadCompletePages({ items: [{ id: 'a' }], total: 2, limit: 1 }, async () => ({ items: [{ id: 'b' }], total: 3, limit: 1 }), 'éléments');
} catch { rejectedChangedTotal = true; }
assert(rejectedChangedTotal, 'un total qui change entre les pages fait échouer le chargement plutôt que d’afficher un faux total');
let rejectedOverLimit = false;
try {
    await loadCompletePages({ items: [], total: 5_001, limit: 100 }, async () => ({ items: [], total: 5_001, limit: 100 }), 'éléments');
} catch { rejectedOverLimit = true; }
assert(rejectedOverLimit, 'au-delà de la limite, aucun total partiel ne peut être présenté comme complet');

assert(resolveCashCityUuid(ville, 'city-old', [{ id: 'city-allowed' }], 'city-allowed') === 'city-allowed', 'ADMIN_VILLE utilise une ville attribuée');
assert(resolveCashCityUuid(ville, 'city-old', [{ id: 'city-allowed' }], 'city-outside') === 'city-allowed', 'une ville active hors périmètre retombe sur une ville attribuée');
assert(resolveCashCityUuid(ville, 'city-selected', [{ id: 'city-selected' }, { id: 'city-active' }], 'city-active') === 'city-selected', 'ADMIN_VILLE peut conserver une ville sélectionnée dans son périmètre, même si une autre ville est active');
assert(resolveCashCityUuid(ville, 'city-outside', [{ id: 'city-allowed' }, { id: 'city-active' }], 'city-active') === 'city-active', 'une sélection hors périmètre retombe sur la ville active autorisée');
assert(resolveCashCityUuid(immobilier, 'city-old', [{ id: 'city-new' }]) === 'city-new', 'un changement de contexte remplace une ville de caisse qui n’est plus disponible');
assert(!requiresCashOpeningConfirmation(null) && requiresCashOpeningConfirmation('0.00'), 'la confirmation est demandée uniquement pour remplacer un solde déjà défini, même égal à zéro');

console.log(`OK : ${checks} contrôles Trésorerie vérifiés.`);
