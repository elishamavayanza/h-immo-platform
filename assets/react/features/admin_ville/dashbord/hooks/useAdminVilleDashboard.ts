import { useMemo } from 'react';
import { useAuth } from '../../../../app/providers/AuthProvider';
import { buildAdminVilleDashboard } from '../services/adminVilleDashboardService';
export function useAdminVilleDashboard() { const { user } = useAuth(); const city = user?.cities[0]?.name ?? 'Ville assignée'; return useMemo(() => buildAdminVilleDashboard(city), [city]); }
