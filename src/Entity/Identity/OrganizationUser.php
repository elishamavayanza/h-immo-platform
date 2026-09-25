<?php

declare(strict_types=1);

namespace App\Entity\Identity;

use App\Entity\Shared\TimestampedEntity;
use App\Enum\OrganizationRole;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * OrganizationUser
 *
 * Package  : Identity & Access
 * Table    : organization_user
 *
 * Table de liaison N-N entre User et Organization, portant en plus
 * le rôle (OrganizationRole) que l'utilisateur occupe au sein de
 * cette Organization précise (PATRON, ADMIN_IMMOBILIER, ADMIN_VILLE).
 * Un couple (organization, user) est unique : un utilisateur n'a
 * qu'un seul rôle par Organization.
 */
#[ORM\Entity]
#[ORM\Table(name: 'organization_user')]
#[ORM\UniqueConstraint(name: 'uniq_org_user', columns: ['organization_id', 'user_id'])]
class OrganizationUser extends TimestampedEntity
{

    /**
     * Organisation à laquelle l'utilisateur est rattaché.
     */
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false)]
    private Organization $organization;

    /**
     * Utilisateur associé à l'organisation.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private User $user;

    /**
     * Rôle de l'utilisateur au sein de l'organisation.
     */
    #[ORM\Column(type: Types::STRING, enumType: OrganizationRole::class)]
    private OrganizationRole $role;

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function setOrganization(Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getRole(): OrganizationRole
    {
        return $this->role;
    }

    public function setRole(OrganizationRole $role): static
    {
        $this->role = $role;

        return $this;
    }
}
