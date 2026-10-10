import { OrganizationSummary } from '../../../shared/components/OrganizationSummary';
import { Card } from '../../../../components/UI/Card';
import { Button } from '../../../../components/UI/Button';
import { Spinner } from '../../../../components/UI/Spinner';
import { EmptyState } from '../../../../components/Data/EmptyState';
import { ConfirmDialog } from '../../../../components/UI/ConfirmDialog';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { useLocataires } from '../hooks/useLocataires';
import { LocatairesTable } from '../components/LocatairesTable';
import '../../../../../styles/pages/admin_immobilier/locataires/_locataires.scss';

export function LocatairesPage() {
    const {
        data, rows, isLoading, error, reload,
        search, setSearch, type, setType,
        pending, requestArchive, cancelArchive, confirmArchive, isArchiving,
    } = useLocataires();

    let body = <LocatairesTable rows={rows} onArchive={requestArchive} />;
    if (isLoading) {
        body = <EmptyState icon={<Spinner size="large" />} title="Chargement des locataires…" description="Récupération des fiches et de leurs baux en cours." />;
    } else if (error) {
        body = <EmptyState title="Locataires indisponibles" description={error} action={<Button onClick={() => void reload()}>Réessayer</Button>} />;
    } else if (rows.length === 0) {
        body = <EmptyState
            title="Aucun locataire à afficher"
            description={data && data.rows.length > 0 ? 'Aucun locataire ne correspond à la recherche ou au filtre sélectionné.' : 'Votre organisation n’a encore aucun locataire enregistré.'}
        />;
    }

    return <main className="organization-page organization-operational-page"><header className="organization-page__header"><div><span className="organization-page__eyebrow">GESTION LOCATIVE</span><h1>Locataires</h1><p>Retrouvez les occupants, leurs baux et leur situation.</p></div></header>
        <OrganizationSummary items={[{ label: 'Locataires', value: <>{data?.total ?? 0}</> }, { label: 'Baux en cours', value: <>{data?.activeLeases ?? 0}</> }, { label: 'Personnes morales', value: <>{(data?.rows ?? []).filter((row) => row.type === 'company').length}</> }]} />
        <Card className="organization-table-card" padding="medium"><div className="organization-table-toolbar"><div><h2>Liste des locataires</h2><p>{data?.note ?? 'La fin du bail affichée correspond au bail actif du locataire.'}</p></div><div className="organization-filters organization-filters--tenant"><SearchInput value={search} onSearch={setSearch} placeholder="Rechercher un locataire…" fullWidth /><Select aria-label="Filtrer par type" value={type} onChange={(event) => setType(event.target.value)} options={[{ value: 'all', label: 'Tous les types' }, { value: 'individual', label: 'Personnes physiques' }, { value: 'company', label: 'Personnes morales' }]} /></div></div>{body}</Card>
        <ConfirmDialog
            isOpen={pending !== null}
            onClose={cancelArchive}
            onConfirm={() => void confirmArchive()}
            title="Archiver ce locataire ?"
            message={`${pending?.name ?? 'Ce locataire'} sera retiré des listes. Ses baux et son historique restent conservés.`}
            confirmLabel={isArchiving ? 'Archivage…' : 'Archiver'}
            cancelLabel="Annuler"
        />
    </main>;
}
