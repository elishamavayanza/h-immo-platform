import { Card } from '../../../../components/UI/Card';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { usePatrimoine } from '../hooks/usePatrimoine';
import { PatrimoineTable } from '../components/PatrimoineTable';
import '../../../../../styles/pages/patron/patrimoine/_patrimoine.scss';

export function PatrimoinePage() {
    const { rows, search, setSearch, city, setCity, kind, setKind } = usePatrimoine();
    return <main className="patron-page patron-operational-page"><header className="patron-page__header"><div><span className="patron-page__eyebrow">GESTION IMMOBILIÈRE</span><h1>Patrimoine</h1><p>Parcourez les biens de votre portefeuille et leur occupation.</p></div></header>
        <section className="patron-mini-stats"><Card padding="medium"><span>Biens répertoriés</span><strong>124</strong></Card><Card padding="medium"><span>Unités locatives</span><strong>238</strong></Card><Card padding="medium"><span>Taux d’occupation</span><strong>87,5 %</strong></Card></section>
        <Card className="patron-table-card" padding="medium"><div className="patron-table-toolbar"><div><h2>Vos biens</h2><p>La liste est une maquette avec données locales.</p></div><div className="patron-filters"><SearchInput value={search} onSearch={setSearch} placeholder="Rechercher un bien…" fullWidth /><Select aria-label="Filtrer par ville" value={city} onChange={(event) => setCity(event.target.value)} options={[{ value: 'all', label: 'Toutes les villes' }, ...Array.from(new Set(rows.map((item) => item.city))).map((value) => ({ value, label: value }))]} /><Select aria-label="Filtrer par type" value={kind} onChange={(event) => setKind(event.target.value)} options={[{ value: 'all', label: 'Tous les types' }, ...['Parcelle', 'Bâtiment', 'Unité'].map((value) => ({ value, label: value }))]} /></div></div><PatrimoineTable rows={rows} /></Card>
    </main>;
}
