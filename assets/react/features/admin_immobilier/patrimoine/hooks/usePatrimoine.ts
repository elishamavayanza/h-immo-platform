import { useCallback, useEffect, useMemo, useState } from 'react';

import { useToast } from '../../../../app/layout/MainLayout/contexts/ToastContext';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { actionErrorMessage, isValidationError } from '../../../shared/actionErrors';
import {
    addParcelPhotos,
    createCityAdmin as createCityAdminRequest,
    addUnitPhoto,
    createBuilding as createBuildingRequest,
    createCity as createCityRequest,
    createParcel as createParcelRequest,
    createUnit as createUnitRequest,
    deleteBuilding as deleteBuildingRequest,
    deleteCity as deleteCityRequest,
    deleteParcel as deleteParcelRequest,
    deleteUnit as deleteUnitRequest,
    deleteParcelPhoto,
    fetchPatrimoine,
    publishUnit as publishUnitRequest,
    removeUnitPhoto,
    updateBuilding as updateBuildingRequest,
    updateCity as updateCityRequest,
    updateParcel as updateParcelRequest,
    updateUnit as updateUnitRequest,
    type BuildingPayload,
    type CityPayload,
    type ParcelPayload,
    type UnitPayload,
} from '../services/patrimoineService';
import type { CityItem, UnitItem } from '../../shared/types/reference.types';
import type { PatrimoineData } from '../types/patrimoine.types';

/**
 * Chargement du patrimoine de l'organization active (catalogue + rapport),
 * puis filtrage local (recherche, ville, type) sur le jeu chargé.
 *
 * Les filtres ne déclenchent aucun nouvel appel : les listes API sont
 * bornées à REFERENCE_LIMIT, et le backend ne propose pas de `search` sur
 * ces endpoints — filtrer côté client sur ce qui est déjà chargé reste plus
 * honnête qu'une pagination qui ferait croire à une liste complète.
 */
export function usePatrimoine() {
    const { currentOrganization } = useOrganization();
    const { push } = useToast();
    const organizationUuid = currentOrganization?.uuid ?? null;

    const [data, setData] = useState<PatrimoineData | null>(null);
    const [isLoading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const [search, setSearch] = useState('');
    const [city, setCity] = useState('all');
    const [kind, setKind] = useState('all');

    const reload = useCallback(async () => {
        if (!organizationUuid) {
            setData(null);
            setLoading(false);

            return;
        }
        setLoading(true);
        setError(null);
        try {
            setData(await fetchPatrimoine(organizationUuid));
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'Impossible de charger le patrimoine de l’organisation.');
        } finally {
            setLoading(false);
        }
    }, [organizationUuid]);

    useEffect(() => { void reload(); }, [reload]);

    const rows = useMemo(() => {
        const query = search.trim().toLocaleLowerCase('fr');
        return (data?.rows ?? []).filter((row) =>
            (!query || [row.name, row.sublabel, row.city].some((value) => value.toLocaleLowerCase('fr').includes(query)))
            && (city === 'all' || row.city === city)
            && (kind === 'all' || row.kind === kind),
        );
    }, [data, search, city, kind]);

    /**
     * Exécute une écriture : toast de succès + rechargement, ou toast d'erreur.
     * Un 422 est re-throw sans toast : les violations sont mappées champ par
     * champ par le composant appelant. Toute méthode re-throw l'erreur.
     */
    const runAction = async (successMessage: string, action: () => Promise<unknown>): Promise<void> => {
        try {
            await action();
            push('success', successMessage);
            await reload();
        } catch (cause) {
            if (!isValidationError(cause)) push('error', actionErrorMessage(cause));
            throw cause;
        }
    };

    const createCity = (payload: CityPayload) => {
        if (!organizationUuid) return Promise.resolve();
        return runAction('Ville enregistrée.', () => createCityRequest(organizationUuid, payload));
    };

    const createCityAdmin = (cityUuid: string, payload: { fullName: string; email: string; phone: string }) => {
        if (!organizationUuid) return Promise.resolve();
        return runAction('Administrateur de ville créé. Un email de configuration lui sera envoyé.', () =>
            createCityAdminRequest({ organizationUuid, cityUuid, ...payload }),
        );
    };

    const updateCity = (uuid: string, payload: CityPayload) =>
        runAction('Ville mise à jour.', () => updateCityRequest(uuid, payload));

    const setCityStatus = (city: CityItem, status: CityPayload['status']) =>
        updateCity(city.id, {
            name: city.name,
            code: city.code,
            province: city.province,
            country: city.country,
            status,
        });

    const deleteCity = (uuid: string) => runAction('Ville supprimée.', () => deleteCityRequest(uuid));

    const createParcel = (payload: ParcelPayload) =>
        runAction('Parcelle enregistrée.', () => createParcelRequest(payload));

    const updateParcel = (uuid: string, payload: ParcelPayload) =>
        runAction('Parcelle mise à jour.', () => updateParcelRequest(uuid, payload));

    const deleteParcel = (uuid: string) => runAction('Parcelle supprimée.', () => deleteParcelRequest(uuid));

    const addParcelPhotosAction = useCallback(async (parcelUuid: string, files: File[]): Promise<void> => {
        await runAction('Photo(s) ajoutée(s).', () => addParcelPhotos(parcelUuid, files));
        await reload();
    }, [push, reload]);

    const deleteParcelPhotoAction = useCallback(async (parcelUuid: string, filename: string): Promise<void> => {
        await runAction('Photo retirée.', () => deleteParcelPhoto(parcelUuid, filename));
        await reload();
    }, [push, reload]);

    const createBuilding = (payload: BuildingPayload) =>
        runAction('Bâtiment enregistré.', () => createBuildingRequest(payload));

    const updateBuilding = (uuid: string, payload: BuildingPayload) =>
        runAction('Bâtiment mis à jour.', () => updateBuildingRequest(uuid, payload));

    const deleteBuilding = (uuid: string) => runAction('Bâtiment supprimé.', () => deleteBuildingRequest(uuid));

    const createUnit = (payload: UnitPayload) => runAction('Unité enregistrée.', () => createUnitRequest(payload));

    const updateUnit = (uuid: string, payload: UnitPayload) =>
        runAction('Unité mise à jour.', () => updateUnitRequest(uuid, payload));

    const deleteUnit = (uuid: string) => runAction('Unité supprimée.', () => deleteUnitRequest(uuid));

    const addUnitPhotoAction = useCallback(async (unitUuid: string, file: File): Promise<void> => {
        await runAction('Photo ajoutée.', () => addUnitPhoto(unitUuid, file));
        await reload();
    }, [push, reload]);

    const removeUnitPhotoAction = useCallback(async (unitUuid: string, photoUuid: string): Promise<void> => {
        await runAction('Photo retirée.', () => removeUnitPhoto(unitUuid, photoUuid));
        await reload();
    }, [push, reload]);

    const publishUnit = (unit: UnitItem) =>
        runAction(unit.isPublished ? 'Annonce retirée de la vitrine.' : 'Unité publiée sur la vitrine.', () => publishUnitRequest(unit.id, !unit.isPublished));

    return {
        data,
        rows,
        cities: data?.cities ?? [],
        parcels: data?.parcels ?? [],
        buildings: data?.buildings ?? [],
        units: data?.units ?? [],
        isLoading,
        error,
        reload,
        search,
        setSearch,
        city,
        setCity,
        kind,
        setKind,
        createCity,
        createCityAdmin,
        updateCity,
        setCityStatus,
        deleteCity,
        createParcel,
        updateParcel,
        deleteParcel,
        addParcelPhotos: addParcelPhotosAction,
        deleteParcelPhoto: deleteParcelPhotoAction,
        createBuilding,
        updateBuilding,
        deleteBuilding,
        createUnit,
        updateUnit,
        deleteUnit,
        addUnitPhoto: addUnitPhotoAction,
        removeUnitPhoto: removeUnitPhotoAction,
        publishUnit,
    };
}
