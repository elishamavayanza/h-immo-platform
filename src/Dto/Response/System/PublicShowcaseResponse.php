<?php

declare(strict_types=1);

namespace App\Dto\Response\System;

/**
 * Présentation publique d'une entreprise sur sa vitrine.
 *
 * Distinguée de `OrganizationResponse` : seules les informations qu'une
 * entreprise accepte de montrer à un visiteur sans authentification y figurent.
 * Le `uuid` de l'Organization n'y est pas non plus — un visiteur n'a aucune
 * raison de le connaître, et l'exposer Faciliterait l'énumération.
 */
final readonly class PublicShowcaseResponse
{
    public function __construct(
        public string $name,
        public ?string $logo,
        public ?string $description,
        public ?string $address,
        public ?string $city,
        public ?string $country,
        public string $email,
        public string $phone,
        public int $availableUnitsCount,
    ) {
    }
}