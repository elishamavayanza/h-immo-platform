<?php

declare(strict_types=1);

namespace App\Dto\Response\Property;

/**
 * Annonce publique d'une unité disponible.
 *
 * Volontairement distincte de `UnitResponse` : ce qu'un visiteur non
 * authentifié est autorisé à voir n'a rien à voir avec la fiche d'exploitation
 * interne. Aucune donnée de gestion n'y figure — pas de `createdBy`, pas de
 * `updatedAt`, pas de compteur de contrats.
 *
 * `unitId` est un identifiant opaque : c'est le UUID public, déjà la seule
 * référence exposée par le reste de l'API.
 */
final readonly class PublicListingResponse
{
    public function __construct(
        public string $unitId,
        public string $reference,
        public string $type,
        public int $floor,
        public ?string $description,
        public string $monthlyRent,
        public string $currency,
        public ?string $surface,
        public ?int $bedrooms,
        public ?int $rooms,
        public ?int $bathrooms,
        public string $city,
        public string $buildingName,
        /** @var list<array{url: string, position: int}> */
        public array $photos,
    ) {
    }
}