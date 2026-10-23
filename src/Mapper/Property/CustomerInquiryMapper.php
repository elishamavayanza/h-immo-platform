<?php

declare(strict_types=1);

namespace App\Mapper\Property;

use App\Dto\Response\Property\CustomerInquiryHistoryResponse;
use App\Dto\Response\Property\CustomerInquiryResponse;
use App\Dto\Response\Property\CustomerVisitResponse;
use App\Entity\Property\CustomerInquiry;
use App\Entity\Property\CustomerInquiryHistory;

final class CustomerInquiryMapper
{
    public function toResponse(CustomerInquiry $inquiry): CustomerInquiryResponse
    {
        $listing = $inquiry->getListing();
        $unit = $listing->getUnit();

        return new CustomerInquiryResponse(
            uuid: (string) $inquiry->getUuid(),
            listingId: (string) $listing->getUuid(),
            listingTitle: $listing->getTitle(),
            listingReference: $unit->getReference(),
            city: $unit->getBuilding()->getParcel()->getCity()->getName(),
            building: $unit->getBuilding()->getName(),
            unitType: $unit->getType()->value,
            surface: $unit->getSurface(),
            bedrooms: $unit->getBedrooms(),
            rooms: $unit->getRooms(),
            bathrooms: $unit->getBathrooms(),
            offerType: $listing->getOfferType()->value,
            price: $listing->getPrice(),
            currency: $listing->getCurrency()->value,
            type: $inquiry->getType()->value,
            name: $inquiry->getName(),
            phone: $inquiry->getPhone(),
            email: $inquiry->getEmail(),
            message: $inquiry->getMessage(),
            status: $inquiry->getStatus()->value,
            assignedTo: $inquiry->getAssignedTo()?->getFullName(),
            createdAt: $inquiry->getCreatedAt()->format(DATE_ATOM),
            updatedAt: $inquiry->getUpdatedAt()->format(DATE_ATOM),
            closedReason: $inquiry->getClosedReason(),
            convertedLeaseUuid: $inquiry->getConvertedLease() === null ? null : (string) $inquiry->getConvertedLease()->getUuid(),
            convertedLeaseReference: $inquiry->getConvertedLease()?->getReference(),
        );
    }

    public function toVisitResponse(\App\Entity\Property\CustomerVisit $visit): CustomerVisitResponse
    {
        return new CustomerVisitResponse(
            uuid: (string) $visit->getUuid(),
            inquiryUuid: (string) $visit->getInquiry()->getUuid(),
            scheduledAt: $visit->getScheduledAt()->format(DATE_ATOM),
            status: $visit->getStatus()->value,
            outcome: $visit->getOutcome(),
            createdBy: $visit->getCreatedBy()->getFullName(),
            createdAt: $visit->getCreatedAt()->format(DATE_ATOM),
            updatedAt: $visit->getUpdatedAt()->format(DATE_ATOM),
        );
    }

    public function toHistoryResponse(CustomerInquiryHistory $history): CustomerInquiryHistoryResponse
    {
        return new CustomerInquiryHistoryResponse(
            uuid: (string) $history->getUuid(),
            action: $history->getAction(),
            oldStatus: $history->getOldStatus()?->value,
            newStatus: $history->getNewStatus()->value,
            reason: $history->getReason(),
            actor: $history->getActor()?->getFullName(),
            createdAt: $history->getCreatedAt()->format(DATE_ATOM),
        );
    }
}
