<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * UnitType
 *
 * Type d'unité locative (Unit) au sein d'un bâtiment.
 */
enum UnitType: string
{
    case APARTMENT = 'apartment';
    case HOUSE = 'house';
    case SHOP = 'shop';
    case OFFICE = 'office';
    case RESTAURANT = 'restaurant';
    case OTHER = 'other';
}
