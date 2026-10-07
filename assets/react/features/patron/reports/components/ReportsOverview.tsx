import { ReportsPageView } from '../../../shared/reports/components/ReportsPageView';
import type { ReportsData } from '../types';
export function ReportsOverview({ data, isLoading }: { data: ReportsData | null; isLoading: boolean }) {
    return <ReportsPageView data={data} isLoading={isLoading} />;
}
