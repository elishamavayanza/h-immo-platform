<?php

declare(strict_types=1);

namespace App\Entity\System;

use App\Entity\Identity\User;
use App\Entity\Shared\CreatedOnlyEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'publication_recipient')]
#[ORM\UniqueConstraint(name: 'uniq_publication_recipient', columns: ['publication_id', 'user_id'])]
#[ORM\Index(name: 'idx_recipient_active', columns: ['user_id', 'available_at', 'first_viewed_at'])]
class PublicationRecipient extends CreatedOnlyEntity
{
    #[ORM\ManyToOne(targetEntity: Publication::class)]
    #[ORM\JoinColumn(name: 'publication_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Publication $publication;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'available_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $availableAt;

    #[ORM\Column(name: 'first_viewed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $firstViewedAt = null;

    #[ORM\Column(name: 'withdrawn_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $withdrawnAt = null;

    public function getPublication(): Publication { return $this->publication; }
    public function setPublication(Publication $publication): static { $this->publication = $publication; return $this; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }
    public function getAvailableAt(): \DateTimeImmutable { return $this->availableAt; }
    public function setAvailableAt(\DateTimeImmutable $date): static { $this->availableAt = $date; return $this; }
    public function getFirstViewedAt(): ?\DateTimeImmutable { return $this->firstViewedAt; }
    public function setFirstViewedAt(?\DateTimeImmutable $date): static { $this->firstViewedAt = $date; return $this; }
    public function getWithdrawnAt(): ?\DateTimeImmutable { return $this->withdrawnAt; }
    public function setWithdrawnAt(?\DateTimeImmutable $date): static { $this->withdrawnAt = $date; return $this; }
}
