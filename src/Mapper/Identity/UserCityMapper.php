<?php

declare(strict_types=1);

namespace App\Mapper\Identity;

use App\Dto\Response\Identity\UserCityResponse;
use App\Entity\Identity\UserCity;

/**
 * UserCityMapper
 *
 * Package : Identity & Access — Service de mapping d'entité
 *
 * Transforme la liaison UserCity en objet DTO de réponse.
 * La projection vers le DTO est intégralement assurée par la fabrique
 * statique du DTO de réponse (identifiants des deux parents).
 */
final readonly class UserCityMapper
{
    public function toResponse(UserCity $userCity): UserCityResponse
    {
        return UserCityResponse::fromEntity($userCity);
    }
}
