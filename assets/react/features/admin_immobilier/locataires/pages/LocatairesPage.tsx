import { OrganizationSummary } from '../../../shared/components/OrganizationSummary';
import { Card } from '../../../../components/UI/Card';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { useLocataires } from '../hooks/useLocataires';
import { LocatairesTable } from '../components/LocatairesTable';
import '../../../../../styles/pages/admin_immobilier/locataires/_locataires.scss';

export function LocatairesPage() {
    const { rows, search, setSearch, status, setStatus } = useLocataires();
    return <main className="organization-page organization-operational-page"><header className="organization-page__header"><div><span className="organization-page__eyebrow">GESTION LOCATIVE</span><h1>Locataires</h1><p>Retrouvez les occupants, leurs baux et leur situation.</p></div></header>
        <OrganizationSummary items={[{ label: 'Locataires visibles', value: <>{rows.length}</> }, { label: 'Locataires actifs', value: <>{rows.filter((row) => row.status === 'Actif').length}</> }, { label: 'Soldes à suivre', value: <>{rows.filter((row) => row.balance !== '0 $').length}</> }]} />
        <Card className="organization-table-card" padding="medium"><div className="organization-table-toolbar"><div><h2>Liste des locataires</h2><p>La liste est une maquette avec données locales.</p></div><div className="organization-filters organization-filters--tenant"><SearchInput value={search} onSearch={setSearch} placeholder="Rechercher un locataire…" fullWidth /><Select aria-label="Filtrer par statut" value={status} onChange={(event) => setStatus(event.target.value)} options={[{ value: 'all', label: 'Tous les statuts' }, ...['Actif', 'En attente', 'Ancien'].map((value) => ({ value, label: value }))]} /></div></div><LocatairesTable rows={rows} /></Card>
    </main>;
}
