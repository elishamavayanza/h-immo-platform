<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * CityAccessScope
 *
 * Mode de résolution du périmètre villes d'un compte authentifié.
 *
 * Il répond à une ambiguïté qu'un simple tableau de villes ne lève pas :
 * une liste vide signifie « aucune ville assignée » pour un compte
 * `ADMIN_VILLE`, mais « aucun filtre, tout est visible » pour un compte
 * `SUPER_ADMIN`. Sans ce descripteur, un client ne peut pas distinguer les
 * deux et risque soit de masquer un sélecteur utile, soit de croire à tort
 * qu'un compte n'a accès à rien.
 *
 * La valeur est informative : l'autorisation reste décidée par l'API
 * (`SecurityService`).
 */
enum CityAccessScope: string
{
    /** Périmètre plateforme : toutes les villes actives sont visibles. */
    case PLATFORM = 'platform';

    /** Périmètre restreint aux villes explicitement assignées au compte. */
    case ASSIGNED = 'assigned';

    /** Aucune ville assignée. */
    case NONE = 'none';
}
