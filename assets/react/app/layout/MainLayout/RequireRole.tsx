// ============================================================
// upload/react/app/layout/MainLayout/RequireRole.tsx
// Garde de route UX basée sur le rôle.
//
// ⚠️ Ce composant n'est PAS une sécurité : le backend doit
// toujours vérifier les permissions. Cette garde sert à éviter
// à l'utilisateur d'atterrir sur une page vide ou en erreur
// quand il n'a pas le rôle requis.
//
// Utilise `useOrganization()` qui résout :
//   - platformRole : 'super_admin' | null
//   - organizationRole : 'patron' | 'admin_immobilier'
//                       | 'admin_ville' | null
// ============================================================

import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';


import { useOrganization } from '../../providers/OrganizationProvider';
import {Loading} from "../../../components/UI/Loading";
import {ErrorState} from "../../../components/UI/ErrorState";

// ============================================================
// TYPES
// ============================================================

export type PlatformRole = 'super_admin';
export type OrganizationRole = 'patron' | 'admin_immobilier' | 'admin_ville';

export interface RequireRoleProps {
    children: React.ReactNode;

    /**
     * Liste des rôles plateforme autorisés.
     * Si non fourni, aucun rôle plateforme n'est autorisé
     * (les rôles d'organisation restent contrôlés par
     * `organizationRoles`).
     */
    platformRoles?: PlatformRole[];

    /**
     * Liste des rôles d'organisation autorisés.
     * Si non fourni, tous les rôles d'organisation actifs
     * sont autorisés.
     */
    organizationRoles?: OrganizationRole[];

    /**
     * Si `true` (défaut) : redirige vers `/` quand l'accès
     * est refusé. Si `false` : affiche un <ErrorState /> 403.
     */
    redirectOnDeny?: boolean;

    /**
     * Chemin de redirection en cas d'accès refusé.
     * Défaut : '/'
     */
    redirectTo?: string;
}

// ============================================================
// COMPOSANT
// ============================================================

export function RequireRole({
                                children,
                                platformRoles,
                                organizationRoles,
                                redirectOnDeny = false,
                                redirectTo = '/',
                            }: RequireRoleProps) {
    const { platformRole, organizationRole, isLoading } = useOrganization();
    const location = useLocation();

    // 1. Chargement → on attend avant de décider (évite un flash
    //    de redirection sur les comptes en cours de résolution).
    if (isLoading) {
        return (
            <div className="require-role require-role--loading">
                <Loading text="Vérification de vos accès..." />
            </div>
        );
    }

    // 2. Aucun rôle résolu → utilisateur non autorisé
    const hasPlatformRole =
        platformRole !== null &&
        platformRole !== undefined &&
        (platformRoles === undefined
            ? false
            : platformRoles.includes(platformRole as PlatformRole));

    const hasOrganizationRole =
        organizationRole !== null &&
        organizationRole !== undefined &&
        (organizationRoles === undefined
            ? true
            : organizationRoles.includes(organizationRole as OrganizationRole));

    // Règle :
    // - Si `platformRoles` fourni : il faut avoir un rôle plateforme valide.
    // - Sinon : il faut avoir un rôle d'organisation valide.
    const isAllowed =
        platformRoles !== undefined
            ? hasPlatformRole || hasOrganizationRole
            : hasOrganizationRole;

    // 3. Autorisé → on rend les enfants
    if (isAllowed) {
        return <>{children}</>;
    }

    // 4. Refusé
    if (redirectOnDeny) {
        return <Navigate to={redirectTo} replace state={{ from: location }} />;
    }

    // Affichage par défaut d'un écran 403 cohérent avec le reste
    return (
        <ErrorState
            status={403}
            codeLabel="Forbidden"
            kind="forbidden"
            tone="danger"
            title="Accès refusé"
            message="Vous n’avez pas les permissions nécessaires pour accéder à cette page. Contactez votre administrateur si vous pensez qu’il s’agit d’une erreur."
            onBack={() => window.history.back()}
            onHome={() => {
                window.location.href = '/';
            }}
        />
    );
}
