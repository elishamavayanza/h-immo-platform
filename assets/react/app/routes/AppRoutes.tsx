// ============================================================
// upload/react/app/routes/AppRoutes.tsx
// Table de routage du SPA.
//
// `react-router-dom` (v7) est installé et `main.tsx` monte un
// `<BrowserRouter>`. Deux familles de routes :
//
//   - Hors session (AuthLayout) : `/login`, `/forgot-password`, `/reset-password`.
//   - Back-office (`/app`, MainLayout) : sections chargées en
//     `React.lazy`, feuilles affichant un placeholder « à venir », garde
//     `RequireRole` (UX) positionnée par MainLayout.
//
// Les chemins des feuilles sont LES MÊMES que ceux du `sidebar.config` :
// si un chemin du menu disparaît, il disparaît aussi du routeur (et vice
// versa) — la garde `isPathInMenu` départage alors les rôles.
// ============================================================

import { lazy, Suspense } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';

import { Loading } from '../../../../public/components/UI/Loading/Loading';
import { useOrganization } from '../providers/OrganizationProvider';
import { ForgotPasswordPage } from '../../features/auth/pages/ForgotPasswordPage';
import { LoginPage } from '../../features/auth/pages/LoginPage';
import { ResetPasswordPage } from '../../features/auth/pages/ResetPasswordPage';
import { AccessDeniedPage } from '../pages/AccessDeniedPage';
import { PlaceholderScreen } from '../pages/PlaceholderScreen';
import { AuthLayout } from '../layout/AuthLayout/AuthLayout';
import { MainLayout } from '../layout/MainLayout/MainLayout';
import { defaultPathFor } from '../layout/MainLayout/sidebar/sidebar.config';

const PatrimoineSection = lazy(() => import('../pages/sections/PatrimoineSection'));
const LocationSection = lazy(() => import('../pages/sections/LocationSection'));
const FinancesSection = lazy(() => import('../pages/sections/FinancesSection'));
const VitrineSection = lazy(() => import('../pages/sections/VitrineSection'));
const AdministrationSection = lazy(() => import('../pages/sections/AdministrationSection'));
const PersonnelSection = lazy(() => import('../pages/sections/PersonnelSection'));
const PlatformSection = lazy(() => import('../pages/sections/PlatformSection'));

/** `/app` → première feuille du menu du rôle courant (suivie par le sidebar). */
function AppLanding() {
    const { platformRole, organizationRole } = useOrganization();

    return <Navigate to={defaultPathFor(platformRole, organizationRole)} replace />;
}

export function AppRoutes() {
    return (
        <Suspense fallback={<Loading text="Chargement de la section..." />}>
            <Routes>
                <Route element={<AuthLayout />}>
                    <Route path="/login" element={<LoginPage />} />
                    <Route path="/forgot-password" element={<ForgotPasswordPage />} />
                    <Route path="/reset-password" element={<ResetPasswordPage />} />
                </Route>

                <Route path="/" element={<Navigate to="/app" replace />} />

                <Route path="/app" element={<MainLayout />}>
                    <Route index element={<AppLanding />} />
                    <Route path="access-denied" element={<AccessDeniedPage />} />

                    <Route path="patrimoine" element={<PatrimoineSection />}>
                        <Route path="villes" element={<PlaceholderScreen title="Cités" />} />
                        <Route path="parcelles" element={<PlaceholderScreen title="Parcelles" />} />
                        <Route path="batiments" element={<PlaceholderScreen title="Bâtiments" />} />
                        <Route path="unites" element={<PlaceholderScreen title="Unités" />} />
                    </Route>

                    <Route path="location" element={<LocationSection />}>
                        <Route path="locataires" element={<PlaceholderScreen title="Locataires" />} />
                        <Route path="baux" element={<PlaceholderScreen title="Baux" />} />
                        <Route path="loyers" element={<PlaceholderScreen title="Loyers" />} />
                        <Route path="paiements" element={<PlaceholderScreen title="Paiements" />} />
                    </Route>

                    <Route path="finances" element={<FinancesSection />}>
                        <Route path="depenses" element={<PlaceholderScreen title="Dépenses" />} />
                        <Route path="rapports" element={<PlaceholderScreen title="Rapports" />} />
                    </Route>

                    <Route path="vitrine" element={<VitrineSection />} />

                    <Route path="administration" element={<AdministrationSection />}>
                        <Route path="equipe" element={<PlaceholderScreen title="Équipe" />} />
                        <Route path="villes" element={<PlaceholderScreen title="Villes" />} />
                    </Route>

                    <Route path="personnel" element={<PersonnelSection />}>
                        <Route path="ouvriers" element={<PlaceholderScreen title="Ouvriers" />} />
                        <Route path="affectations" element={<PlaceholderScreen title="Affectations" />} />
                    </Route>

                    <Route path="admin" element={<PlatformSection />}>
                        <Route path="organisations" element={<PlaceholderScreen title="Organisations" />} />
                        <Route path="utilisateurs" element={<PlaceholderScreen title="Utilisateurs" />} />
                        <Route path="audit" element={<PlaceholderScreen title="Journal d'audit" />} />
                        <Route path="taux-change" element={<PlaceholderScreen title="Taux de change" />} />
                    </Route>
                </Route>

                <Route path="*" element={<Navigate to="/app" replace />} />
            </Routes>
        </Suspense>
    );
}
