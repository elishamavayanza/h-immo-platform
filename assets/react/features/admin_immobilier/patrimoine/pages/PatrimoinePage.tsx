import { OrganizationSummary } from '../../../shared/components/OrganizationSummary';
import { Card } from '../../../../components/UI/Card';
import { Button } from '../../../../components/UI/Button';
import { Spinner } from '../../../../components/UI/Spinner';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { RightSidebar } from '../../../../components/Navigation/RightSidebar';
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
import { PatrimoineNav, buildPatrimoinePath, resolvePatrimoineSelection } from '../components/PatrimoineNav';
import '../../../../../styles/pages/admin_immobilier/patrimoine/_patrimoine.scss';

/**
 * Patrimoine : vue d'ensemble (lecture) + gestion des quatre niveaux.
 *
 * La page orchestre : elle charge via `usePatrimoine`, affiche le bandeau
 * d'indicateurs, puis délègue chaque niveau à son écran dédié. Le niveau actif
 * et le contexte de drill-down sont lus dans l'URL ; la navigation verticale
 * (panneau droit) et le fil d'Ariane ne font que des liens vers ces URL, ce qui
 * garde chaque vue partageable et le bouton Retour fonctionnel.
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

    const goToLevel = (level: 'overview' | 'villes' | 'parcelles' | 'batiments' | 'unites', cityId: string | null, parcelId: string | null, buildingId: string | null) => {
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

    const levelContent = activeLevel === 'villes'
        ? <VillesTab cities={cities} role={organizationRole} isLoading={isLoading} error={error} note={note} onReload={() => void reload()} onCreate={createCity} onCreateAdmin={createCityAdmin} onUpdate={updateCity} onSetStatus={setCityStatus} onDelete={deleteCity} onOpen={(id) => goToLevel('parcelles', id, null, null)} readOnly={readOnly} />
        : activeLevel === 'parcelles'
            ? <ParcellesTab parcels={parcels} cities={cities} role={organizationRole} isLoading={isLoading} error={error} note={note} onReload={() => void reload()} onCreate={createParcel} onUpdate={updateParcel} onDelete={deleteParcel} selectedCityId={selectedCityId} onCityFilter={(id) => goToLevel('parcelles', id, null, null)} onOpen={(id) => { const parcel = parcels.find((item) => item.id === id); goToLevel('batiments', parcel?.cityId ?? selectedCityId, id, null); }} readOnly={readOnly} />
            : activeLevel === 'batiments'
                ? <BatimentsTab buildings={buildings} parcels={parcels} cities={cities} role={organizationRole} isLoading={isLoading} error={error} note={note} onReload={() => void reload()} onCreate={createBuilding} onUpdate={updateBuilding} onDelete={deleteBuilding} selectedParcelId={selectedParcelId} onParcelFilter={openParcel} onOpen={(id) => openBuilding(id)} readOnly={readOnly} />
                : activeLevel === 'unites'
                    ? <UnitesTab units={units} buildings={buildings} role={organizationRole} isLoading={isLoading} error={error} note={note} onReload={() => void reload()} onCreate={createUnit} onUpdate={updateUnit} onDelete={deleteUnit} onPublish={publishUnit} selectedBuildingId={selectedBuildingId} onBuildingFilter={openBuilding} readOnly={readOnly} />
                    : (
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

    return (
        <div className="patrimoine-layout">
            <main className="patrimoine-layout__main organization-page organization-operational-page">
                <header className="organization-page__header"><div><span className="organization-page__eyebrow">GESTION IMMOBILIÈRE</span><h1>{isCityAdmin ? 'Biens de ma ville' : 'Patrimoine'}</h1><p>{isCityAdmin ? 'Consultez et gérez les parcelles, bâtiments et unités de vos villes attribuées.' : 'Parcourez les biens de votre portefeuille et gérez chaque niveau : villes, parcelles, bâtiments et unités.'}</p></div></header>
                <OrganizationSummary items={isCityAdmin ? [{ label: 'Parcelles', value: <>{formatInteger(parcels.length)}</> }, { label: 'Unités gérées', value: <>{formatInteger(data?.totalUnits ?? 0)}</> }, { label: 'Occupation', value: <>{formatPercent(data?.globalOccupancyRate ?? 0)}</> }] : [{ label: 'Villes', value: <>{formatInteger(cities.length)}</> }, { label: 'Unités gérées', value: <>{formatInteger(data?.totalUnits ?? 0)}</> }, { label: 'Occupation globale', value: <>{formatPercent(data?.globalOccupancyRate ?? 0)}</> }]} />

                {(selectedCity || selectedParcel || selectedBuilding) && <nav className="patrimoine-breadcrumb" aria-label="Fil d’Ariane du patrimoine"><button type="button" onClick={() => goToLevel(isCityAdmin ? 'parcelles' : 'villes', null, null, null)}>{isCityAdmin ? 'Toutes les parcelles' : 'Toutes les villes'}</button>{selectedCity && <><span aria-hidden="true">/</span>{isCityAdmin ? <span>{selectedCity.name}</span> : <button type="button" onClick={() => goToLevel('parcelles', selectedCity.id, null, null)}>{selectedCity.name}</button>}</>}{selectedParcel && <><span aria-hidden="true">/</span><button type="button" onClick={() => goToLevel('batiments', selectedCity?.id ?? selectedParcel.cityId, selectedParcel.id, null)}>{selectedParcel.name}</button></>}{selectedBuilding && <><span aria-hidden="true">/</span><button type="button" onClick={() => goToLevel('unites', selectedCity?.id ?? null, selectedParcel?.id ?? null, selectedBuilding.id)}>{selectedBuilding.name}</button></>}</nav>}
                {(contextParcel || selectedBuilding) && <nav className="patrimoine-related" aria-label="Accès rapide aux informations associées"><strong>Accès rapide · {selectedBuilding?.name ?? contextParcel?.name}</strong>{canDo(organizationRole, 'view_tenant') && <Link to={`/app/location/locataires?parcelUuid=${encodeURIComponent(contextParcel?.id ?? '')}${selectedBuilding ? `&buildingUuid=${encodeURIComponent(selectedBuilding.id)}` : ''}`}>Locataires et contrats</Link>}{canDo(organizationRole, 'view_rent') && <Link to={`/app/location/loyers?parcelUuid=${encodeURIComponent(contextParcel?.id ?? '')}${selectedBuilding ? `&buildingUuid=${encodeURIComponent(selectedBuilding.id)}` : ''}`}>Loyers et paiements</Link>}{canDo(organizationRole, 'view_expense') && <Link to={`/app/depenses?parcelUuid=${encodeURIComponent(contextParcel?.id ?? '')}${selectedBuilding ? `&buildingUuid=${encodeURIComponent(selectedBuilding.id)}` : ''}`}>Dépenses</Link>}{canDo(organizationRole, 'create_worker_assignment') && organizationRole !== 'patron' && <Link to={`${isCityAdmin ? '/app/operations' : '/app/personnel'}?parcelUuid=${encodeURIComponent(contextParcel?.id ?? '')}${selectedBuilding ? `&buildingUuid=${encodeURIComponent(selectedBuilding.id)}` : ''}`}>{isCityAdmin ? 'Affecter un employé' : 'Employés'}</Link>}</nav>}

                <div className="patrimoine-level" key={activeLevel}>{levelContent}</div>
            </main>

            <RightSidebar
                title="Explorer le patrimoine"
                size="large"
                collapsible
                collapsedWidth={64}
                className="patrimoine-layout__nav"
                collapsedContent={<PatrimoineNav isCityAdmin={isCityAdmin} selection={selection} cities={cities} parcels={parcels} buildings={buildings} units={units} collapsed />}
            >
                <PatrimoineNav isCityAdmin={isCityAdmin} selection={selection} cities={cities} parcels={parcels} buildings={buildings} units={units} />
            </RightSidebar>
        </div>
    );
}
