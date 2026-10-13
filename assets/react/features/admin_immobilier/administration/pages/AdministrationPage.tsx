import { useOrganization } from '../../../../app/providers/OrganizationProvider';
import { RoleAssignmentsPanel } from '../../../patron/administration/components/RoleAssignmentsPanel';

export function AdministrationPage() {
    const { currentOrganization } = useOrganization();
    return (
        <main className="organization-page organization-operational-page">
            <header className="organization-page__header">
                <div><span className="organization-page__eyebrow">ACCÈS DÉLÉGUÉS</span><h1>Administration</h1>
                    <p>Gérez les espaces Administrateur de ville de {currentOrganization?.name ?? 'votre organisation'} et leur périmètre de villes.</p>
                </div>
            </header>
            <RoleAssignmentsPanel />
        </main>
    );
}
