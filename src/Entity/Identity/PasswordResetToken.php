<?php

declare(strict_types=1);

namespace App\Entity\Identity;

use App\Entity\Shared\CreatedOnlyEntity;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * PasswordResetToken
 *
 * Package  : Identity & Access
 * Table    : password_reset_token
 *
 * Jeton à usage unique clôturant le flux « mot de passe oublié » :
 * il matérialise une demande de réinitialisation du mot de passe d'un
 * compte, jusqu'à sa consommation ou son expiration.
 *
 * Principes de sécurité appliqués :
 *   - le jeton n'est JAMAIS stocké en clair : seul son condensat SHA-256
 *     est persisté, ce qui empêche qu'une fuite de la base de données
 *     (ou un dump SQL) permette de voler un compte ;
 *   - le jeton est à usage unique : `consumedAt` est renseigné dès la
 *     remise à jour du mot de passe, et la recherche d'un jeton valide
 *     ignore les lignes déjà consommées ;
 *   - le jeton expire : `expiresAt` est fixé à la création et vérifié
 *     à chaque résolution ;
 *   - la table est purgée périodiquement des lignes expirées ou
 *     consommées (cf. PasswordResetService::purgeExpiredTokens()).
 */
#[ORM\Entity]
#[ORM\Table(name: 'password_reset_token')]
#[ORM\Index(name: 'idx_prt_user', columns: ['user_id'])]
class PasswordResetToken extends CreatedOnlyEntity
{
    /**
     * Condensat SHA-256 en hexadecimal du jeton transmis au client.
     * Le jeton en clair n'est jamais stocké.
     */
    #[ORM\Column(type: Types::STRING, length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $consumedAt = null;

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getConsumedAt(): ?DateTimeImmutable
    {
        return $this->consumedAt;
    }

    public function isConsumed(): bool
    {
        return $this->consumedAt !== null;
    }

    public function isExpired(?DateTimeImmutable $now = null): bool
    {
        return ($now ?? new DateTimeImmutable()) > $this->expiresAt;
    }

    /**
     * Le jeton n'est utilisable que s'il est à la fois non consommé et
     * non expiré.
     */
    public function isUsable(?DateTimeImmutable $now = null): bool
    {
        return !$this->isConsumed() && !$this->isExpired($now);
    }

    public function markConsumed(?DateTimeImmutable $now = null): self
    {
        $this->consumedAt = $now ?? new DateTimeImmutable();

        return $this;
    }
}
