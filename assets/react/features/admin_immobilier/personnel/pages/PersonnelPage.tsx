import { OrganizationSummary } from '../../../shared/components/OrganizationSummary';
import { Card } from '../../../../components/UI/Card';
import { Button } from '../../../../components/UI/Button';
import { Spinner } from '../../../../components/UI/Spinner';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { usePersonnel } from '../hooks/usePersonnel';
import { PersonnelTable } from '../components/PersonnelTable';
import '../../../../../styles/pages/admin_immobilier/personnel/_personnel.scss';

export function PersonnelPage() {
    const { data, rows, availableCities, isLoading, error, reload, search, setSearch, city, setCity } = usePersonnel();

    let body = <PersonnelTable rows={rows} />;
    if (isLoading) {
        body = <EmptyState icon={<Spinner size="large" />} title="Chargement du personnel…" description="Récupération des travailleurs de l’organisation." />;
    } else if (error) {
        body = <EmptyState title="Personnel indisponible" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} />;
    } else if (rows.length === 0) {
        body = <EmptyState
            title="Aucun membre à afficher"
            description={data && data.rows.length > 0 ? 'Aucun membre ne correspond aux filtres.' : 'Aucun travailleur enregistré pour cette organisation.'}
        />;
    }

    return <main className="organization-page organization-operational-page"><header className="organization-page__header"><div><span className="organization-page__eyebrow">ÉQUIPE OPÉRATIONNELLE</span><h1>Personnel</h1><p>Consultez les ouvriers et leurs affectations sur vos biens.</p></div></header>
        <OrganizationSummary items={[{ label: 'Membres affichés', value: <>{rows.length}</> }, { label: 'Affectations visibles', value: <>{rows.reduce((total, row) => total + row.assignments, 0)}</> }, { label: 'Villes couvertes', value: <>{new Set(rows.map((row) => row.city).filter((value) => value !== '—')).size}</> }]} />
        <Card className="organization-table-card" padding="medium"><div className="organization-table-toolbar"><div><h2>Équipe</h2><p>{data?.note ?? 'La fonction et la ville indiquées proviennent de la dernière affectation.'}</p></div><div className="organization-filters organization-filters--tenant"><SearchInput value={search} onSearch={setSearch} placeholder="Rechercher un membre…" fullWidth /><Select aria-label="Filtrer par ville" value={city} onChange={(event) => setCity(event.target.value)} options={[{ value: 'all', label: 'Toutes les villes' }, ...availableCities.map((value) => ({ value, label: value }))]} /></div></div>{body}</Card>
    </main>;
}
