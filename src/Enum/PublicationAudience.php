<?php

declare(strict_types=1);

namespace App\Enum;

enum PublicationAudience: string
{
    case ALL = 'all';
    case ROLES = 'roles';
    case ORGANIZATION = 'organization';
}
