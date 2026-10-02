<?php

declare(strict_types=1);

namespace App\Mapper\Property;

use App\Dto\Request\Property\ParcelRequest;
use App\Dto\Response\Property\ParcelResponse;
use App\Entity\Property\Parcel;

/**
 * ParcelMapper
 *
 * Package : Property Management — Service de mapping d'entité
 *
 * Assure la conversion bidirectionnelle entre l'entité Parcel
 * et les objets DTO de requête et de réponse associés.
 * La ville parente est positionnée par le service appelant : le mapper
 * ne fait que recopier les colonnes scalaires de la parcelle.
 */
final class ParcelMapper
{
    public function copyToEntity(ParcelRequest $request, Parcel $parcel): Parcel
    {
        if ($request->reference !== null) {
            $parcel->setReference($request->reference);
        }
        if ($request->titleNumber !== null) {
            $parcel->setTitleNumber($request->titleNumber);
        }
        if ($request->name !== null) {
            $parcel->setName($request->name);
        }
        if ($request->address !== null) {
            $parcel->setAddress($request->address);
        }
        if ($request->quarter !== null) {
            $parcel->setQuarter($request->quarter);
        }
        if ($request->area !== null) {
            $parcel->setArea($request->area);
        }
        if ($request->latitude !== null) {
            $parcel->setLatitude((string) $request->latitude);
        }
        if ($request->longitude !== null) {
            $parcel->setLongitude((string) $request->longitude);
        }
        if ($request->description !== null) {
            $parcel->setDescription($request->description);
        }

        return $parcel;
    }

    /**
     * Delegue la projection Entite -> DTO a la fabrique statique du DTO
     * de reponse, qui constitue l'unique source de verite du mapping
     * en lecture (aucune duplication de la liste des champs).
     */
    public function toResponse(Parcel $parcel): ParcelResponse
    {
        return ParcelResponse::fromEntity($parcel);
    }

}
