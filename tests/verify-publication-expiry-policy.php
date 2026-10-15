<?php

declare(strict_types=1);

use App\Entity\Identity\User;
use App\Entity\System\Publication;
use App\Entity\System\PublicationRecipient;
use App\Enum\PublicationAudience;
use App\Enum\PublicationCategory;
use App\Enum\PublicationStatus;
use App\Service\System\PublicationExpiryPolicy;

require dirname(__DIR__).'/vendor/autoload.php';

$policy = new PublicationExpiryPolicy();
$now = new DateTimeImmutable('2026-10-15T12:00:00+00:00');
$user = (new ReflectionClass(User::class))->newInstanceWithoutConstructor();
$publication = (new Publication())->setTitle('Test')->setContentBlocks([])->setCategory(PublicationCategory::FEATURE)
    ->setAudience(PublicationAudience::ALL)->setStatus(PublicationStatus::PUBLISHED)->setCreatedBy($user);
$recipient = (new PublicationRecipient())->setPublication($publication)->setUser($user)->setAvailableAt($now->modify('-47 hours -59 minutes'));
$checks = [
    'unread remains active before 48h' => $policy->isActive($recipient, $now),
    'unread expires exactly at 48h' => !$policy->isActive($recipient->setAvailableAt($now->modify('-48 hours')), $now),
    'read remains active before 24h after first view' => $policy->isActive($recipient->setAvailableAt($now->modify('-60 hours'))->setFirstViewedAt($now->modify('-23 hours -59 minutes')), $now),
    'read expires exactly at 24h after first view' => !$policy->isActive($recipient->setFirstViewedAt($now->modify('-24 hours')), $now),
    'read timestamp is per recipient' => $policy->isActive($recipient->setFirstViewedAt(null)->setAvailableAt($now->modify('-2 hours')), $now)
        && !$policy->isActive((new PublicationRecipient())->setPublication($publication)->setUser($user)->setAvailableAt($now->modify('-48 hours')), $now),
    'withdrawn recipient is hidden' => !$policy->isActive($recipient->setAvailableAt($now->modify('-1 hour'))->setWithdrawnAt($now), $now),
    'scheduled notification is hidden until available' => !$policy->isActive($recipient->setWithdrawnAt(null)->setAvailableAt($now->modify('+1 hour')), $now),
    'publication end date is respected' => !$policy->isActive($recipient->setAvailableAt($now->modify('-1 hour'))->setFirstViewedAt(null)->setPublication($publication->setEndsAt($now)), $now),
];
$failures = 0;
foreach ($checks as $label => $passed) { echo sprintf("[%s] %s\n", $passed ? 'OK' : 'FAIL', $label); if (!$passed) $failures++; }
exit($failures === 0 ? 0 : 1);
