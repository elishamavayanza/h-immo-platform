<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * WorkerRole
 *
 * Fonction exercée par un travailleur dans le cadre d'une affectation
 * (portée par l'entité de liaison WorkerAssignment).
 *
 * Les rôles sont définis au niveau de l'affectation et non du travailleur :
 * une même personne peut être gérante d'un immeuble et sentinelle d'un
 * autre. `AUTRE` couvre les fonctions non prévues par l'énumération sans
 * interdire la saisie.
 */
enum WorkerRole: string
{
    /** Gérant d'immeuble ou de parcelle. */
    case GERANT = 'gerant';

    /** Sentinelle affectée à un patrol ou à un immeuble. */
    case SENTINELLE = 'sentinelle';

    /** Ménager ou personnel de maison. */
    case MENAGER = 'menager';

    /** Gardien d'immeuble ou d'entrée. */
    case GARDIEN = 'gardien';

    /** Agent d'entretien ou agent technique. */
    case AGENT_ENTRETIEN = 'agent_entretien';

    /** Comptable, caissier ou autre personnel administratif. */
    case AGENT_ADMINISTRATIF = 'agent_administratif';

    /** Fonction non prévue par l'énumération. */
    case AUTRE = 'autre';
}
