<?php

declare(strict_types=1);

namespace App\Service\Property;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/** Vérifie le jeton Turnstile côté serveur, avec un mode de développement explicitement séparé. */
final readonly class PublicInquiryTurnstileVerifier
{
    /** @var list<string> */
    private array $allowedHostnames;

    public function __construct(
        private TurnstileSiteverifyClientInterface $siteverify,
        #[Autowire('%env(bool:TURNSTILE_ENABLED)%')]
        private bool $enabled,
        #[Autowire('%env(TURNSTILE_SECRET_KEY)%')]
        private string $secretKey,
        #[Autowire('%env(TURNSTILE_EXPECTED_HOSTNAMES)%')]
        string $expectedHostnames,
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {
        $this->allowedHostnames = array_values(array_filter(array_map(
            static fn (string $hostname): string => strtolower(trim($hostname)),
            explode(',', $expectedHostnames)
        )));

        if ($this->environment === 'prod' && !$this->enabled) {
            throw new \LogicException('TURNSTILE_ENABLED doit être activé en production.');
        }

        if ($this->enabled && ($this->secretKey === '' || $this->allowedHostnames === [])) {
            throw new \LogicException('La clé secrète et les domaines Turnstile autorisés sont requis.');
        }
    }

    public function verify(?string $token, ?string $clientIp): bool
    {
        if (!$this->enabled) {
            return $this->environment === 'dev';
        }

        if ($token === null || trim($token) === '') {
            return false;
        }

        $payload = $this->siteverify->verify($this->secretKey, $token, $clientIp);
        if ($payload === null) {
            throw new ServiceUnavailableHttpException(30, 'La vérification anti-robot est momentanément indisponible.');
        }

        if (($payload['success'] ?? false) !== true || ($payload['action'] ?? null) !== 'public_inquiry') {
            return false;
        }

        return in_array(strtolower((string) ($payload['hostname'] ?? '')), $this->allowedHostnames, true);
    }
}
