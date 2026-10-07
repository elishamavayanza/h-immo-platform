import { useReports } from '../hooks/useReports';
import { ReportsOverview } from '../components/ReportsOverview';
import '../../../../../styles/pages/super_admin/reports/_reports.scss';
export function ReportsPage() { const { data, isLoading } = useReports(); return <ReportsOverview data={data} isLoading={isLoading} />; }
