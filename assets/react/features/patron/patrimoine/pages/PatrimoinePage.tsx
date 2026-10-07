import { OrganizationSummary } from '../../../shared/components/OrganizationSummary';
import { Card } from '../../../../components/UI/Card';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { usePatrimoine } from '../hooks/usePatrimoine';
import { PatrimoineTable } from '../components/PatrimoineTable';
import '../../../../../styles/pages/patron/patrimoine/_patrimoine.scss';

export function PatrimoinePage() {
    const { rows, availableCities, search, setSearch, city, setCity, kind, setKind } = usePatrimoine();
    return <main className="organization-page organization-operational-page"><header className="organization-page__header"><div><span className="organization-page__eyebrow">GESTION IMMOBILIÈRE</span><h1>Patrimoine</h1><p>Parcourez les biens de votre portefeuille et leur occupation.</p></div></header>
        <OrganizationSummary items={[{ label: 'Biens affichés', value: <>{rows.length}</> }, { label: 'Unités listées', value: <>{rows.reduce((total, row) => total + row.units, 0)}</> }, { label: 'Villes concernées', value: <>{new Set(rows.map((row) => row.city)).size}</> }]} />
        <Card className="organization-table-card" padding="medium"><div className="organization-table-toolbar"><div><h2>Vos biens</h2><p>La liste est une maquette avec données locales.</p></div><div className="organization-filters"><SearchInput value={search} onSearch={setSearch} placeholder="Rechercher un bien…" fullWidth /><Select aria-label="Filtrer par ville" value={city} onChange={(event) => setCity(event.target.value)} options={[{ value: 'all', label: 'Toutes les villes' }, ...availableCities.map((value) => ({ value, label: value }))]} /><Select aria-label="Filtrer par type" value={kind} onChange={(event) => setKind(event.target.value)} options={[{ value: 'all', label: 'Tous les types' }, ...['Parcelle', 'Bâtiment', 'Unité'].map((value) => ({ value, label: value }))]} /></div></div><PatrimoineTable rows={rows} /></Card>
    </main>;
}
