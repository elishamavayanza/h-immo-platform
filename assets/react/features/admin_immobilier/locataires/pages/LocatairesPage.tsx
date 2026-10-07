import { Card } from '../../../../components/UI/Card';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { useLocataires } from '../hooks/useLocataires';
import { LocatairesTable } from '../components/LocatairesTable';
import '../../../../../styles/pages/admin_immobilier/locataires/_locataires.scss';

export function LocatairesPage() {
    const { rows, search, setSearch, status, setStatus } = useLocataires();
    return <main className="patron-page patron-operational-page"><header className="patron-page__header"><div><span className="patron-page__eyebrow">GESTION LOCATIVE</span><h1>Locataires</h1><p>Retrouvez les occupants, leurs baux et leur situation.</p></div></header>
        <section className="patron-mini-stats"><Card padding="medium"><span>Locataires actifs</span><strong>96</strong></Card><Card padding="medium"><span>Baux en cours</span><strong>108</strong></Card><Card padding="medium"><span>Soldes à suivre</span><strong>12</strong></Card></section>
        <Card className="patron-table-card" padding="medium"><div className="patron-table-toolbar"><div><h2>Liste des locataires</h2><p>La liste est une maquette avec données locales.</p></div><div className="patron-filters patron-filters--tenant"><SearchInput value={search} onSearch={setSearch} placeholder="Rechercher un locataire…" fullWidth /><Select aria-label="Filtrer par statut" value={status} onChange={(event) => setStatus(event.target.value)} options={[{ value: 'all', label: 'Tous les statuts' }, ...['Actif', 'En attente', 'Ancien'].map((value) => ({ value, label: value }))]} /></div></div><LocatairesTable rows={rows} /></Card>
    </main>;
}
