import { OrganizationSummary } from '../../../shared/components/OrganizationSummary';
import { Card } from '../../../../components/UI/Card';
import { Button } from '../../../../components/UI/Button';
import { Spinner } from '../../../../components/UI/Spinner';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { Tabs } from '../../../../components/Navigation/Tabs';
import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { formatInteger, formatPercent } from '../../../../../utils/format.utils';
import { usePatrimoine } from '../hooks/usePatrimoine';
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

    return <main className="organization-page organization-operational-page"><header className="organization-page__header"><div><span className="organization-page__eyebrow">GESTION IMMOBILIÈRE</span><h1>Patrimoine</h1><p>Parcourez les biens de votre portefeuille et gérez chaque niveau : villes, parcelles, bâtiments et unités.</p></div></header>
        <OrganizationSummary items={[{ label: 'Villes', value: <>{formatInteger(cities.length)}</> }, { label: 'Unités gérées', value: <>{formatInteger(data?.totalUnits ?? 0)}</> }, { label: 'Occupation globale', value: <>{formatPercent(data?.globalOccupancyRate ?? 0)}</> }]} />

        <Tabs
            tabs={[
                { id: 'overview', label: 'Vue d’ensemble' },
                { id: 'villes', label: 'Villes' },
                { id: 'parcelles', label: 'Parcelles' },
                { id: 'batiments', label: 'Bâtiments' },
                { id: 'unites', label: 'Unités' },
            ]}
            defaultActiveTabId="overview"
            renderContent={(activeTabId) => {
                if (activeTabId === 'villes') {
                    return <VillesTab cities={cities} role={organizationRole} isLoading={isLoading} error={error} note={note} onReload={() => void reload()} onCreate={createCity} onUpdate={updateCity} onSetStatus={setCityStatus} onDelete={deleteCity} />;
                }

                if (activeTabId === 'parcelles') {
                    return <ParcellesTab parcels={parcels} cities={cities} role={organizationRole} isLoading={isLoading} error={error} note={note} onReload={() => void reload()} onCreate={createParcel} onUpdate={updateParcel} onDelete={deleteParcel} onAddPhotos={addParcelPhotos} onDeletePhoto={deleteParcelPhoto} />;
                }

                if (activeTabId === 'batiments') {
                    return <BatimentsTab buildings={buildings} parcels={parcels} role={organizationRole} isLoading={isLoading} error={error} note={note} onReload={() => void reload()} onCreate={createBuilding} onUpdate={updateBuilding} onDelete={deleteBuilding} />;
                }

                if (activeTabId === 'unites') {
                    return <UnitesTab units={units} buildings={buildings} role={organizationRole} isLoading={isLoading} error={error} note={note} onReload={() => void reload()} onCreate={createUnit} onUpdate={updateUnit} onDelete={deleteUnit} onPublish={publishUnit} />;
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