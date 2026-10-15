<?php

declare(strict_types=1);

namespace App\Service\System;

use App\Entity\System\PublicationRecipient;
use App\Enum\PublicationStatus;

/** Règle pure, partagée par les contrôles et les tests à horloge déterministe. */
final class PublicationExpiryPolicy
{
    public function isActive(PublicationRecipient $recipient, \DateTimeImmutable $now): bool
    {
        $publication = $recipient->getPublication();
        if ($publication->getStatus() !== PublicationStatus::PUBLISHED || $recipient->getWithdrawnAt() !== null || $recipient->getAvailableAt() > $now) return false;
        if ($publication->getEndsAt() !== null && $publication->getEndsAt() <= $now) return false;
        $expiresAt = ($recipient->getFirstViewedAt() ?? $recipient->getAvailableAt())->modify($recipient->getFirstViewedAt() === null ? '+48 hours' : '+24 hours');
        return $expiresAt > $now;
    }
}
