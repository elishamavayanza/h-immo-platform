<?php

declare(strict_types=1);

namespace App\Entity\Property;

use App\Entity\Identity\User;
use App\Entity\Shared\CreatedOnlyEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'customer_inquiry_notification')]
#[ORM\UniqueConstraint(name: 'uniq_inquiry_notification_recipient', columns: ['inquiry_id', 'user_id'])]
#[ORM\Index(name: 'idx_inquiry_notification_inbox', columns: ['user_id', 'read_at', 'created_at'])]
class CustomerInquiryNotification extends CreatedOnlyEntity
{
    #[ORM\ManyToOne(targetEntity: CustomerInquiry::class)]
    #[ORM\JoinColumn(name: 'inquiry_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private CustomerInquiry $inquiry;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $recipient;

    #[ORM\Column(name: 'read_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    public function getInquiry(): CustomerInquiry { return $this->inquiry; }
    public function setInquiry(CustomerInquiry $inquiry): static { $this->inquiry = $inquiry; return $this; }
    public function getRecipient(): User { return $this->recipient; }
    public function setRecipient(User $recipient): static { $this->recipient = $recipient; return $this; }
    public function getReadAt(): ?\DateTimeImmutable { return $this->readAt; }
    public function setReadAt(?\DateTimeImmutable $readAt): static { $this->readAt = $readAt; return $this; }
}
