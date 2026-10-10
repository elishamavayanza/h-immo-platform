import { OrganizationSummary } from '../../../shared/components/OrganizationSummary';
import { Card } from '../../../../components/UI/Card';
import { Button } from '../../../../components/UI/Button';
import { Spinner } from '../../../../components/UI/Spinner';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { formatInteger, formatPercent } from '../../../../../utils/format.utils';
import { usePatrimoine } from '../hooks/usePatrimoine';
import { PatrimoineTable } from '../components/PatrimoineTable';
import '../../../../../styles/pages/admin_immobilier/patrimoine/_patrimoine.scss';

export function PatrimoinePage() {
    const { data, rows, isLoading, error, reload, search, setSearch, city, setCity, kind, setKind } = usePatrimoine();

    let body = <PatrimoineTable rows={rows} />;
    if (isLoading) {
        body = <EmptyState icon={<Spinner size="large" />} title="Chargement du patrimoine…" description="Récupération du catalogue et du rapport d’occupation." />;
    } else if (error) {
        body = <EmptyState title="Patrimoine indisponible" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} />;
    } else if (rows.length === 0) {
        body = <EmptyState
            title="Aucun bien à afficher"
            description={data && data.rows.length > 0 ? 'Aucun bien ne correspond à la recherche ou aux filtres sélectionnés.' : 'Votre organisation n’a encore aucune parcelle ou immeuble enregistré.'}
        />;
    }

    return <main className="organization-page organization-operational-page"><header className="organization-page__header"><div><span className="organization-page__eyebrow">GESTION IMMOBILIÈRE</span><h1>Patrimoine</h1><p>Parcourez les biens de votre portefeuille et leur occupation.</p></div></header>
        <OrganizationSummary items={[{ label: 'Biens affichés', value: <>{rows.length}</> }, { label: 'Unités gérées', value: <>{formatInteger(data?.totalUnits ?? 0)}</> }, { label: 'Occupation globale', value: <>{formatPercent(data?.globalOccupancyRate ?? 0)}</> }]} />
        <Card className="organization-table-card" padding="medium"><div className="organization-table-toolbar"><div><h2>Vos biens</h2><p>{data?.note ?? 'Parcourez les parcelles et immeubles de votre organisation et leur occupation.'}</p></div><div className="organization-filters"><SearchInput value={search} onSearch={setSearch} placeholder="Rechercher un bien…" fullWidth /><Select aria-label="Filtrer par ville" value={city} onChange={(event) => setCity(event.target.value)} options={[{ value: 'all', label: 'Toutes les villes' }, ...(data?.availableCities ?? []).map((value) => ({ value, label: value }))]} /><Select aria-label="Filtrer par type" value={kind} onChange={(event) => setKind(event.target.value)} options={[{ value: 'all', label: 'Tous les types' }, { value: 'Parcelle', label: 'Parcelle' }, { value: 'Bâtiment', label: 'Bâtiment' }]} /></div></div>{body}</Card>
    </main>;
}
