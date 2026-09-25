<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * OrganizationRole
 *
 * Rôle d'un utilisateur au sein d'une Organization donnée
 * (porté par l'entité de liaison OrganizationUser).
 */
enum OrganizationRole: string
{
    case PATRON = 'patron';
    case ADMIN_IMMOBILIER = 'admin_immobilier';
    case ADMIN_VILLE = 'admin_ville';
}
