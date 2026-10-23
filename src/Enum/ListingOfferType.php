<?php

declare(strict_types=1);

namespace App\Enum;

/** Nature commerciale explicitement publiée avec l'annonce. */
enum ListingOfferType: string
{
    case RENT = 'rent';
    case SALE = 'sale';
}
