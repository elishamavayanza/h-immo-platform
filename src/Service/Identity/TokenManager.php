<?php

declare(strict_types=1);

namespace App\Service\Identity;

use App\Dto\Response\Identity\SessionUserResponse;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * TokenManager
 *
 * Émet et vérifie le jeton d'API (JWT HS256).
 *
 * Le jeton porte l'identité et les droits du compte : c'est ce qui permet
 * à un client de retrouver son état sans'appel réseau supplémentaire, et
 * ce qui rend le jeton auto-porteur.
 *
 * Deux conséquences, assumées :
 *
 * 1. Un changement de rôle (promotion, rétrogradation, désactivation) ne
 *    s'applique qu'aux jetons émis APRÈS ce changement. Un jeton déjà
 *    délivré reste valable jusqu'à expiration. La durée de vie est donc
 *    courte (1 h par défaut) et non renouvelable indefiniment.
 *    `ApiTokenAuthenticator` recharge l'utilisateur en base à chaque
 *    requête et refuse un compte désactivé ou supprimé, ce qui referme
 *    le risque le plus grave indépendamment du contenu du jeton.
 *
 * 2. Un jeton ne peut pas être révoqué par signature. La déconnexion
 *    ajoute son `jti` à une liste de révocation (`RevokedToken`).
 *
 * L'algorithme est fixé explicitement à HS256 à l'émission ET à la
 * vérification. Ne jamais dériver l'algorithme du jeton reçu : c'est
 * l'attaque « algorithm confusion » (un attaquant passe le jeton en
 * `none`, ou en asymétrique avec la clé publique en signature).
 */
final class TokenManager
{
    private const ALGORITHM = 'HS256';

    private readonly string $jwtSecret;

    public function __construct(
        string $jwtSecret,
        private readonly int $jwtTtl,
    ) {
        // Une clé absente ou courte est le seul cas où une erreur de
        // configuration rend l'API silencieusement forçable : on refuse
        // de démarrer plutôt que d'émettre des jetons signing avec une
        // chaîne vide que n'importe qui peut retrouver dans le dépôt.
        if ($jwtSecret === '') {
            throw new \LogicException(
                'JWT_SECRET est vide. Générez une clé avec : '
                . "php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;' "
                . 'et placez-la dans .env.local.'
            );
        }

        if (strlen($jwtSecret) < 32) {
            throw new \LogicException(sprintf(
                'JWT_SECRET doit faire au moins 32 caractères (HS256 attend une clé de 256 bits) ; %d fournis.',
                strlen($jwtSecret)
            ));
        }

        if ($jwtTtl < 60) {
            throw new \LogicException(sprintf(
                'JWT_TTL doit valoir au moins 60 secondes ; %d fourni. Un jeton expirant avant cela '
                . 'serait inutilisable.',
                $jwtTtl
            ));
        }

        $this->jwtSecret = $jwtSecret;
    }

    /**
     * Émet un jeton pour l'utilisateur authentifié.
     *
     * @return array{token: string, jti: string, issuedAt: \DateTimeImmutable, expiresAt: \DateTimeImmutable}
     */
    public function issue(SessionUserResponse $user): array
    {
        $issuedAt = new \DateTimeImmutable();
        $expiresAt = $issuedAt->add(new \DateInterval('PT' . $this->jwtTtl . 'S'));

        // `jti` identifie le jeton pour la révocation.
        $jti = bin2hex(random_bytes(16));

        $claims = [
            'iss' => 'himmo',
            'aud' => 'himmo-api',
            'sub' => $user->uuid,
            'jti' => $jti,
            'iat' => $issuedAt->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
            'email' => $user->email,
            'fullName' => $user->fullName,
            'platformRole' => $user->platformRole?->value,
            'roles' => $user->roles,
            'cityScope' => $user->cityScope->value,
            'isActive' => $user->isActive,
            // Les rôles métier voyagent avec le jeton : ils sont ce qui
            // détermine l'accès aux Organizations, et un client a besoin
            // de les connaître pour afficher les bons sélecteurs.
            'organizations' => array_map(
                static fn ($membership): array => [
                    'uuid' => $membership->uuid,
                    'code' => $membership->code,
                    'role' => $membership->role->value,
                ],
                $user->organizations,
            ),
        ];

        return [
            'token' => JWT::encode($claims, $this->jwtSecret, self::ALGORITHM),
            'jti' => $jti,
            'issuedAt' => $issuedAt,
            'expiresAt' => $expiresAt,
        ];
    }

    /**
     * Vérifie la signature et l'expiration, puis renvoie les claims.
     *
     * @throws \UnexpectedValueException si le jeton est illisible, mal
     *                                   signé, expiré ou non encore valide
     */
    public function parse(string $token): array
    {
        // `Key` porte l'algorithme attendu : `JWT::decode` refuse un jeton
        // dont l'en-tête `alg` diffère, ce qui neutralise la confusion
        // d'algorithme.
        $decoded = JWT::decode($token, new Key($this->jwtSecret, self::ALGORITHM));

        return (array) $decoded;
    }

    public function ttl(): int
    {
        return $this->jwtTtl;
    }
}
