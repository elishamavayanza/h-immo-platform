<?php

declare(strict_types=1);

namespace App\Service\Property;

interface TurnstileSiteverifyClientInterface
{
    /** @return array<string, mixed>|null null means the verification service could not be reached safely */
    public function verify(string $secret, string $token, ?string $clientIp): ?array;
}
