import { useEffect, useState } from 'react';
import { fetchAdminImmobilierDashboard } from '../services/adminImmobilierDashboardService';
import type { AdminImmobilierDashboardData } from '../types/adminImmobilierDashboard.types';
export function useAdminImmobilierDashboard() { const [data, setData] = useState<AdminImmobilierDashboardData | null>(null); useEffect(() => { let mounted = true; fetchAdminImmobilierDashboard().then((result) => { if (mounted) setData(result); }); return () => { mounted = false; }; }, []); return data; }
