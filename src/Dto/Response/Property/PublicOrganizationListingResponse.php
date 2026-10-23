<?php

declare(strict_types=1);

namespace App\Dto\Response\Property;

/** Annonce publique accompagnée de l'identité publique de son agence. */
final readonly class PublicOrganizationListingResponse
{
    public function __construct(
        public string $organizationSlug,
        public string $organizationName,
        public PublicListingResponse $listing,
    ) {
    }
}
