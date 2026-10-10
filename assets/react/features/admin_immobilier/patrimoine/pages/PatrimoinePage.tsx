import { OrganizationSummary } from '../../../shared/components/OrganizationSummary';
import { Card } from '../../../../components/UI/Card';
import { Button } from '../../../../components/UI/Button';
import { Spinner } from '../../../../components/UI/Spinner';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { Tabs } from '../../../../components/Navigation/Tabs';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { formatInteger, formatPercent } from '../../../../../utils/format.utils';
import { usePatrimoine } from '../hooks/usePatrimoine';
import { canDo } from '../../../shared/permissions';
import { PatrimoineTable } from '../components/PatrimoineTable';
import { VillesTab } from '../components/VillesTab';
import { ParcellesTab } from '../components/ParcellesTab';
import { BatimentsTab } from '../components/BatimentsTab';
import { UnitesTab } from '../components/UnitesTab';
import '../../../../../styles/pages/admin_immobilier/patrimoine/_patrimoine.scss';

/**
 * Patrimoine : vue d'ensemble (lecture) + gestion des quatre niveaux.
 *
 * La page orchestre : elle charge via `usePatrimoine`, affiche le bandeau
 * d'indicateurs, puis délègue chaque niveau à son onglet. Les onglets portent
 * leur propre état de formulaire ; la page ne détient que les handlers
 * d'écriture du hook.
 */
export function PatrimoinePage() {
    const { organizationRole } = useOrganization();
    const location = useLocation();
    const navigate = useNavigate();
    const pathParts = location.pathname.split('/').filter(Boolean);
    const cityPart = pathParts.indexOf('villes');
    const parcelPart = pathParts.indexOf('parcelles');
    const buildingPart = pathParts.indexOf('batiments');
    const selectedCityId = cityPart >= 0 ? pathParts[cityPart + 1] ?? null : null;
    const selectedParcelId = parcelPart >= 0 ? pathParts[parcelPart + 1] ?? null : null;
    const selectedBuildingId = buildingPart >= 0 ? pathParts[buildingPart + 1] ?? null : null;
    const activeTab = pathParts.includes('unites') || selectedBuildingId ? 'unites'
        : selectedParcelId ? 'batiments'
            : pathParts.includes('batiments') ? 'batiments'
                : selectedCityId ? 'parcelles'
                    : pathParts.includes('parcelles') ? 'parcelles'
                        : pathParts.includes('villes') ? 'villes' : 'overview';
    const goToLevel = (tab: string, cityId: string | null, parcelId: string | null, buildingId: string | null) => {
        const path = ['/app/patrimoine'];
        if (cityId) path.push('villes', encodeURIComponent(cityId));
        if (parcelId) path.push('parcelles', encodeURIComponent(parcelId));
        if (buildingId) path.push('batiments', encodeURIComponent(buildingId));
        if (tab === 'unites') path.push('unites');
        if (path.length === 1 && tab !== 'overview') path.push(tab === 'villes' ? 'villes' : tab === 'parcelles' ? 'parcelles' : tab === 'batiments' ? 'batiments' : 'unites');
        navigate(path.join('/'));
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
        addParcelPhotos,
        deleteParcelPhoto,
        createBuilding,
        updateBuilding,
        deleteBuilding,
        createUnit,
        updateUnit,
        deleteUnit,
        publishUnit,
    } = usePatrimoine();

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

    const note = data?.note;
    const selectedCity = cities.find((item) => item.id === selectedCityId);
    const selectedParcel = parcels.find((item) => item.id === selectedParcelId);
    const selectedBuilding = buildings.find((item) => item.id === selectedBuildingId);
    const contextParcel = selectedParcel ?? parcels.find((item) => item.id === selectedBuilding?.parcelId);
    const openParcel = (id: string | null) => {
        const parcel = parcels.find((item) => item.id === id);
        goToLevel(id ? 'batiments' : 'parcelles', parcel?.cityId ?? selectedCityId, id, null);
    };
    const openBuilding = (id: string | null) => {
        const building = buildings.find((item) => item.id === id);
        const parcel = parcels.find((item) => item.id === building?.parcelId);
        goToLevel('unites', parcel?.cityId ?? selectedCityId, parcel?.id ?? selectedParcelId, id);
    };

    return <main className="organization-page organization-operational-page"><header className="organization-page__header"><div><span className="organization-page__eyebrow">GESTION IMMOBILIÈRE</span><h1>Patrimoine</h1><p>Parcourez les biens de votre portefeuille et gérez chaque niveau : villes, parcelles, bâtiments et unités.</p></div></header>
        <OrganizationSummary items={[{ label: 'Villes', value: <>{formatInteger(cities.length)}</> }, { label: 'Unités gérées', value: <>{formatInteger(data?.totalUnits ?? 0)}</> }, { label: 'Occupation globale', value: <>{formatPercent(data?.globalOccupancyRate ?? 0)}</> }]} />

        {(selectedCity || selectedParcel || selectedBuilding) && <nav className="patrimoine-breadcrumb" aria-label="Fil d’Ariane du patrimoine"><button type="button" onClick={() => goToLevel('villes', null, null, null)}>Toutes les villes</button>{selectedCity && <><span aria-hidden="true">/</span><button type="button" onClick={() => goToLevel('parcelles', selectedCity.id, null, null)}>{selectedCity.name}</button></>}{selectedParcel && <><span aria-hidden="true">/</span><button type="button" onClick={() => goToLevel('batiments', selectedCity?.id ?? selectedParcel.cityId, selectedParcel.id, null)}>{selectedParcel.name}</button></>}{selectedBuilding && <><span aria-hidden="true">/</span><button type="button" onClick={() => goToLevel('unites', selectedCity?.id ?? null, selectedParcel?.id ?? null, selectedBuilding.id)}>{selectedBuilding.name}</button></>}</nav>}
        {(contextParcel || selectedBuilding) && <nav className="patrimoine-related" aria-label="Accès rapide aux informations associées"><strong>Accès rapide · {selectedBuilding?.name ?? contextParcel?.name}</strong>{canDo(organizationRole, 'view_tenant') && <Link to={`/app/location/locataires?parcelUuid=${encodeURIComponent(contextParcel?.id ?? '')}${selectedBuilding ? `&buildingUuid=${encodeURIComponent(selectedBuilding.id)}` : ''}`}>Locataires et contrats</Link>}{canDo(organizationRole, 'view_rent') && <Link to={`/app/location/loyers?parcelUuid=${encodeURIComponent(contextParcel?.id ?? '')}${selectedBuilding ? `&buildingUuid=${encodeURIComponent(selectedBuilding.id)}` : ''}`}>Loyers et paiements</Link>}{canDo(organizationRole, 'view_expense') && <Link to={`/app/depenses?parcelUuid=${encodeURIComponent(contextParcel?.id ?? '')}${selectedBuilding ? `&buildingUuid=${encodeURIComponent(selectedBuilding.id)}` : ''}`}>Dépenses</Link>}{canDo(organizationRole, 'view_worker') && <Link to={`/app/personnel?parcelUuid=${encodeURIComponent(contextParcel?.id ?? '')}${selectedBuilding ? `&buildingUuid=${encodeURIComponent(selectedBuilding.id)}` : ''}`}>Employés</Link>}</nav>}

        <Tabs
            key={activeTab}
            tabs={[
                { id: 'overview', label: 'Vue d’ensemble' },
                { id: 'villes', label: 'Villes' },
                { id: 'parcelles', label: 'Parcelles' },
                { id: 'batiments', label: 'Bâtiments' },
                { id: 'unites', label: 'Unités' },
            ]}
            defaultActiveTabId={activeTab}
            renderContent={(activeTabId) => {
                if (activeTabId === 'villes') {
                    return <VillesTab cities={cities} role={organizationRole} isLoading={isLoading} error={error} note={note} onReload={() => void reload()} onCreate={createCity} onCreateAdmin={createCityAdmin} onUpdate={updateCity} onSetStatus={setCityStatus} onDelete={deleteCity} onOpen={(id) => goToLevel('parcelles', id, null, null)} />;
                }

                if (activeTabId === 'parcelles') {
                    return <ParcellesTab parcels={parcels} cities={cities} role={organizationRole} isLoading={isLoading} error={error} note={note} onReload={() => void reload()} onCreate={createParcel} onUpdate={updateParcel} onDelete={deleteParcel} onAddPhotos={addParcelPhotos} onDeletePhoto={deleteParcelPhoto} selectedCityId={selectedCityId} onCityFilter={(id) => goToLevel(id ? 'parcelles' : 'parcelles', id, null, null)} onOpen={(id) => { const parcel = parcels.find((item) => item.id === id); goToLevel('batiments', parcel?.cityId ?? selectedCityId, id, null); }} />;
                }

                if (activeTabId === 'batiments') {
                    return <BatimentsTab buildings={buildings} parcels={parcels} cities={cities} role={organizationRole} isLoading={isLoading} error={error} note={note} onReload={() => void reload()} onCreate={createBuilding} onUpdate={updateBuilding} onDelete={deleteBuilding} selectedParcelId={selectedParcelId} onParcelFilter={openParcel} onOpen={(id) => openBuilding(id)} />;
                }

                if (activeTabId === 'unites') {
                    return <UnitesTab units={units} buildings={buildings} role={organizationRole} isLoading={isLoading} error={error} note={note} onReload={() => void reload()} onCreate={createUnit} onUpdate={updateUnit} onDelete={deleteUnit} onPublish={publishUnit} selectedBuildingId={selectedBuildingId} onBuildingFilter={openBuilding} />;
                }

                return (
                    <Card className="organization-table-card" padding="medium">
                        <div className="organization-table-toolbar">
                            <div><h2>Vos biens</h2><p>{note ?? 'Parcourez les parcelles et immeubles de votre organisation et leur occupation.'}</p></div>
                            <div className="organization-filters">
                                <SearchInput value={search} onSearch={setSearch} placeholder="Rechercher un bien…" fullWidth />
                                <Select aria-label="Filtrer par ville" value={city} onChange={(event) => setCity(event.target.value)} options={[{ value: 'all', label: 'Toutes les villes' }, ...(data?.availableCities ?? []).map((value) => ({ value, label: value }))]} />
                                <Select aria-label="Filtrer par type" value={kind} onChange={(event) => setKind(event.target.value)} options={[{ value: 'all', label: 'Tous les types' }, { value: 'Parcelle', label: 'Parcelle' }, { value: 'Bâtiment', label: 'Bâtiment' }]} />
                            </div>
                        </div>
                        {overviewBody}
                    </Card>
                );
            }}
        />
    </main>;
}
