<?php

declare(strict_types=1);

namespace App\Entity\Identity;

use App\Entity\Shared\CreatedOnlyEntity;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * RevokedToken
 *
 * Package  : Identity & Access
 * Table    : revoked_token
 *
 * Jeton d'API révoqué.
 *
 * Un JWT est auto-porteur : sa signature prouve qu'il a été émis par le
 * serveur, mais rien ne permet de le retirer avant son expiration. Sans
 * cette table, « déconnexion » ne serait qu'un mot : le client
 * oublierait le jeton, mais quiconque l'aurait intercepté continuerait
 * d'accéder à l'API jusqu'à l'heure d'expiration.
 *
 * `jti` est l'identifiant unique du jeton (revoke list, claim standard
 * OpenID Connect), et non le jeton lui-même : la ligne ne révèle donc
 * rien d'exploitable en cas de fuite de la base.
 *
 * `expiresAt` est recopié de la revendication `exp` du jeton. Dès qu'un
 * jeton est expiré, sa ligne n'est plus utile et peut être purgée : la
 * table reste proportionnelle aux seuls déconnectages en cours.
 */
#[ORM\Entity]
#[ORM\Table(name: 'revoked_token')]
#[ORM\UniqueConstraint(name: 'uniq_revoked_token_jti', columns: ['jti'])]
#[ORM\Index(name: 'idx_revoked_token_expires', columns: ['expires_at'])]
class RevokedToken extends CreatedOnlyEntity
{
    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $jti;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $expiresAt;

    /**
     * Une ligne de révocation est construite complète et n'est ensuite
     * plus modifiée : elle représente un fait accompli (« ce jeton a été
     * révoqué »), pas un état de transition.
     */
    public function __construct(string $jti, DateTimeImmutable $expiresAt)
    {
        parent::__construct();

        $this->jti = $jti;
        $this->expiresAt = $expiresAt;
    }

    public function getJti(): string
    {
        return $this->jti;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
