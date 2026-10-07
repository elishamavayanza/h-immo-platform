import { useReportsData } from '../../../shared/reports/hooks/useReportsData';
import { fetchPatronReports } from '../services/reportsService';
export function useReports() { return useReportsData(fetchPatronReports); }
