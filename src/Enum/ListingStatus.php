<?php

declare(strict_types=1);

namespace App\Enum;

/** Cycle de vérification et de diffusion d'une annonce immobilière. */
enum ListingStatus: string
{
    case DRAFT = 'draft';
    case PENDING_REVIEW = 'pending_review';
    case CORRECTIONS_REQUESTED = 'corrections_requested';
    case PENDING_PUBLICATION = 'pending_publication';
    case PUBLISHED = 'published';
    case REJECTED = 'rejected';
    case WITHDRAWN = 'withdrawn';
}
