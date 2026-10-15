<?php

declare(strict_types=1);

namespace App\Enum;

enum PublicationStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case WITHDRAWN = 'withdrawn';
    case ARCHIVED = 'archived';
}
