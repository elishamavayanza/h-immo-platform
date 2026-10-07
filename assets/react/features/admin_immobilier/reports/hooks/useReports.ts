import { useReportsData } from '../../../shared/reports/hooks/useReportsData';
import { fetchAdminImmobilierReports } from '../services/reportsService';
export function useReports() { return useReportsData(fetchAdminImmobilierReports); }
