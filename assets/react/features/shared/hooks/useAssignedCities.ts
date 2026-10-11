import { useEffect, useMemo, useState } from 'react';

import type { SelectOption } from '../../../components/Forms/Select/Select';
import { useOrganization } from '../../../app/providers/OrganizationProvider';
import { fetchCities } from '../../admin_immobilier/shared/services/referenceService';
import type { CityItem } from '../../admin_immobilier/shared/types/reference.types';

/**
 * Villes réellement accessibles à l'utilisateur courant dans l'organisation
 * active.
 *
 * Le backend borne déjà la réponse : pour un ADMIN_VILLE, `GET
 * /v1/property/cities` ne renvoie que les villes qui lui sont affectées
 * (UserCity). Ce hook fédère l'appel, l'état de chargement et la
 * conversion en options de `<Select>`, afin que chaque écran (tableau de
 * bord, rapports, formulaires) affiche le même sélecteur et le même état
 * vide « Aucune ville ne vous est affectée ».
 */
export interface UseAssignedCitiesResult {
    /** Villes retournées par le backend (UUID exposé = `id`). */
    cities: readonly CityItem[];
    /** Options prêtes pour `<Select>` (value = `uuid` de la ville). */
    options: SelectOption[];
    isLoading: boolean;
    hasAssignedCities: boolean;
}

export function useAssignedCities(): UseAssignedCitiesResult {
    const { currentOrganization } = useOrganization();
    const [cities, setCities] = useState<CityItem[]>([]);
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        let active = true;
        setCities([]);

        const organizationUuid = currentOrganization?.uuid;
        if (!organizationUuid) {
            setIsLoading(false);
            return;
        }

        setIsLoading(true);
        fetchCities(organizationUuid)
            .then((page) => {
                if (active) setCities(page.items);
            })
            .catch(() => {
                if (active) setCities([]);
            })
            .finally(() => {
                if (active) setIsLoading(false);
            });

        return () => {
            active = false;
        };
    }, [currentOrganization?.uuid]);

    const options = useMemo<SelectOption[]>(
        () => cities.map((city) => ({ value: city.id, label: city.name })),
        [cities]
    );

    return {
        cities,
        options,
        isLoading,
        hasAssignedCities: cities.length > 0,
    };
}