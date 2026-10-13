import { useCallback, useEffect, useState } from 'react';

import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { fetchCities } from '../../../admin_immobilier/shared/services/referenceService';
import { fetchPatronReports } from '../services/reportsService';
import type { PatronReportsData } from '../types';
import { computePeriodPreset } from '../../../shared/reports/periodPresets';

/** Option du sélecteur de ville (borné aux villes de l'organisation active). */
export interface PatronCityOption {
    value: string;
    label: string;
}

/**
 * Rapport PATRON : le périmètre vient du backend (organisation active et, si
 * fournie, ville). On recharge à chaque changement de période, de ville ou
 * d'organisation ; aucun filtrage local n'est appliqué (le backend reste seul
 * juge de l'accès). Les villes proposées au filtre sont celles de
 * l'organisation active.
 */
export function useReports() {
    const { currentOrganization } = useOrganization();
    const organizationUuid = currentOrganization?.uuid ?? null;

    const [periodFrom, setPeriodFrom] = useState(() => computePeriodPreset('this_year').from);
    const [periodTo, setPeriodTo] = useState(() => computePeriodPreset('this_year').to);
    const [cityUuid, setCityUuid] = useState('');

    const [cities, setCities] = useState<PatronCityOption[]>([]);
    const [data, setData] = useState<PatronReportsData | null>(null);
    const [isLoading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    // Villes de l'organisation : alimente le filtre. Une ville retirée du
    // filtre laisse la liste intacte ; une erreur de chargement des villes ne
    // bloque pas le rapport (le filtre reste simplement sans options).
    useEffect(() => {
        let active = true;
        if (!organizationUuid) {
            setCities([]);

            return () => { active = false; };
        }
        fetchCities(organizationUuid)
            .then((page) => {
                if (active) setCities(page.items.map((city) => ({ value: city.id, label: city.name })));
            })
            .catch(() => {
                if (active) setCities([]);
            });

        return () => { active = false; };
    }, [organizationUuid]);

    const reload = useCallback(async () => {
        if (!organizationUuid) {
            setData(null);
            setLoading(false);

            return;
        }
        setLoading(true);
        setError(null);
        try {
            setData(await fetchPatronReports(organizationUuid, {
                periodFrom: periodFrom || undefined,
                periodTo: periodTo || undefined,
                cityUuid: cityUuid || undefined,
            }));
        } catch (cause) {
            setData(null);
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger le rapport de l’organisation.');
        } finally {
            setLoading(false);
        }
    }, [organizationUuid, periodFrom, periodTo, cityUuid]);

    useEffect(() => { void reload(); }, [reload]);

    return {
        data,
        isLoading,
        error,
        reload,
        cities,
        cityUuid,
        setCityUuid,
        periodFrom,
        setPeriodFrom,
        periodTo,
        setPeriodTo,
    };
}
