import { lazy, Suspense, type ComponentType, type ReactElement } from 'react';
import { useOrganization } from '../providers/OrganizationProvider';
import { Spinner } from '../../components/UI/Spinner';

/** Charge les pages à la demande pour garder les espaces métier hors du bundle initial. */
function lazyPage<Module extends object, ExportName extends keyof Module>(loader: () => Promise<Module>, exportName: ExportName) {
    return lazy(async () => ({ default: (await loader())[exportName] as ComponentType }));
}

const PatronDashboardPage = lazyPage(() => import('../../features/patron/dashboard/pages/PatronDashboardPage'), 'PatronDashboardPage');
const PatronPatrimoinePage = lazyPage(() => import('../../features/patron/patrimoine/pages/PatrimoinePage'), 'PatrimoinePage');
const PatronLocatairesPage = lazyPage(() => import('../../features/patron/locataires/pages/LocatairesPage'), 'LocatairesPage');
const PatronLoyersPage = lazyPage(() => import('../../features/patron/loyers/pages/LoyersPage'), 'LoyersPage');
const PatronDepensesPage = lazyPage(() => import('../../features/patron/depenses/pages/DepensesPage'), 'DepensesPage');
const PatronVitrinePage = lazyPage(() => import('../../features/patron/vitrine/pages/VitrinePage'), 'VitrinePage');
const PatronAdministrationPage = lazyPage(() => import('../../features/patron/administration/pages/AdministrationPage'), 'AdministrationPage');
const PatronReportsPage = lazyPage(() => import('../../features/patron/reports/pages/ReportsPage'), 'ReportsPage');
const AdminImmobilierDashboardPage = lazyPage(() => import('../../features/admin_immobilier/dashboard/pages/AdminImmobilierDashboardPage'), 'AdminImmobilierDashboardPage');
const AdminImmobilierPatrimoinePage = lazyPage(() => import('../../features/admin_immobilier/patrimoine/pages/PatrimoinePage'), 'PatrimoinePage');
const AdminImmobilierLocatairesPage = lazyPage(() => import('../../features/admin_immobilier/locataires/pages/LocatairesPage'), 'LocatairesPage');
const AdminImmobilierLoyersPage = lazyPage(() => import('../../features/admin_immobilier/loyers/pages/LoyersPage'), 'LoyersPage');
const AdminImmobilierDepensesPage = lazyPage(() => import('../../features/admin_immobilier/depenses/pages/DepensesPage'), 'DepensesPage');
const AdminImmobilierVitrinePage = lazyPage(() => import('../../features/admin_immobilier/vitrine/pages/VitrinePage'), 'VitrinePage');
const AdminImmobilierPersonnelPage = lazyPage(() => import('../../features/admin_immobilier/personnel/pages/PersonnelPage'), 'PersonnelPage');
const AdminImmobilierReportsPage = lazyPage(() => import('../../features/admin_immobilier/reports/pages/ReportsPage'), 'ReportsPage');
const AdminImmobilierAdministrationPage = lazyPage(() => import('../../features/admin_immobilier/administration/pages/AdministrationPage'), 'AdministrationPage');
const AdminVilleDashboardPage = lazyPage(() => import('../../features/admin_ville/dashboard/pages/AdminVilleDashboardPage'), 'AdminVilleDashboardPage');
const AdminVillePatrimoinePage = lazyPage(() => import('../../features/admin_ville/patrimoine/pages/PatrimoinePage'), 'PatrimoinePage');
const AdminVilleLocatairesPage = lazyPage(() => import('../../features/admin_ville/locataires/pages/LocatairesPage'), 'LocatairesPage');
const AdminVilleLoyersPage = lazyPage(() => import('../../features/admin_ville/loyers/pages/LoyersPage'), 'LoyersPage');
const AdminVilleDepensesPage = lazyPage(() => import('../../features/admin_ville/depenses/pages/DepensesPage'), 'DepensesPage');
const AdminVilleReportsPage = lazyPage(() => import('../../features/admin_ville/reports/pages/ReportsPage'), 'ReportsPage');
const AdminVilleOperationsPage = lazyPage(() => import('../../features/admin_ville/operations/pages/OperationsPage'), 'OperationsPage');

interface OrganizationMenuPageProps { path: string; }

/** Sélectionne la feuille du menu métier selon le rôle réellement résolu. */
export function OrganizationMenuPage({ path }: OrganizationMenuPageProps) {
    const { organizationRole } = useOrganization();
    let page: ReactElement | null = null;

    if (organizationRole === 'admin_immobilier') {
        switch (path) {
            case '/app/dashboard': page = <AdminImmobilierDashboardPage />; break;
            case '/app/patrimoine': page = <AdminImmobilierPatrimoinePage />; break;
            case '/app/location/locataires': page = <AdminImmobilierLocatairesPage />; break;
            case '/app/location/loyers': page = <AdminImmobilierLoyersPage />; break;
            case '/app/depenses': page = <AdminImmobilierDepensesPage />; break;
            case '/app/rapports': page = <AdminImmobilierReportsPage />; break;
            case '/app/vitrine': page = <AdminImmobilierVitrinePage />; break;
            case '/app/personnel': page = <AdminImmobilierPersonnelPage />; break;
            case '/app/administration': page = <AdminImmobilierAdministrationPage />; break;
        }
    } else if (organizationRole === 'admin_ville') {
        switch (path) {
            case '/app/dashboard': page = <AdminVilleDashboardPage />; break;
            case '/app/patrimoine': page = <AdminVillePatrimoinePage />; break;
            case '/app/location/locataires': page = <AdminVilleLocatairesPage />; break;
            case '/app/location/loyers': page = <AdminVilleLoyersPage />; break;
            case '/app/depenses': page = <AdminVilleDepensesPage />; break;
            case '/app/rapports': page = <AdminVilleReportsPage />; break;
            case '/app/operations': page = <AdminVilleOperationsPage />; break;
        }
    } else {
        switch (path) {
            case '/app/dashboard': page = <PatronDashboardPage />; break;
            case '/app/patrimoine': page = <PatronPatrimoinePage />; break;
            case '/app/location/locataires': page = <PatronLocatairesPage />; break;
            case '/app/location/loyers': page = <PatronLoyersPage />; break;
            case '/app/depenses': page = <PatronDepensesPage />; break;
            case '/app/rapports': page = <PatronReportsPage />; break;
            case '/app/vitrine': page = <PatronVitrinePage />; break;
            case '/app/administration': page = <PatronAdministrationPage />; break;
        }
    }

    return page ? <Suspense fallback={<div className="main-layout__page-loading" aria-busy="true"><Spinner size="large" className="spinner--page" /><span>Chargement de la page…</span></div>}>{page}</Suspense> : null;
}
