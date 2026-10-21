import { useChartDisplayMode } from './useChartDisplayMode';

export function ChartDisplayToggle() {
    const [mode, setMode] = useChartDisplayMode();

    return (
        <div className="chart-display-toggle" role="group" aria-label="Mode d’affichage des graphiques d’occupation">
            <button
                type="button"
                className="chart-display-toggle__button"
                aria-pressed={mode === 'donut'}
                onClick={() => setMode('donut')}
                title="Afficher en anneau"
            >
                Anneau
            </button>
            <button
                type="button"
                className="chart-display-toggle__button"
                aria-pressed={mode === 'bar'}
                onClick={() => setMode('bar')}
                title="Afficher en barre empilée"
            >
                Barre
            </button>
        </div>
    );
}
