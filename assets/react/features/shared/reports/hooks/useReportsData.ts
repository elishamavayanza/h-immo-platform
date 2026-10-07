import { useEffect, useState } from 'react';
import type { ReportsData } from '../types/report.types';

export function useReportsData(load: () => Promise<ReportsData>) {
    const [data, setData] = useState<ReportsData | null>(null);
    const [isLoading, setLoading] = useState(true);

    useEffect(() => {
        let active = true;
        setLoading(true);
        void load().then((result) => {
            if (!active) return;
            setData(result);
            setLoading(false);
        });
        return () => { active = false; };
    }, [load]);

    return { data, isLoading };
}
