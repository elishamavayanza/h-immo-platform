<?php

declare(strict_types=1);

namespace App\Repository\Identity;

use App\Entity\Identity\PasswordResetToken;
use App\Entity\Identity\User;
use DateTimeImmutable;
use App\Repository\UuidParameterTrait;
use App\Service\System\DateTimeService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * PasswordResetTokenRepository
 *
 * Package : Identity & Access
 * Entité  : App\Entity\Identity\PasswordResetToken
 *
 * Requêtes sur les jetons de réinitialisation de mot de passe.
 * Les seules méthodes interrogeant la base le font par *condensat*
 * (tokenHash) : le jeton en clair ne transite que dans la réponse
 * envoyée au demandeur, jamais dans une requête.
 *
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
class PasswordResetTokenRepository extends ServiceEntityRepository
{
    use UuidParameterTrait;
    public function __construct(ManagerRegistry $registry, private readonly DateTimeService $dateTime)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    public function save(PasswordResetToken $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Résout un jeton à partir de son condensat.
     *
     * Seuls les jetons NON consommés et NON expirés sont retournés :
     * un jeton déjà utilisé ou dépassé est considéré comme inexistant,
     * ce qui évite de distinguer "jeton invalide" de "jeton expiré"
     * et ne leaks aucune information sur l'état du compte.
     */
    public function findUsableByTokenHash(string $tokenHash, ?DateTimeImmutable $now = null): ?PasswordResetToken
    {
        $now ??= $this->dateTime->now();

        return $this->createQueryBuilder('t')
            ->andWhere('t.tokenHash = :tokenHash')
            ->andWhere('t.consumedAt IS NULL')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('tokenHash', $tokenHash)
            ->setParameter('now', $now)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Invalide tous les jetons encore utilisables d'un utilisateur.
     *
     * Appelé lors d'une nouvelle demande de réinitialisation : une
     * seule demande active à la fois, ce qui rend obsolète tout lien
     * de récupération précédemment transmis.
     */
    public function consumeAllForUser(User $user, ?DateTimeImmutable $now = null): int
    {
        $now ??= $this->dateTime->now();

        return (int) $this->createQueryBuilder('t')
            ->update()
            ->set('t.consumedAt', ':now')
            ->andWhere('t.user = :user')
            ->andWhere('t.consumedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }

    /**
     * Supprime les jetons expirés ou déjà consommés.
     *
     * Destiné à une commande de maintenance : la table ne doit pas
     * croître indéfiniment.
     */
    public function purgeConsumedAndExpired(?DateTimeImmutable $now = null): int
    {
        $now ??= $this->dateTime->now();

        return (int) $this->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.consumedAt IS NOT NULL')
            ->orWhere('t.expiresAt < :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }
}
