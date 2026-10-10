import { OrganizationSummary } from '../../../shared/components/OrganizationSummary';
import { Card } from '../../../../components/UI/Card';
import { Button } from '../../../../components/UI/Button';
import { Spinner } from '../../../../components/UI/Spinner';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { useDepenses } from '../hooks/useDepenses';
import { DepensesTable } from '../components/DepensesTable';
import '../../../../../styles/pages/admin_immobilier/depenses/_depenses.scss';

export function DepensesPage() {
    const { data, rows, availableCategories, isLoading, error, reload, search, setSearch, category, setCategory } = useDepenses();

    let body = <DepensesTable rows={rows} />;
    if (isLoading) {
        body = <EmptyState icon={<Spinner size="large" />} title="Chargement des dépenses…" description="Récupération des coûts de l’organisation." />;
    } else if (error) {
        body = <EmptyState title="Dépenses indisponibles" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} />;
    } else if (rows.length === 0) {
        body = <EmptyState
            title="Aucune dépense à afficher"
            description={data && data.rows.length > 0 ? 'Aucune dépense ne correspond aux filtres.' : 'Aucune dépense enregistrée pour cette organisation.'}
        />;
    }

    return <main className="organization-page organization-operational-page"><header className="organization-page__header"><div><span className="organization-page__eyebrow">SUIVI FINANCIER</span><h1>Dépenses</h1><p>Consultez les coûts liés à vos biens et à leur fonctionnement.</p></div></header>
        <OrganizationSummary items={[{ label: 'Dépenses affichées', value: <>{rows.length}</> }, { label: 'Villes concernées', value: <>{new Set(rows.map((row) => row.city)).size}</> }, { label: 'Catégories visibles', value: <>{new Set(rows.map((row) => row.category)).size}</> }]} />
        <Card className="organization-table-card" padding="medium"><div className="organization-table-toolbar"><div><h2>Historique des dépenses</h2><p>{data?.note ?? 'Les montants sont affichés dans leur devise d’origine.'}</p></div><div className="organization-filters organization-filters--tenant"><SearchInput value={search} onSearch={setSearch} placeholder="Rechercher une dépense…" fullWidth /><Select aria-label="Filtrer par catégorie" value={category} onChange={(event) => setCategory(event.target.value)} options={[{ value: 'all', label: 'Toutes catégories' }, ...availableCategories.map(([value, label]) => ({ value, label }))]} /></div></div>{body}</Card>
    </main>;
}
