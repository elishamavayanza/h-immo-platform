<?php

declare(strict_types=1);

namespace App\Entity\Property;

use App\Entity\Identity\User;
use App\Entity\Shared\CreatedOnlyEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Trace immuable des soumissions, contrôles et changements du contenu. */
#[ORM\Entity]
#[ORM\Table(name: 'property_listing_history')]
#[ORM\Index(name: 'idx_listing_history_listing_date', columns: ['listing_id', 'created_at'])]
class ListingHistory extends CreatedOnlyEntity
{
    #[ORM\ManyToOne(targetEntity: Listing::class)]
    #[ORM\JoinColumn(name: 'listing_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Listing $listing;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'actor_id', referencedColumnName: 'id', nullable: false)]
    private User $actor;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $action;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $oldStatus = null;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $newStatus;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $oldValues = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $newValues = null;

    public function setListing(Listing $listing): static { $this->listing = $listing; return $this; }
    public function getListing(): Listing { return $this->listing; }
    public function setActor(User $actor): static { $this->actor = $actor; return $this; }
    public function getActor(): User { return $this->actor; }
    public function setAction(string $action): static { $this->action = $action; return $this; }
    public function getAction(): string { return $this->action; }
    public function setOldStatus(?string $status): static { $this->oldStatus = $status; return $this; }
    public function getOldStatus(): ?string { return $this->oldStatus; }
    public function setNewStatus(string $status): static { $this->newStatus = $status; return $this; }
    public function getNewStatus(): string { return $this->newStatus; }
    public function setReason(?string $reason): static { $this->reason = $reason === null ? null : trim($reason); return $this; }
    public function getReason(): ?string { return $this->reason; }
    public function setOldValues(?array $values): static { $this->oldValues = $values; return $this; }
    public function getOldValues(): ?array { return $this->oldValues; }
    public function setNewValues(?array $values): static { $this->newValues = $values; return $this; }
    public function getNewValues(): ?array { return $this->newValues; }
}
