import { useState } from 'react';

import { OrganizationSummary } from '../../../shared/components/OrganizationSummary';
import { Card } from '../../../../components/UI/Card';
import { Button } from '../../../../components/UI/Button';
import { Badge } from '../../../../components/UI/Badge';
import { Modal } from '../../../../components/UI/Modal';
import { Spinner } from '../../../../components/UI/Spinner';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { RightSidebar } from '../../../../components/Navigation/RightSidebar';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { formatInteger, formatMoney, formatPercent } from '../../../../../utils/format.utils';
import { usePatrimoine } from '../hooks/usePatrimoine';
import { canDo } from '../../../shared/permissions';
import { PhotoViewer } from '../../../shared/media/components/PhotoViewer';
import { PatrimoineTable } from '../components/PatrimoineTable';
import { VillesTab } from '../components/VillesTab';
import { ParcellesTab } from '../components/ParcellesTab';
import { BatimentsTab } from '../components/BatimentsTab';
import { UnitesTab } from '../components/UnitesTab';
import { PatrimoineNav, buildPatrimoinePath, resolvePatrimoineSelection, type PatrimoineLevel } from '../components/PatrimoineNav';
import { PatrimoineSummaryCard } from '../components/PatrimoineSummaryCard';
import { PatrimoineChildList, type PatrimoineChildItem } from '../components/PatrimoineChildList';
import { Tabs } from '../../../../components/Navigation/Tabs';
import {
    buildingIndex,
    buildingOccupancy,
    buildingsOfCity,
    buildingsOfParcel,
    buildingTypeLabel,
    cityOccupancy,
    parcelCoordinates,
    parcelIndex,
    parcelOccupancy,
    parcelsOfCity,
    unitCount,
    unitsOfBuilding,
    unitTypeLabel,
} from '../components/patrimoineView';
import type { ParcelItem } from '../../shared/types/reference.types';
import '../../../../../styles/pages/admin_immobilier/patrimoine/_patrimoine.scss';

/** Surface affichée en m² à partir d'une chaîne décimale (jamais un montant). */
function squareMeters(value: string): string {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? `${new Intl.NumberFormat('fr-CD', { maximumFractionDigits: 2 }).format(parsed)} m²` : `${value} m²`;
}

/**
 * Patrimoine : vue d'ensemble (lecture) + gestion des quatre niveaux.
 *
 * La page orchestre : elle charge via `usePatrimoine`, affiche le bandeau
 * d'indicateurs, puis, pour chaque niveau sélectionné, une carte de synthèse et
 * les éléments enfants sous forme de cartes navigables, avant le tableau de
 * gestion. Le niveau actif et le contexte de drill-down sont lus dans l'URL ;
 * la navigation verticale (panneau droit) et le fil d'Ariane ne font que des
 * liens vers ces URL, ce qui garde chaque vue partageable et le bouton Retour
 * fonctionnel.
 *
 * Tous les indicateurs sont dérivés des listes déjà chargées par le hook et du
 * rapport d'occupation : aucune vue ne déclenche d'appel supplémentaire, aucun
 * montant n'est recalculé. Les actions sont gatées par `canDo` / `readOnly` ;
 * l'API demeure l'autorité.
 *
 * `readOnly` (espace PATRON) : les écrans de niveau n'exposent aucune écriture
 * et le lien « Employés » (absent de l'espace Patron) est écarté.
 */
export function PatrimoinePage({ readOnly = false }: { readOnly?: boolean } = {}) {
    const { organizationRole } = useOrganization();
    const location = useLocation();
    const navigate = useNavigate();
    const isCityAdmin = organizationRole === 'admin_ville';
    const selection = resolvePatrimoineSelection(location.pathname, isCityAdmin);
    const { level: activeLevel, cityId: selectedCityId, parcelId: selectedParcelId, buildingId: selectedBuildingId } = selection;

    const [photoParcel, setPhotoParcel] = useState<ParcelItem | null>(null);

    const goToLevel = (level: PatrimoineLevel, cityId: string | null, parcelId: string | null, buildingId: string | null) => {
        navigate(buildPatrimoinePath(level, { cityId, parcelId, buildingId }));
    };
    const {
        data,
        rows,
        cities,
        parcels,
        buildings,
        units,
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
        createBuilding,
        updateBuilding,
        deleteBuilding,
        createUnit,
        updateUnit,
        deleteUnit,
        publishUnit,
    } = usePatrimoine();

    const note = data?.note;
    const cityBy = new Map(cities.map((entry) => [entry.id, entry]));
    const parcelBy = parcelIndex(data);
    const buildingBy = buildingIndex(data);
    const selectedCity = selectedCityId ? cityBy.get(selectedCityId) : undefined;
    const selectedParcel = selectedParcelId ? parcelBy.get(selectedParcelId) : undefined;
    const selectedBuilding = selectedBuildingId ? buildingBy.get(selectedBuildingId) : undefined;
    const contextParcel = selectedParcel ?? parcelBy.get(selectedBuilding?.parcelId ?? '');
    const contextCity = contextParcel ? cityBy.get(contextParcel.cityId) : selectedCity;
    const openParcel = (id: string | null) => {
        const parcel = parcels.find((item) => item.id === id);
        goToLevel(id ? 'batiments' : 'parcelles', parcel?.cityId ?? selectedCityId, id, null);
    };
    const openBuilding = (id: string | null) => {
        const building = buildings.find((item) => item.id === id);
        const parcel = parcels.find((item) => item.id === building?.parcelId);
        goToLevel('unites', parcel?.cityId ?? selectedCityId, parcel?.id ?? selectedParcelId, id);
    };

    const dataReady = !isLoading && !error && data !== null;

    let overviewBody = <PatrimoineTable rows={rows} />;
    if (isLoading) {
        overviewBody = <EmptyState icon={<Spinner size="large" />} title="Chargement du patrimoine…" description="Récupération du catalogue et du rapport d’occupation." />;
    } else if (error) {
        overviewBody = <EmptyState title="Patrimoine indisponible" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} />;
    } else if (rows.length === 0) {
        overviewBody = <EmptyState
            title="Aucun bien à afficher"
            description={data && data.rows.length > 0 ? 'Aucun bien ne correspond à la recherche ou aux filtres sélectionnés.' : 'Votre organisation n’a encore aucune parcelle ou immeuble enregistré.'}
        />;
    }

    /** Vue contextuelle de la ville sélectionnée : synthèse + parcelles. */
    const cityView = selectedCity && (
        <>
            <PatrimoineSummaryCard
                eyebrow="VILLE"
                title={selectedCity.name}
                badges={<Badge variant={selectedCity.status === 'active' ? 'success' : 'secondary'}>{selectedCity.status === 'active' ? 'Active' : 'Inactive'}</Badge>}
                subtitle="Structure du patrimoine rattaché à cette ville."
                fields={(() => {
                    const cityParcels = parcelsOfCity(data, selectedCity.id);
                    const cityBuildings = buildingsOfCity(data, selectedCity.id);
                    const occupancy = cityOccupancy(data, selectedCity.id);
                    const cityUnits = occupancy ? occupancy.totalUnits : cityBuildings.reduce((total, building) => total + unitsOfBuilding(data, building.id).length, 0);
                    return [
                        { label: 'Code', value: selectedCity.code },
                        { label: 'Province', value: selectedCity.province || '—' },
                        { label: 'Pays', value: selectedCity.country || '—' },
                        { label: 'Parcelles', value: formatInteger(cityParcels.length) },
                        { label: 'Bâtiments', value: formatInteger(cityBuildings.length) },
                        { label: 'Unités', value: formatInteger(cityUnits) },
                        { label: 'Occupation', value: occupancy ? formatPercent(occupancy.occupancyRate) : '—' },
                    ];
                })()}
            />
            <PatrimoineChildList
                title="Parcelles de la ville"
                description="Ouvrez une parcelle pour accéder à ses bâtiments."
                items={parcelsOfCity(data, selectedCity.id).map((parcel) => {
                    const occupancy = parcelOccupancy(data, parcel.id);
                    return {
                        id: parcel.id,
                        title: parcel.name,
                        subtitle: parcel.reference,
                        meta: [
                            { label: 'Adresse', value: [parcel.quarter, parcel.address].filter(Boolean).join(' · ') || '—' },
                            { label: 'Superficie', value: squareMeters(parcel.area) },
                            { label: 'Occupation', value: occupancy ? formatPercent(occupancy.occupancyRate) : '—' },
                        ],
                        to: buildPatrimoinePath('batiments', { cityId: selectedCity.id, parcelId: parcel.id, buildingId: null }),
                    } satisfies PatrimoineChildItem;
                })}
                emptyTitle="Aucune parcelle"
                emptyDescription="Ajoutez une parcelle à cette ville pour commencer à la structurer."
                ariaLabel={`Parcelles de ${selectedCity.name}`}
            />
        </>
    );

    /** Vue contextuelle de la parcelle sélectionnée : synthèse + bâtiments. */
    const parcelView = selectedParcel && (
        <>
            <PatrimoineSummaryCard
                eyebrow="PARCELLE"
                title={selectedParcel.name}
                subtitle={contextCity ? `Ville de ${contextCity.name}` : undefined}
                fields={(() => {
                    const parcelBuildings = buildingsOfParcel(data, selectedParcel.id);
                    const occupancy = parcelOccupancy(data, selectedParcel.id);
                    const parcelUnits = occupancy ? occupancy.totalUnits : parcelBuildings.reduce((total, building) => total + unitsOfBuilding(data, building.id).length, 0);
                    const coordinates = parcelCoordinates(selectedParcel);
                    return [
                        { label: 'Référence', value: selectedParcel.reference },
                        { label: 'Ville', value: contextCity?.name ?? '—' },
                        { label: 'Adresse', value: selectedParcel.address || '—' },
                        { label: 'Quartier', value: selectedParcel.quarter || '—' },
                        { label: 'Superficie', value: squareMeters(selectedParcel.area) },
                        { label: 'N° de titre', value: selectedParcel.titleNumber || '—' },
                        { label: 'Coordonnées', value: coordinates ? `${coordinates.latitude}, ${coordinates.longitude}` : 'GPS absent' },
                        { label: 'Bâtiments', value: formatInteger(parcelBuildings.length) },
                        { label: 'Unités', value: formatInteger(parcelUnits) },
                        { label: 'Occupation', value: occupancy ? formatPercent(occupancy.occupancyRate) : '—' },
                    ];
                })()}
                actions={<>
                    {canDo(organizationRole, 'view_parcel') && <Button variant="outline" onClick={() => setPhotoParcel(selectedParcel)}>Voir les photos</Button>}
                </>}
            />
            <PatrimoineChildList
                title="Bâtiments de la parcelle"
                description="Ouvrez un bâtiment pour consulter et gérer ses unités."
                items={buildingsOfParcel(data, selectedParcel.id).map((building) => {
                    const occupancy = buildingOccupancy(data, building.id);
                    return {
                        id: building.id,
                        title: building.name,
                        subtitle: building.reference,
                        meta: [
                            { label: 'Type', value: buildingTypeLabel(building.type) },
                            { label: 'Niveaux', value: building.numberOfFloors !== null ? String(building.numberOfFloors) : '—' },
                            { label: 'Occupation', value: occupancy ? formatPercent(occupancy.occupancyRate) : '—' },
                        ],
                        to: buildPatrimoinePath('unites', { cityId: contextCity?.id ?? selectedCityId, parcelId: selectedParcel.id, buildingId: building.id }),
                    } satisfies PatrimoineChildItem;
                })}
                emptyTitle="Aucun bâtiment"
                emptyDescription="Ajoutez un bâtiment à cette parcelle pour héberger des unités."
                ariaLabel={`Bâtiments de ${selectedParcel.name}`}
            />
        </>
    );

    /** Vue contextuelle du bâtiment sélectionné : synthèse + unités. */
    const buildingView = selectedBuilding && (
        <>
            <PatrimoineSummaryCard
                eyebrow="BÂTIMENT"
                title={selectedBuilding.name}
                subtitle={contextParcel ? `Parcelle ${contextParcel.name}${contextCity ? ` · ${contextCity.name}` : ''}` : undefined}
                fields={(() => {
                    const buildingUnits = unitsOfBuilding(data, selectedBuilding.id);
                    const occupancy = buildingOccupancy(data, selectedBuilding.id);
                    return [
                        { label: 'Référence', value: selectedBuilding.reference },
                        { label: 'Type', value: buildingTypeLabel(selectedBuilding.type) },
                        { label: 'Niveaux', value: selectedBuilding.numberOfFloors !== null ? String(selectedBuilding.numberOfFloors) : '—' },
                        { label: 'Parcelle', value: contextParcel?.name ?? '—' },
                        { label: 'Ville', value: contextCity?.name ?? '—' },
                        { label: 'Unités', value: formatInteger(unitCount(occupancy, buildingUnits)) },
                        { label: 'Publiées', value: formatInteger(buildingUnits.filter((unit) => unit.isPublished).length) },
                        { label: 'Occupation', value: occupancy ? formatPercent(occupancy.occupancyRate) : '—' },
                    ];
                })()}
            >
                <p className="patrimoine-summary__hint">
                    Locataires, baux et employés affectés sont gérés dans leurs pages dédiées ; utilisez les accès rapides ci-dessus, filtrés sur ce bâtiment.
                </p>
            </PatrimoineSummaryCard>
            <PatrimoineChildList
                title="Unités du bâtiment"
                description="Informations principales ; la gestion se fait dans la liste détaillée ci-dessous."
                items={unitsOfBuilding(data, selectedBuilding.id).map((unit) => ({
                    id: unit.id,
                    title: unit.reference,
                    subtitle: `${unitTypeLabel(unit.type)} · niveau ${unit.floor}`,
                    meta: [
                        { label: 'Surface', value: squareMeters(unit.surface) },
                        { label: 'Chambres', value: unit.bedrooms === null ? '—' : formatInteger(unit.bedrooms) },
                        { label: 'Pièces', value: unit.rooms === null ? '—' : formatInteger(unit.rooms) },
                        { label: 'Salles de bain', value: unit.bathrooms === null ? '—' : formatInteger(unit.bathrooms) },
                        { label: 'Loyer mensuel', value: formatMoney(unit.monthlyRent, unit.currency) },
                        { label: 'Vitrine', value: <Badge variant={unit.isPublished ? 'success' : 'secondary'}>{unit.isPublished ? 'Publiée' : 'Non publiée'}</Badge> },
                    ],
                }))}
                emptyTitle="Aucune unité"
                emptyDescription="Ajoutez une unité à ce bâtiment pour commencer à la louer."
                ariaLabel={`Unités de ${selectedBuilding.name}`}
            />
        </>
    );

    let contextView = null;
    if (dataReady && activeLevel === 'unites' && selectedBuilding) contextView = buildingView;
    else if (dataReady && activeLevel === 'batiments' && selectedParcel) contextView = parcelView;
    else if (dataReady && activeLevel === 'parcelles' && selectedCity) contextView = cityView;

    const renderTab = (level: PatrimoineLevel) => {
        if (level === 'villes') {
            return <VillesTab cities={cities} role={organizationRole} isLoading={isLoading} error={error} note={note} onReload={() => void reload()} onCreate={createCity} onCreateAdmin={createCityAdmin} onUpdate={updateCity} onSetStatus={setCityStatus} onDelete={deleteCity} onOpen={(id) => goToLevel('parcelles', id, null, null)} readOnly={readOnly} />;
        }
        if (level === 'parcelles') {
            return <ParcellesTab parcels={parcels} cities={cities} role={organizationRole} isLoading={isLoading} error={error} note={note} onReload={() => void reload()} onCreate={createParcel} onUpdate={updateParcel} onDelete={deleteParcel} selectedCityId={selectedCityId} onCityFilter={(id) => goToLevel('parcelles', id, null, null)} onOpen={(id) => openParcel(id)} readOnly={readOnly} />;
        }
        if (level === 'batiments') {
            return <BatimentsTab buildings={buildings} parcels={parcels} cities={cities} role={organizationRole} isLoading={isLoading} error={error} note={note} onReload={() => void reload()} onCreate={createBuilding} onUpdate={updateBuilding} onDelete={deleteBuilding} selectedParcelId={selectedParcelId} onParcelFilter={openParcel} onOpen={(id) => openBuilding(id)} readOnly={readOnly} />;
        }
        return <UnitesTab units={units} buildings={buildings} role={organizationRole} isLoading={isLoading} error={error} note={note} onReload={() => void reload()} onCreate={createUnit} onUpdate={updateUnit} onDelete={deleteUnit} onPublish={publishUnit} selectedBuildingId={selectedBuildingId} onBuildingFilter={openBuilding} readOnly={readOnly} />;
    };

    const overviewCities = isLoading ? (
        <EmptyState icon={<Spinner size="large" />} title="Chargement des villes…" description="Récupération du patrimoine de l’organisation." />
    ) : error ? (
        <EmptyState title="Villes indisponibles" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} />
    ) : (
        <PatrimoineChildList
            title="Villes du portefeuille"
            description="Choisissez une ville pour parcourir son patrimoine."
            items={cities.map((entry) => {
                const occupancy = cityOccupancy(data, entry.id);
                return {
                    id: entry.id,
                    title: entry.name,
                    subtitle: [entry.code, entry.province || entry.country].filter(Boolean).join(' · '),
                    meta: [
                        { label: 'Parcelles', value: formatInteger(parcelsOfCity(data, entry.id).length) },
                        { label: 'Bâtiments', value: formatInteger(buildingsOfCity(data, entry.id).length) },
                        { label: 'Occupation', value: occupancy ? formatPercent(occupancy.occupancyRate) : '—' },
                    ],
                    to: buildPatrimoinePath('parcelles', { cityId: entry.id, parcelId: null, buildingId: null }),
                };
            })}
            emptyTitle="Aucune ville"
            emptyDescription="Ajoutez une première ville pour structurer votre patrimoine."
            ariaLabel="Villes du portefeuille"
        />
    );

    const overviewProperties = (
        <Card className="organization-table-card" padding="medium">
            <div className="organization-table-toolbar">
                <div><h2>Biens du portefeuille</h2><p>{note ?? 'Recherchez et filtrez les parcelles et bâtiments de votre organisation.'}</p></div>
                <div className="organization-filters">
                    <SearchInput value={search} onSearch={setSearch} placeholder="Rechercher un bien…" fullWidth />
                    <Select aria-label="Filtrer par ville" value={city} onChange={(event) => setCity(event.target.value)} options={[{ value: 'all', label: 'Toutes les villes' }, ...(data?.availableCities ?? []).map((value) => ({ value, label: value }))]} />
                    <Select aria-label="Filtrer par type" value={kind} onChange={(event) => setKind(event.target.value)} options={[{ value: 'all', label: 'Tous les types' }, { value: 'Parcelle', label: 'Parcelle' }, { value: 'Bâtiment', label: 'Bâtiment' }]} />
                </div>
            </div>
            {overviewBody}
        </Card>
    );

    let levelContent;
    if (activeLevel === 'overview') {
        levelContent = <Tabs
            key="portfolio-overview"
            variant="underline"
            tabs={[{ id: 'cities', label: 'Villes' }, { id: 'properties', label: 'Tous les biens' }]}
            defaultActiveTabId="cities"
            renderContent={(tabId) => tabId === 'cities' ? overviewCities : overviewProperties}
        />;
    } else if (contextView) {
        const managementLabel = activeLevel === 'parcelles' ? 'Gérer les parcelles'
            : activeLevel === 'batiments' ? 'Gérer les bâtiments'
                : activeLevel === 'unites' ? 'Gérer les unités' : 'Gérer le patrimoine';
        levelContent = <Tabs
            key={`${activeLevel}:${selectedCityId ?? ''}:${selectedParcelId ?? ''}:${selectedBuildingId ?? ''}`}
            variant="underline"
            tabs={[{ id: 'overview', label: 'Aperçu' }, { id: 'manage', label: managementLabel }]}
            defaultActiveTabId="overview"
            renderContent={(tabId) => tabId === 'overview' ? contextView : renderTab(activeLevel)}
        />;
    } else {
        levelContent = renderTab(activeLevel);
    }

    return (
        <div className="patrimoine-layout">
            <main className="patrimoine-layout__main organization-page organization-operational-page">
                <header className="organization-page__header"><div><span className="organization-page__eyebrow">GESTION IMMOBILIÈRE</span><h1>{isCityAdmin ? 'Biens de ma ville' : 'Patrimoine'}</h1><p>{isCityAdmin ? 'Consultez et gérez les parcelles, bâtiments et unités de vos villes attribuées.' : 'Parcourez les biens de votre portefeuille et gérez chaque niveau : villes, parcelles, bâtiments et unités.'}</p></div></header>
                {!contextView && <OrganizationSummary items={isCityAdmin ? [{ label: 'Parcelles', value: <>{formatInteger(parcels.length)}</> }, { label: 'Unités gérées', value: <>{formatInteger(data?.totalUnits ?? 0)}</> }, { label: 'Occupation', value: <>{formatPercent(data?.globalOccupancyRate ?? 0)}</> }] : [{ label: 'Villes', value: <>{formatInteger(cities.length)}</> }, { label: 'Unités gérées', value: <>{formatInteger(data?.totalUnits ?? 0)}</> }, { label: 'Occupation globale', value: <>{formatPercent(data?.globalOccupancyRate ?? 0)}</> }]} />}

                {(selectedCity || selectedParcel || selectedBuilding) && <nav className="patrimoine-breadcrumb" aria-label="Fil d’Ariane du patrimoine"><button type="button" onClick={() => goToLevel(isCityAdmin ? 'parcelles' : 'villes', null, null, null)}>{isCityAdmin ? 'Toutes les parcelles' : 'Toutes les villes'}</button>{selectedCity && <><span aria-hidden="true">/</span>{isCityAdmin ? <span>{selectedCity.name}</span> : <button type="button" onClick={() => goToLevel('parcelles', selectedCity.id, null, null)}>{selectedCity.name}</button>}</>}{selectedParcel && <><span aria-hidden="true">/</span><button type="button" onClick={() => goToLevel('batiments', selectedCity?.id ?? selectedParcel.cityId, selectedParcel.id, null)}>{selectedParcel.name}</button></>}{selectedBuilding && <><span aria-hidden="true">/</span><button type="button" onClick={() => goToLevel('unites', selectedCity?.id ?? null, selectedParcel?.id ?? null, selectedBuilding.id)}>{selectedBuilding.name}</button></>}</nav>}
                {(contextParcel || selectedBuilding) && <nav className="patrimoine-related" aria-label="Accès rapide aux informations associées"><strong>Accès rapide · {selectedBuilding?.name ?? contextParcel?.name}</strong>{canDo(organizationRole, 'view_tenant') && <Link to={`/app/location/locataires?parcelUuid=${encodeURIComponent(contextParcel?.id ?? '')}${selectedBuilding ? `&buildingUuid=${encodeURIComponent(selectedBuilding.id)}` : ''}`}>Locataires et contrats</Link>}{canDo(organizationRole, 'view_rent') && <Link to={`/app/location/loyers?parcelUuid=${encodeURIComponent(contextParcel?.id ?? '')}${selectedBuilding ? `&buildingUuid=${encodeURIComponent(selectedBuilding.id)}` : ''}`}>Loyers et paiements</Link>}{canDo(organizationRole, 'view_expense') && <Link to={`/app/depenses?parcelUuid=${encodeURIComponent(contextParcel?.id ?? '')}${selectedBuilding ? `&buildingUuid=${encodeURIComponent(selectedBuilding.id)}` : ''}`}>Dépenses</Link>}{canDo(organizationRole, 'create_worker_assignment') && organizationRole !== 'patron' && <Link to={`${isCityAdmin ? '/app/operations' : '/app/personnel'}?parcelUuid=${encodeURIComponent(contextParcel?.id ?? '')}${selectedBuilding ? `&buildingUuid=${encodeURIComponent(selectedBuilding.id)}` : ''}`}>{isCityAdmin ? 'Affecter un employé' : 'Employés'}</Link>}</nav>}

                <div className="patrimoine-level" key={`${activeLevel}:${selectedCityId ?? ''}:${selectedParcelId ?? ''}:${selectedBuildingId ?? ''}`}>
                    {levelContent}
                </div>
            </main>

            <RightSidebar
                title="Explorer le patrimoine"
                size="large"
                collapsible
                collapsedWidth={48}
                className="patrimoine-layout__nav"
                collapsedContent={<PatrimoineNav isCityAdmin={isCityAdmin} selection={selection} cities={cities} parcels={parcels} buildings={buildings} units={units} collapsed />}
            >
                <PatrimoineNav isCityAdmin={isCityAdmin} selection={selection} cities={cities} parcels={parcels} buildings={buildings} units={units} />
            </RightSidebar>

            <Modal
                isOpen={photoParcel !== null}
                onClose={() => setPhotoParcel(null)}
                title={photoParcel ? `Photos — ${photoParcel.name}` : 'Photos de la parcelle'}
                size="large"
                footer={<Button variant="outline" onClick={() => setPhotoParcel(null)}>Fermer</Button>}
            >
                {photoParcel && <PhotoViewer entityUuid={photoParcel.id} entityType="parcel" entityName={photoParcel.name} />}
            </Modal>
        </div>
    );
}
