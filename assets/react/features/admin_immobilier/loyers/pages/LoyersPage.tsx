import { OrganizationSummary } from '../../../shared/components/OrganizationSummary';
import { Card } from '../../../../components/UI/Card';
import { Button } from '../../../../components/UI/Button';
import { Spinner } from '../../../../components/UI/Spinner';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { useLoyers } from '../hooks/useLoyers';
import { LoyersTable } from '../components/LoyersTable';
import '../../../../../styles/pages/admin_immobilier/loyers/_loyers.scss';

export function LoyersPage() {
    const { data, rows, isLoading, error, reload, search, setSearch, status, setStatus } = useLoyers();

    let body = <LoyersTable rows={rows} />;
    if (isLoading) {
        body = <EmptyState icon={<Spinner size="large" />} title="Chargement des échéances…" description="Récupération des loyers de l’organisation." />;
    } else if (error) {
        body = <EmptyState title="Échéances indisponibles" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} />;
    } else if (rows.length === 0) {
        body = <EmptyState
            title="Aucune échéance à afficher"
            description={data && data.rows.length > 0 ? 'Aucune échéance ne correspond à la recherche.' : 'Aucune échéance ne correspond au statut sélectionné.'}
        />;
    }

    return <main className="organization-page organization-operational-page"><header className="organization-page__header"><div><span className="organization-page__eyebrow">SUIVI FINANCIER</span><h1>Loyers</h1><p>Suivez les échéances, paiements et retards de votre portefeuille.</p></div></header>
        <OrganizationSummary items={[{ label: 'Échéances listées', value: <>{rows.length}</> }, { label: 'Payées', value: <>{rows.filter((row) => row.status === 'paid').length}</> }, { label: 'En retard', value: <>{rows.filter((row) => row.status === 'overdue').length}</> }]} />
        <Card className="organization-table-card" padding="medium"><div className="organization-table-toolbar"><div><h2>Échéances récentes</h2><p>{data?.note ?? 'Les montants sont affichés dans leur devise d’origine.'}</p></div><div className="organization-filters organization-filters--tenant"><SearchInput value={search} onSearch={setSearch} placeholder="Rechercher une échéance…" fullWidth /><Select aria-label="Filtrer par statut" value={status} onChange={(event) => setStatus(event.target.value)} options={[{ value: 'all', label: 'Tous les statuts' }, { value: 'pending', label: 'En attente' }, { value: 'partially_paid', label: 'Partiel' }, { value: 'paid', label: 'Payé' }, { value: 'overdue', label: 'En retard' }]} /></div></div>{body}</Card>
    </main>;
}
