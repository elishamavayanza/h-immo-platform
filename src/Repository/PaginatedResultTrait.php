<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\ORM\QueryBuilder;

/**
 * PaginatedResultTrait
 *
 * Factorise la mécanique de pagination appliquée à une requête
 * déjà filtrée par l'appelant.
 *
 * Pourquoi une trait plutôt qu'une méthode par repository : chaque
 * resource a son propre filtre de périmètre (Organization, City,
 * requête de l'utilisateur courant...). Seule la partie "une page de
 * N éléments + le total" est commune, elle est donc écrite une seule
 * fois. Le `COUNT` est dérivé du `SELECT` par `DISTINCT` afin de ne pas
 * compter les lignes dupliquées par un `JOIN`.
 */
trait PaginatedResultTrait
{
    /**
     * Exécute la requête et renvoie la page courante avec le total.
     *
     * @return array{items: list<object>, total: int}
     */
    private function fetchPaginated(QueryBuilder $qb, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        $items = (clone $qb)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $total = (int) (clone $qb)
            ->select('COUNT(DISTINCT ' . $this->paginationRootAlias($qb) . '.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * Alias racine de la requête, déduit de son unique `FROM`.
     *
     * Les repositories de ce projet n'utilisent tous qu'un seul alias
     * racine et aucun sous-select sur l'entité racine, ce qui rend cette
     * déduction fiable ; le `FROM` multiple reste interdit par convention.
     */
    private function paginationRootAlias(QueryBuilder $qb): string
    {
        $from = $qb->getDQLPart('from');

        if ($from === [] || !isset($from[0]) || !($from[0] instanceof \Doctrine\ORM\Query\Expr\From)) {
            throw new \LogicException(
                'Impossible de déterminer l\'alias racine : la requête doit contenir un FROM simple.'
            );
        }

        return $from[0]->getAlias();
    }
}
