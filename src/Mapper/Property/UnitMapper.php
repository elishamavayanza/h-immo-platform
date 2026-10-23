<?php

declare(strict_types=1);

namespace App\Mapper\Property;

use App\Dto\Request\Property\UnitRequest;
use App\Dto\Response\Property\PublicListingResponse;
use App\Dto\Response\Property\UnitResponse;
use App\Entity\Property\Unit;
use App\Entity\Property\Listing;
use App\Enum\UnitOccupancyStatus;

/**
 * UnitMapper
 *
 * Package : Property Management — Service de mapping d'entité
 *
 * Assure la conversion bidirectionnelle entre l'entité Unit
 * et les objets DTO de requête et de réponse associés.
 * Le bâtiment parent est positionné par le service appelant : le mapper
 * ne fait que recopier les colonnes scalaires de l'unité.
 */
final class UnitMapper
{
    public function copyToEntity(UnitRequest $request, Unit $unit): Unit
    {
        if ($request->reference !== null) {
            $unit->setReference($request->reference);
        }
        if ($request->type !== null) {
            $unit->setType($request->type);
        }
        if ($request->operationalStatus !== null) {
            $unit->setOperationalStatus($request->operationalStatus);
        }
        if ($request->commercialActivity !== null) {
            $unit->setCommercialActivity($request->commercialActivity);
        }
        if ($request->floor !== null) {
            $unit->setFloor($request->floor);
        }
        if ($request->floorLabel !== null) {
            $unit->setFloorLabel($request->floorLabel === '' ? null : trim($request->floorLabel));
        }
        if ($request->surface !== null) {
            $unit->setSurface($request->surface);
        }
        if ($request->bedrooms !== null) {
            $unit->setBedrooms($request->bedrooms);
        }
        if ($request->rooms !== null) {
            $unit->setRooms($request->rooms);
        }
        if ($request->bathrooms !== null) {
            $unit->setBathrooms($request->bathrooms);
        }
        if ($request->monthlyRent !== null) {
            $unit->setMonthlyRent($request->monthlyRent);
        }
        if ($request->currency !== null) {
            $unit->setCurrency($request->currency);
        }
        if ($request->description !== null) {
            $unit->setDescription($request->description);
        }

        return $unit;
    }

    /**
     * Delegue la projection Entite -> DTO a la fabrique statique du DTO
     * de reponse, qui constitue l'unique source de verite du mapping
     * en lecture (aucune duplication de la liste des champs).
     */
    public function toResponse(Unit $unit, UnitOccupancyStatus $occupancyStatus = UnitOccupancyStatus::VACANT): UnitResponse
    {
        return UnitResponse::fromEntity($unit, $occupancyStatus);
    }

    /**
     * Annonce publique d'une unité disponible.
     *
     * La ville et le nom du bâtiment sont résolus ici plutôt que dans le
     * service : la chaîne `Unit → Building → Parcel → City` est une
     * navigation d'entité, donc une affaire de mapping.
     *
     * @param list<array{url: string, position: int}> $photos galerie déjà ordonnée
     */
    public function toPublicListing(
        Unit $unit,
        array $photos,
        Listing $listing
    ): PublicListingResponse {
        $snapshot = $listing->getPublicSnapshot() ?? [];

        return new PublicListingResponse(
            unitId: (string) $unit->getUuid(),
            listingId: (string) $listing->getUuid(),
            title: $listing->getTitle(),
            offerType: $listing->getOfferType()->value,
            price: $listing->getPrice(),
            reference: (string) ($snapshot['reference'] ?? ''),
            type: (string) ($snapshot['type'] ?? ''),
            floor: (int) ($snapshot['floor'] ?? 0),
            description: $listing->getDescription(),
            monthlyRent: $listing->getOfferType()->value === 'rent' ? $listing->getPrice() : '0.00',
            currency: $listing->getCurrency()->value,
            surface: isset($snapshot['surface']) ? (string) $snapshot['surface'] : null,
            bedrooms: isset($snapshot['bedrooms']) ? (int) $snapshot['bedrooms'] : null,
            rooms: isset($snapshot['rooms']) ? (int) $snapshot['rooms'] : null,
            bathrooms: isset($snapshot['bathrooms']) ? (int) $snapshot['bathrooms'] : null,
            city: (string) ($snapshot['city'] ?? ''),
            buildingName: (string) ($snapshot['buildingName'] ?? ''),
            photos: $photos,
        );
    }
}
