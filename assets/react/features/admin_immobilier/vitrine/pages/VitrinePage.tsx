import { OrganizationSummary } from '../../../shared/components/OrganizationSummary';
import { Card } from '../../../../components/UI/Card';
import { Button } from '../../../../components/UI/Button';
import { Spinner } from '../../../../components/UI/Spinner';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { useVitrine } from '../hooks/useVitrine';
import { VitrineTable } from '../components/VitrineTable';
import '../../../../../styles/pages/admin_immobilier/vitrine/_vitrine.scss';

export function VitrinePage() {
    const { data, rows, isLoading, error, reload, search, setSearch, status, setStatus, pendingId, togglePublished } = useVitrine();

    let body = <VitrineTable rows={rows} pendingId={pendingId} onTogglePublished={(row) => void togglePublished(row)} />;
    if (isLoading) {
        body = <EmptyState icon={<Spinner size="large" />} title="Chargement des annonces…" description="Récupération des biens de l’organisation." />;
    } else if (error) {
        body = <EmptyState title="Annonces indisponibles" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} />;
    } else if (rows.length === 0) {
        body = <EmptyState
            title="Aucune annonce à afficher"
            description={data && data.rows.length > 0 ? 'Aucune annonce ne correspond aux filtres.' : 'Aucune unité enregistrée pour cette organisation.'}
        />;
    }

    return <main className="organization-page organization-operational-page"><header className="organization-page__header"><div><span className="organization-page__eyebrow">PRÉSENCE EN LIGNE</span><h1>Vitrine</h1><p>Gérez la publication de vos biens sur le site public.</p></div></header>
        <OrganizationSummary items={[{ label: 'Annonces affichées', value: <>{rows.length}</> }, { label: 'Publiées', value: <>{rows.filter((row) => row.isPublished).length}</> }, { label: 'Brouillons', value: <>{rows.filter((row) => !row.isPublished).length}</> }]} />
        <Card className="organization-table-card" padding="medium"><div className="organization-table-toolbar"><div><h2>Annonces immobilières</h2><p>{data?.note ?? 'Publier une unité occupée par un bail actif est refusé.'}</p></div><div className="organization-filters organization-filters--tenant"><SearchInput value={search} onSearch={setSearch} placeholder="Rechercher une annonce…" fullWidth /><Select aria-label="Filtrer par statut" value={status} onChange={(event) => setStatus(event.target.value)} options={[{ value: 'all', label: 'Tous les statuts' }, { value: 'published', label: 'Publiées' }, { value: 'unpublished', label: 'Brouillons' }]} /></div></div>{body}</Card>
    </main>;
}
