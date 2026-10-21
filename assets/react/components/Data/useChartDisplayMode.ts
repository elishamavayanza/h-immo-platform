import { useEffect, useState } from 'react';
import {
    readChartDisplayMode,
    saveChartDisplayMode,
    subscribeChartDisplayMode,
    type ChartDisplayMode,
} from '../../services/userPreferences';

export function useChartDisplayMode(): [ChartDisplayMode, (mode: ChartDisplayMode) => void] {
    const [mode, setMode] = useState(readChartDisplayMode);

    useEffect(() => subscribeChartDisplayMode(() => setMode(readChartDisplayMode())), []);

    return [mode, saveChartDisplayMode];
}
