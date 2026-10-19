import { useEffect, useMemo, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';

import { Button } from '../../../../components/UI/Button';
import { Card } from '../../../../components/UI/Card';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { Spinner } from '../../../../components/UI/Spinner';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { useAdminCity } from '../../../../app/providers/AdminCityProvider';
import { ApiError } from '../../../../../services/api/api.types';
import { fetchParcels } from '../../shared/services/referenceService';
import type { ListPage, ParcelItem } from '../../shared/types/reference.types';
import { ParcelMap } from '../components/ParcelMap';
import { parcelCoordinates } from '../components/patrimoineView';
import '../../../../../styles/pages/admin_immobilier/patrimoine/_patrimoine.scss';

interface ParcelMapNavigationState {
    parcelIds?: unknown;
    initialParcelId?: unknown;
    returnTo?: unknown;
}

function navigationState(value: unknown): ParcelMapNavigationState {
    return value !== null && typeof value === 'object' ? value as ParcelMapNavigationState : {};
}

/** Carte plein écran ; le catalogue reste borné par l’API et le rôle courant. */
export function ParcelMapPage() {
    const { currentOrganization, organizationRole } = useOrganization();
    const { activeCityUuid, isLoading: isCityLoading } = useAdminCity();
    const location = useLocation();
    const navigate = useNavigate();
    const state = navigationState(location.state);
    const [parcelsPage, setParcelsPage] = useState<ListPage<ParcelItem> | null>(null);
    const [isLoading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [reloadVersion, setReloadVersion] = useState(0);

    useEffect(() => {
        let active = true;
        const organizationUuid = currentOrganization?.uuid;
        if (!organizationUuid) {
            setParcelsPage(null);
            setError('Aucune organisation active.');
            setLoading(false);
            return () => { active = false; };
        }
        if (organizationRole === 'admin_ville' && (isCityLoading || !activeCityUuid)) {
            setParcelsPage(null);
            setError(null);
            setLoading(isCityLoading);
            return () => { active = false; };
        }

        setLoading(true);
        setError(null);
        void fetchParcels(organizationUuid).then((next) => {
            if (active) setParcelsPage(next);
        }).catch((cause: unknown) => {
            if (active) setError(cause instanceof ApiError ? cause.message : 'Impossible de charger les parcelles de votre espace.');
        }).finally(() => {
            if (active) setLoading(false);
        });

        return () => { active = false; };
    }, [currentOrganization?.uuid, organizationRole, activeCityUuid, isCityLoading, reloadVersion]);

    const parcels = useMemo(() => {
        const ids = Array.isArray(state.parcelIds)
            ? new Set(state.parcelIds.filter((id): id is string => typeof id === 'string'))
            : null;
        return (parcelsPage?.items ?? []).filter((parcel) => {
            if (organizationRole === 'admin_ville' && parcel.cityId !== activeCityUuid) return false;
            return ids === null || ids.has(parcel.id);
        }).flatMap((parcel) => {
            const coordinates = parcelCoordinates(parcel);
            return coordinates ? [{ ...parcel, ...coordinates }] : [];
        });
    }, [parcelsPage, organizationRole, activeCityUuid, state.parcelIds]);

    const requestedInitialId = typeof state.initialParcelId === 'string'
        ? state.initialParcelId
        : new URLSearchParams(location.search).get('parcelUuid');
    const initialParcelId = parcels.some((parcel) => parcel.id === requestedInitialId) ? requestedInitialId : parcels[0]?.id ?? null;
    const requestedReturnTo = typeof state.returnTo === 'string' ? state.returnTo : null;
    const returnTo = requestedReturnTo?.startsWith('/app/patrimoine') && !requestedReturnTo.startsWith('/app/patrimoine/carte')
        ? requestedReturnTo
        : '/app/patrimoine';

    return (
        <main className="organization-page organization-operational-page parcel-map-page">
            <header className="organization-page__header">
                <div><span className="organization-page__eyebrow">GESTION IMMOBILIÈRE</span><h1>Carte des parcelles</h1><p>Repérez les parcelles géolocalisées de votre organisation.</p></div>
                <Button variant="outline" onClick={() => navigate(returnTo, { state: { restorePatrimoineTab: 'manage' } })}>
                    {requestedReturnTo ? '← Retour à Gérer les parcelles' : '← Retour au patrimoine'}
                </Button>
            </header>
            {isLoading ? (
                <EmptyState icon={<Spinner size="large" />} title="Chargement de la carte…" description="Récupération des parcelles autorisées pour votre espace." />
            ) : error ? (
                <EmptyState title="Carte indisponible" description={error} action={<Button onClick={() => setReloadVersion((version) => version + 1)}>Réessayer</Button>} />
            ) : parcels.length === 0 ? (
                <EmptyState title="Aucune parcelle géolocalisée" description="Ajoutez des coordonnées GPS à une parcelle pour l’afficher sur la carte." />
            ) : (
                <Card className="parcel-map-page__surface" padding="medium">
                    <ParcelMap parcels={parcels} initialParcelId={initialParcelId} />
                </Card>
            )}
        </main>
    );
}
