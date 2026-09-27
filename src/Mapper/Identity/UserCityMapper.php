<?php

declare(strict_types=1);

namespace App\Mapper\Identity;

use App\Dto\Response\Identity\UserCityResponse;
use App\Entity\Identity\UserCity;
use App\Mapper\Property\CityMapper;

/**
 * UserCityMapper
 *
 * Package : Identity & Access — Service de mapping d'entité
 *
 * Transforme les liaisons UserCity en DTOs de réponse
 * combinant les sous-DTOs User et City rattachés.
 */
final readonly class UserCityMapper
{
    /**
     * Injecte les mappers de l'utilisateur et de la ville dépendante.
     * Permet la construction récursive de l'objet de réponse UserCityResponse.
     */
    public function __construct(
        private UserMapper $userMapper,
        private CityMapper $cityMapper
    ) {
    }

    /**
     * Mappe une instance de l'entité de liaison UserCity vers son DTO de réponse.
     * Prépare la charge utile détaillant la ville attribuée à l'administrateur.
     */
    public function toResponse(UserCity $userCity): UserCityResponse
    {
        return new UserCityResponse(
            uuid: $userCity->getUuid(),
            user: $this->userMapper->toResponse($userCity->getUser()),
            city: $this->cityMapper->toResponse($userCity->getCity()),
            createdAt: $userCity->getCreatedAt()
        );
    }
}
