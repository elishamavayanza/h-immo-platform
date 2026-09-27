<?php

declare(strict_types=1);

namespace App\DataMapper\Property;

use App\Dto\Request\Property\ParcelRequest;
use App\Dto\Response\Property\ParcelResponse;
use App\Entity\Property\City;
use App\Entity\Property\Parcel;

final class ParcelDataMapper
{
    public function toEntity(ParcelRequest $request, City $city): Parcel
    {
        $parcel = new Parcel();
        $parcel->setCity($city);

        return $this->updateEntity($parcel, $request);
    }

    public function updateEntity(Parcel $parcel, ParcelRequest $request): Parcel
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
            $parcel->setLatitude($request->latitude);
        }
        if ($request->longitude !== null) {
            $parcel->setLongitude($request->longitude);
        }
        if ($request->description !== null) {
            $parcel->setDescription($request->description);
        }

        return $parcel;
    }

    public function toResponse(Parcel $parcel): ParcelResponse
    {
        return new ParcelResponse(
            uuid: $parcel->getUuid(),
            cityUuid: $parcel->getCity()->getUuid(),
            cityName: $parcel->getCity()->getName(),
            reference: $parcel->getReference(),
            titleNumber: $parcel->getTitleNumber(),
            name: $parcel->getName(),
            address: $parcel->getAddress(),
            quarter: $parcel->getQuarter(),
            area: $parcel->getArea(),
            latitude: $parcel->getLatitude(),
            longitude: $parcel->getLongitude(),
            description: $parcel->getDescription(),
            createdAt: $parcel->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $parcel->getUpdatedAt()?->format(\DateTimeInterface::ATOM)
        );
    }
}
