<?php

declare(strict_types=1);

namespace App\Entity\Identity;

use App\Entity\Shared\TimestampedEntity;
use App\Enum\OrganizationRole;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Délégation limitée à la publication des règles locatives d'une organisation. */
#[ORM\Entity]
#[ORM\Table(name: 'rental_rule_publication_delegation')]
#[ORM\Index(name: 'idx_rule_delegation_org_user_active', columns: ['organization_id', 'user_id', 'revoked_at', 'expires_at'])]
final class RentalRulePublicationDelegation extends TimestampedEntity
{
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Organization $organization;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'granted_by_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private User $grantedBy;

    /** Valeurs constantes supportant les FK composites vers les memberships org-scoped. */
    #[ORM\Column(name: 'recipient_role', type: Types::STRING, length: 255, enumType: OrganizationRole::class, options: ['default' => OrganizationRole::ADMIN_IMMOBILIER->value])]
    private OrganizationRole $recipientRole = OrganizationRole::ADMIN_IMMOBILIER;

    #[ORM\Column(name: 'grantor_role', type: Types::STRING, length: 255, enumType: OrganizationRole::class, options: ['default' => OrganizationRole::PATRON->value])]
    private OrganizationRole $grantorRole = OrganizationRole::PATRON;

    #[ORM\Column(name: 'permission', type: Types::STRING, length: 40, options: ['default' => 'rental_rule_publish'])]
    private string $permission = 'rental_rule_publish';

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(name: 'revoked_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'revoked_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?User $revokedBy = null;

    #[ORM\Column(name: 'revocation_reason', type: Types::STRING, length: 500, nullable: true)]
    private ?string $revocationReason = null;

    public function getOrganization(): Organization { return $this->organization; }
    public function setOrganization(Organization $organization): static { $this->organization = $organization; return $this; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }
    public function getGrantedBy(): User { return $this->grantedBy; }
    public function setGrantedBy(User $user): static { $this->grantedBy = $user; return $this; }
    public function setRecipientRole(OrganizationRole $role): static { $this->recipientRole = $role; return $this; }
    public function setGrantorRole(OrganizationRole $role): static { $this->grantorRole = $role; return $this; }
    public function getPermission(): string { return $this->permission; }
    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static { $this->expiresAt = $expiresAt; return $this; }
    public function getRevokedAt(): ?\DateTimeImmutable { return $this->revokedAt; }
    public function getRevokedBy(): ?User { return $this->revokedBy; }
    public function setRevokedBy(?User $user): static { $this->revokedBy = $user; return $this; }
    public function getRevocationReason(): ?string { return $this->revocationReason; }
    public function setRevocationReason(?string $reason): static { $this->revocationReason = $reason; return $this; }
    public function setRevokedAt(?\DateTimeImmutable $date): static { $this->revokedAt = $date; return $this; }
}
