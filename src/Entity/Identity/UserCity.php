<?php

declare(strict_types=1);

namespace App\Entity\Identity;

use App\Entity\Property\City;
use App\Entity\Shared\CreatedOnlyEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * UserCity
 *
 * Package  : Identity & Access
 * Table    : user_city
 *
 * Table de liaison N-N entre User et City, utilisée pour restreindre
 * le périmètre d'accès d'un utilisateur ADMIN_VILLE aux seules villes
 * qui lui sont explicitement attribuées, à l'intérieur d'une même
 * Organization. Ligne créée une fois, jamais modifiée : hérite de
 * CreatedOnlyEntity.
 */
#[ORM\Entity]
#[ORM\Table(name: 'user_city')]
#[ORM\UniqueConstraint(name: 'uniq_user_city', columns: ['user_id', 'city_id'])]
class UserCity extends CreatedOnlyEntity
{
    /**
     * Utilisateur auquel la ville est attribuée.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private User $user;

    /**
     * Ville accessible par l'utilisateur.
     */
    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn(name: 'city_id', referencedColumnName: 'id', nullable: false)]
    private City $city;

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCity(): City
    {
        return $this->city;
    }

    public function setCity(City $city): static
    {
        $this->city = $city;

        return $this;
    }
}
