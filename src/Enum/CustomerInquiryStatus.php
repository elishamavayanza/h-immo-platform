<?php

declare(strict_types=1);

namespace App\Enum;

/** Cycle de suivi interne d'une demande publique, distinct du bail ou de la vente. */
enum CustomerInquiryStatus: string
{
    case NEW = 'new';
    case IN_PROGRESS = 'in_progress';
    case CONTACTED = 'contacted';
    case VISIT_PROPOSED = 'visit_proposed';
    case VISIT_COMPLETED = 'visit_completed';
    case NEGOTIATION = 'negotiation';
    case CONVERTED = 'converted';
    case CLOSED = 'closed';
}
