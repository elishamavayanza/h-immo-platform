import { useReportsData } from '../../../shared/reports/hooks/useReportsData';
import { fetchSuperAdminReports } from '../services/reportsService';
export function useReports() { return useReportsData(fetchSuperAdminReports); }
