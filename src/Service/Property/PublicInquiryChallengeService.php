<?php

declare(strict_types=1);

namespace App\Service\Property;

use App\Service\System\DateTimeService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Le jeton signé lie une preuve temporaire au couple annonce/organisation sans
 * enregistrer de session ni faire confiance à l'horloge fournie par le navigateur.
 */
final readonly class PublicInquiryChallengeService
{
    public function __construct(
        private DateTimeService $dateTime,
        #[Autowire('%env(PUBLIC_INQUIRY_HMAC_SECRET)%')]
        private string $hmacSecret,
        #[Autowire('%kernel.secret%')]
        private string $developmentSecret,
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {
        if ($this->hmacSecret === '' && $this->environment !== 'dev') {
            throw new \LogicException('PUBLIC_INQUIRY_HMAC_SECRET doit être configuré hors développement.');
        }

        if ($this->hmacSecret !== '' && strlen($this->hmacSecret) < 32) {
            throw new \LogicException('PUBLIC_INQUIRY_HMAC_SECRET doit contenir au moins 32 octets.');
        }
    }

    public function issue(string $slug, string $unitUuid): string
    {
        $issuedAt = $this->dateTime->now()->getTimestamp();
        $nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');

        return $issuedAt.'.'.$nonce.'.'.$this->signature($slug, $unitUuid, $issuedAt, $nonce);
    }

    public function isValid(string|null $token, string $slug, string $unitUuid, ?\DateTimeImmutable $now = null): bool
    {
        if ($token === null || !preg_match('/^(\d{10})\.([A-Za-z0-9_-]{22})\.([a-f0-9]{64})$/D', $token, $matches)) {
            return false;
        }

        $issuedAt = (int) $matches[1];
        $age = ($now ?? $this->dateTime->now())->getTimestamp() - $issuedAt;
        if ($age < 3 || $age > 1800) {
            return false;
        }

        return hash_equals($this->signature($slug, $unitUuid, $issuedAt, $matches[2]), $matches[3]);
    }

    private function signature(string $slug, string $unitUuid, int $issuedAt, string $nonce): string
    {
        $secret = $this->hmacSecret !== '' ? $this->hmacSecret : $this->developmentSecret;

        return hash_hmac('sha256', implode('|', ['public-inquiry-v1', $slug, $unitUuid, (string) $issuedAt, $nonce]), $secret);
    }
}
