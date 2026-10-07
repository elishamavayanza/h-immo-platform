import { useReports } from '../hooks/useReports';
import { ReportsOverview } from '../components/ReportsOverview';
import '../../../../../styles/pages/admin_ville/reports/_reports.scss';
export function ReportsPage() { const { data, isLoading } = useReports(); return <ReportsOverview data={data} isLoading={isLoading} />; }
