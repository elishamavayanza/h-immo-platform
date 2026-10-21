import assert from 'node:assert/strict';
import { buildDonutChartLayout, resolveOccupancyCounts } from '../assets/react/components/Data/donutChart.ts';

let checks = 0;
function check(condition: boolean, message: string): void {
    assert.equal(condition, true, message);
    checks++;
}

const occupied = resolveOccupancyCounts(7, 10, 3);
check(occupied.valid && occupied.rate === 70 && occupied.available === 3, 'calcule le taux et les unités vacantes');
check(resolveOccupancyCounts(10, 10).valid, 'accepte une occupation à 100 %');
check(resolveOccupancyCounts(0, 10).valid, 'accepte zéro unité occupée quand le total existe');
const noUnits = resolveOccupancyCounts(0, 0);
check(noUnits.valid && noUnits.rate === null, 'ne divise pas par zéro lorsqu’aucune unité existe');
check(!resolveOccupancyCounts(11, 10).valid, 'refuse plus d’unités occupées que le total');
check(!resolveOccupancyCounts(7, 10, 2).valid, 'refuse un nombre vacant incohérent');
check(!resolveOccupancyCounts(-1, 10).valid, 'refuse les compteurs négatifs');

const layout = buildDonutChartLayout([
    { id: 'occupied', label: 'Occupées', value: 98.4, color: 'primary', displayValue: '98,4 %' },
    { id: 'vacant', label: 'Vacantes', value: 1.6, color: 'neutral', displayValue: '1,6 %' },
]);
check(layout.valid && layout.arcs.length === 2, 'construit deux segments pour un taux décimal');
check(layout.arcs[0].percentage === 98.4 && Math.abs(layout.arcs[1].percentage - 1.6) < 0.000001, 'préserve les proportions de l’anneau');
check(buildDonutChartLayout([{ id: 'invalid', label: 'Invalide', value: -1, color: 'neutral', displayValue: '' }]).valid === false, 'refuse une valeur négative');
check(buildDonutChartLayout([{ id: 'zero', label: 'Vide', value: 0, color: 'neutral', displayValue: '0' }]).total === 0n, 'gère une répartition vide');

console.log(`Vérification DonutChart : ${checks} contrôles réussis.`);
