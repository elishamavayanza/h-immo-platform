<?php

declare(strict_types=1);

namespace App\Repository\Identity;

use App\Entity\Identity\RevokedToken;
use App\Service\System\DateTimeService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * RevokedTokenRepository
 *
 * @extends ServiceEntityRepository<RevokedToken>
 */
class RevokedTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly DateTimeService $dateTime)
    {
        parent::__construct($registry, RevokedToken::class);
    }

    /**
     * Un jeton est-il révoqué ?
     *
     * Interrogé à chaque requête authentifiée, donc sur le chemin
     * critique. Un seul `SELECT` indexé sur la colonne unique `jti`.
     */
    public function isRevoked(string $jti): bool
    {
        $count = $this->createQueryBuilder('r')
            ->select('COUNT(r.jti)')
            ->andWhere('r.jti = :jti')
            ->setParameter('jti', $jti)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    /**
     * Enregistre la révocation. Retourne `false` si le jeton était déjà
     * révoqué : la double déconnexion est alors un no-op, pas une erreur.
     */
    public function revoke(string $jti, \DateTimeImmutable $expiresAt): bool
    {
        if ($this->isRevoked($jti)) {
            return false;
        }

        $this->getEntityManager()->persist(new RevokedToken($jti, $expiresAt));
        $this->getEntityManager()->flush();

        return true;
    }

    /**
     * Nombre de révocations déjà sans effet, donc candidates à la purge.
     *
     * Même prédicat que `purgeExpired()` : les deux méthodes doivent rester
     * alignées, faute de quoi un `--dry-run` annoncerait un nombre différent
     * de ce que la purge réelle supprimerait.
     */
    public function countExpired(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.expiresAt < :now')
            ->setParameter('now', $this->dateTime->now())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Supprime les lignes dont le jeton correspondant est de toute façon
     * refusé : passé l'échéance, la révocation n'a plus d'effet puisque
     * `JWT::decode` rejette déjà le jeton. Sans cette purge, la table
     * grossirait indéfiniment au rythme des déconnexions.
     *
     * La borne est `expiresAt < now`, jamais `<=`, et jamais un calcul sur
     * `createdAt` : une révocation dont le jeton est encore valide doit rester
     * en table, faute de quoi le jeton révoqué redeviendrait utilisable
     * jusqu'à son échéance naturelle.
     */
    public function purgeExpired(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->delete()
            ->andWhere('r.expiresAt < :now')
            ->setParameter('now', $this->dateTime->now())
            ->getQuery()
            ->execute();
    }
}
