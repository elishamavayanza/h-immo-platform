import { useOrganization } from '../providers/OrganizationProvider';

import { PatronDashboardPage } from '../../features/patron/dashboard/pages/PatronDashboardPage';
import { PatrimoinePage as PatronPatrimoinePage } from '../../features/patron/patrimoine/pages/PatrimoinePage';
import { LocatairesPage as PatronLocatairesPage } from '../../features/patron/locataires/pages/LocatairesPage';
import { LoyersPage as PatronLoyersPage } from '../../features/patron/loyers/pages/LoyersPage';
import { DepensesPage as PatronDepensesPage } from '../../features/patron/depenses/pages/DepensesPage';
import { VitrinePage as PatronVitrinePage } from '../../features/patron/vitrine/pages/VitrinePage';
import { AdministrationPage } from '../../features/patron/administration/pages/AdministrationPage';
import { ReportsPage as PatronReportsPage } from '../../features/patron/reports/pages/ReportsPage';
import { AdminImmobilierDashboardPage } from '../../features/admin_immobilier/dashbord/pages/AdminImmobilierDashboardPage';
import { PatrimoinePage as AdminImmobilierPatrimoinePage } from '../../features/admin_immobilier/patrimoine/pages/PatrimoinePage';
import { LocatairesPage as AdminImmobilierLocatairesPage } from '../../features/admin_immobilier/locataires/pages/LocatairesPage';
import { LoyersPage as AdminImmobilierLoyersPage } from '../../features/admin_immobilier/loyers/pages/LoyersPage';
import { DepensesPage as AdminImmobilierDepensesPage } from '../../features/admin_immobilier/depenses/pages/DepensesPage';
import { VitrinePage as AdminImmobilierVitrinePage } from '../../features/admin_immobilier/vitrine/pages/VitrinePage';
import { PersonnelPage as AdminImmobilierPersonnelPage } from '../../features/admin_immobilier/personnel/pages/PersonnelPage';
import { ReportsPage as AdminImmobilierReportsPage } from '../../features/admin_immobilier/reports/pages/ReportsPage';
import { AdminVilleDashboardPage } from '../../features/admin_ville/dashbord/pages/AdminVilleDashboardPage';
import { PatrimoinePage as AdminVillePatrimoinePage } from '../../features/admin_ville/patrimoine/pages/PatrimoinePage';
import { LocatairesPage as AdminVilleLocatairesPage } from '../../features/admin_ville/locataires/pages/LocatairesPage';
import { LoyersPage as AdminVilleLoyersPage } from '../../features/admin_ville/loyers/pages/LoyersPage';
import { DepensesPage as AdminVilleDepensesPage } from '../../features/admin_ville/depenses/pages/DepensesPage';
import { VitrinePage as AdminVilleVitrinePage } from '../../features/admin_ville/vitrine/pages/VitrinePage';
import { ReportsPage as AdminVilleReportsPage } from '../../features/admin_ville/reports/pages/ReportsPage';

interface OrganizationMenuPageProps { path: string; }

/** Sélectionne la feuille du menu métier selon le rôle réellement résolu. */
export function OrganizationMenuPage({ path }: OrganizationMenuPageProps) {
    const { organizationRole } = useOrganization();

    if (organizationRole === 'admin_immobilier') {
        switch (path) {
            case '/app/dashboard': return <AdminImmobilierDashboardPage />;
            case '/app/patrimoine': return <AdminImmobilierPatrimoinePage />;
            case '/app/location/locataires': return <AdminImmobilierLocatairesPage />;
            case '/app/location/loyers': return <AdminImmobilierLoyersPage />;
            case '/app/depenses': return <AdminImmobilierDepensesPage />;
            case '/app/rapports': return <AdminImmobilierReportsPage />;
            case '/app/vitrine': return <AdminImmobilierVitrinePage />;
            case '/app/personnel': return <AdminImmobilierPersonnelPage />;
            default: return null;
        }
    }

    if (organizationRole === 'admin_ville') {
        switch (path) {
            case '/app/dashboard': return <AdminVilleDashboardPage />;
            case '/app/patrimoine': return <AdminVillePatrimoinePage />;
            case '/app/location/locataires': return <AdminVilleLocatairesPage />;
            case '/app/location/loyers': return <AdminVilleLoyersPage />;
            case '/app/depenses': return <AdminVilleDepensesPage />;
            case '/app/rapports': return <AdminVilleReportsPage />;
            case '/app/vitrine': return <AdminVilleVitrinePage />;
            default: return null;
        }
    }

    switch (path) {
        case '/app/dashboard': return <PatronDashboardPage />;
        case '/app/patrimoine': return <PatronPatrimoinePage />;
        case '/app/location/locataires': return <PatronLocatairesPage />;
        case '/app/location/loyers': return <PatronLoyersPage />;
        case '/app/depenses': return <PatronDepensesPage />;
        case '/app/rapports': return <PatronReportsPage />;
        case '/app/vitrine': return <PatronVitrinePage />;
        case '/app/administration': return <AdministrationPage />;
        default: return null;
    }
}
