import type { ReactNode } from 'react';

import { EmptyState } from '../../../components/Data/EmptyState';

interface NoAssignedCityStateProps {
    /** Action éventuelle (bouton de récupération, navigation…). */
    action?: ReactNode;
}

/**
 * État vide partagé par toutes les pages ADMIN_VILLE : l'utilisateur a un
 * rôle ADMIN_VILLE mais aucune ville ne lui est affectée (aucune ligne
 * UserCity), le backend ne renvoie donc aucune donnée.
 */
export function NoAssignedCityState({ action }: NoAssignedCityStateProps) {
    return (
        <EmptyState
            title="Aucune ville ne vous est affectée"
            description="Votre périmètre est vide : demandez à un administrateur de votre organisation de vous rattacher à au moins une ville."
            action={action}
        />
    );
}