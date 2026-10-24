<?php

declare(strict_types=1);

namespace App\Service\Property;

final readonly class CloudflareTurnstileSiteverifyClient implements TurnstileSiteverifyClientInterface
{
    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function verify(string $secret, string $token, ?string $clientIp): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $handle = curl_init(self::ENDPOINT);
        if ($handle === false) {
            return null;
        }

        $body = ['secret' => $secret, 'response' => $token];
        if ($clientIp !== null && $clientIp !== '') {
            $body['remoteip'] = $clientIp;
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if (!is_string($response) || $status !== 200) {
            return null;
        }

        try {
            $payload = json_decode($response, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }
}
