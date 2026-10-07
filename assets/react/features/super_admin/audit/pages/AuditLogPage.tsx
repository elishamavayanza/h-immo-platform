import { DataTable } from '../../../../components/Data/DataTable';
import type { DataTableColumn } from '../../../../hook-components/Data/DataTable';
import { SearchInput } from '../../../../components/Forms/SearchInput';
import { Select } from '../../../../components/Forms/Select';
import { Badge } from '../../../../components/UI/Badge';
import { Card } from '../../../../components/UI/Card';
import { PlatformPageHeader } from '../../shared/PlatformPageHeader';
import { AuditStats } from '../components/AuditStats';
import { useAuditLog } from '../hooks/useAuditLog';
import type { AuditEntry } from '../types/audit.types';
import '../../../../../styles/pages/super_admin/audit/_audit.scss';

const CATEGORY_LABEL = { organization: 'Organisation', user: 'Utilisateur', security: 'Sécurité', billing: 'Facturation' };
const OUTCOME_LABEL = { success: 'Réussie', warning: 'À vérifier', danger: 'Échec' };
const OUTCOME_VARIANT = { success: 'success', warning: 'warning', danger: 'error' } as const;

export function AuditLogPage() {
    const { entries, search, setSearch, category, setCategory, outcome, setOutcome } = useAuditLog();
    const columns: DataTableColumn<AuditEntry>[] = [
        { key: 'date', title: 'Date et heure', sortable: true },
        { key: 'actor', title: 'Acteur', sortable: true },
        { key: 'action', title: 'Action', sortable: true },
        { key: 'target', title: 'Cible', sortable: true },
        { key: 'category', title: 'Catégorie', sortable: true, render: (entry) => <Badge variant="secondary">{CATEGORY_LABEL[entry.category]}</Badge> },
        { key: 'outcome', title: 'Résultat', sortable: true, render: (entry) => <Badge variant={OUTCOME_VARIANT[entry.outcome]}>{OUTCOME_LABEL[entry.outcome]}</Badge> },
        { key: 'ipAddress', title: 'Adresse IP', sortable: true },
    ];
    return <div className="sa-dashboard sa-management-page sa-audit-page">
        <PlatformPageHeader title="Journal d’audit" description="Consultez les événements et les actions sensibles de la plateforme." icon="shield" />
        <AuditStats entries={entries} />
        <Card className="sa-management-table-card" padding="medium">
            <div className="sa-management-toolbar"><div><h2>Événements récents</h2><p>Maquette locale : ces lignes ne proviennent pas encore de l’API.</p></div>
                <div className="sa-management-filters sa-audit-filters">
                    <SearchInput value={search} onSearch={setSearch} placeholder="Rechercher dans le journal..." fullWidth />
                    <Select aria-label="Catégorie" value={category} onChange={(event) => setCategory(event.target.value as typeof category)} options={[{ value: 'all', label: 'Toutes catégories' }, ...Object.entries(CATEGORY_LABEL).map(([value, label]) => ({ value, label }))]} />
                    <Select aria-label="Résultat" value={outcome} onChange={(event) => setOutcome(event.target.value)} options={[{ value: 'all', label: 'Tous résultats' }, { value: 'success', label: 'Réussie' }, { value: 'warning', label: 'À vérifier' }, { value: 'danger', label: 'Échec' }]} />
                </div>
            </div>
            <DataTable columns={columns} data={entries} pageSize={8} initialSortKey="date" />
        </Card>
    </div>;
}
