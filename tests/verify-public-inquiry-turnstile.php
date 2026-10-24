<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\Service\Property\PublicInquiryTurnstileVerifier;
use App\Service\Property\TurnstileSiteverifyClientInterface;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

$makeVerifier = static function (?array $payload, bool $enabled = true, string $environment = 'test', string $secret = 'private-test-secret', string $hosts = 'vitrine.test'): array {
    $client = new class($payload) implements TurnstileSiteverifyClientInterface {
        public int $calls = 0;
        public ?array $lastRequest = null;

        public function __construct(private readonly ?array $payload)
        {
        }

        public function verify(string $secret, string $token, ?string $clientIp): ?array
        {
            $this->calls++;
            $this->lastRequest = [$secret, $token, $clientIp];

            return $this->payload;
        }
    };

    return [new PublicInquiryTurnstileVerifier($client, $enabled, $secret, $hosts, $environment), $client];
};

[$validVerifier, $validClient] = $makeVerifier(['success' => true, 'action' => 'public_inquiry', 'hostname' => 'VITRINE.TEST']);
[$wrongHostVerifier] = $makeVerifier(['success' => true, 'action' => 'public_inquiry', 'hostname' => 'other.test']);
[$wrongActionVerifier] = $makeVerifier(['success' => true, 'action' => 'other', 'hostname' => 'vitrine.test']);
[$invalidTokenVerifier] = $makeVerifier(['success' => false, 'error-codes' => ['invalid-input-response']]);
[$expiredTokenVerifier] = $makeVerifier(['success' => false, 'error-codes' => ['timeout-or-duplicate']]);
[$downVerifier] = $makeVerifier(null);
[$devVerifier] = $makeVerifier(null, false, 'dev', '', '');
[$testDisabledVerifier] = $makeVerifier(null, false, 'test', '', '');

$checks = [
    'un jeton valide, avec action et domaine exacts, est accepté' => $validVerifier->verify('turnstile-token', '203.0.113.8'),
    'la clé privée et IP configurée sont transmises uniquement au vérificateur serveur' => $validClient->lastRequest === ['private-test-secret', 'turnstile-token', '203.0.113.8'],
    'un domaine inattendu est refusé' => !$wrongHostVerifier->verify('turnstile-token', null),
    'une action inattendue est refusée' => !$wrongActionVerifier->verify('turnstile-token', null),
    'un jeton Cloudflare invalide est refusé' => !$invalidTokenVerifier->verify('turnstile-token', null),
    'un jeton Cloudflare expiré ou déjà consommé est refusé' => !$expiredTokenVerifier->verify('turnstile-token', null),
    'un jeton absent est refusé lorsque Turnstile est activé' => !$validVerifier->verify(null, null),
    'le développement peut fonctionner sans service tiers' => $devVerifier->verify(null, null),
    'un environnement autre que dev ne contourne pas une vérification désactivée' => !$testDisabledVerifier->verify(null, null),
];

try {
    $downVerifier->verify('turnstile-token', null);
    $checks['une panne Cloudflare produit un refus temporaire 503'] = false;
} catch (ServiceUnavailableHttpException $exception) {
    $checks['une panne Cloudflare produit un refus temporaire 503'] = $exception->getStatusCode() === 503;
}

try {
    $makeVerifier(['success' => true], false, 'prod', '', '');
    $checks['la production ne peut pas démarrer sans Turnstile'] = false;
} catch (LogicException) {
    $checks['la production ne peut pas démarrer sans Turnstile'] = true;
}

try {
    $makeVerifier(['success' => true], true, 'prod', '', 'vitrine.test');
    $checks['une clé privée vide est rejetée'] = false;
} catch (LogicException) {
    $checks['une clé privée vide est rejetée'] = true;
}

foreach ($checks as $label => $passed) {
    fwrite($passed ? STDOUT : STDERR, sprintf("%s %s\n", $passed ? 'OK' : 'ÉCHEC', $label));
}

exit(in_array(false, $checks, true) ? 1 : 0);
