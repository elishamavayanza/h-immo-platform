<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Symfony\Component\Uid\Uuid;

/**
 * Conversion des UUID publics vers leur représentation en base.
 *
 * ⚠ Piège Doctrine ORM : les colonnes UUID sont stockées en `BINARY(16)`
 * (cf. `BaseEntity::$uuid`). Un objet `Uuid` passé tel quel comme
 * paramètre DQL est converti par DBAL en sa chaîne « 1bfafe39-20d0-… »
 * (36 caractères) : la comparaison se fait alors entre 16 octets et 36
 * caractères et ne correspond JAMAIS. Le symptôme est trompeur : la
 * requête s'exécute sans erreur et renvoie simplement zéro résultat.
 *
 * Concrètement, `findOneByUuid()` retournait `null` pour des lignes
 * existantes, ce qui faisait répondre 404 sur tous les endpoints de
 * consultation, de modification et de suppression.
 *
 * Le persister de `findOneBy(['uuid' => $uuid])` échappe au problème car
 * il connaît le type de colonne, mais tout DQL construit à la main doit
 * convertir explicitement.
 */
trait UuidParameterTrait
{
    /**
     * UUID prêt à être lié dans une comparaison DQL.
     */
    private function bindableUuid(Uuid $uuid): string
    {
        return $uuid->toBinary();
    }

    /**
     * Liste d'UUID prête à être liée dans un `IN (:param)`.
     *
     * Le type `ArrayParameterType::BINARY` est indispensable : passer
     * `'binary'` sur un tableau provoque une conversion « array to
     * string » et ne trouve aucun résultat.
     *
     * @param list<Uuid|string> $uuids
     * @return list<string>
     */
    private function bindableUuids(array $uuids): array
    {
        return array_map(
            static fn (Uuid|string $uuid): string => $uuid instanceof Uuid
                ? $uuid->toBinary()
                : Uuid::fromString($uuid)->toBinary(),
            array_values($uuids)
        );
    }
}
