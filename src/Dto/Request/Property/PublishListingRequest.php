<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * PublishListingRequest
 *
 * Package : Property Management — DTO de requête
 *
 * Bascule de publication d'une unité.
 *
 * `isPublished` est un booléen explicite, jamais un booléen implicite : une
 * valeur par défaut « false » dans le payload ferait qu'un envoi maladroit
 * dépublierait l'annonce au lieu de ne rien changer.
 */
#[OA\Schema(
    title: 'PublishListingRequest',
    description: 'Décide si l\'unité apparaît sur la vitrine publique.'
)]
final readonly class PublishListingRequest
{
    public function __construct(
        #[OA\Property(
            description: 'Publier (true) ou retirer (false) l\'annonce de la vitrine publique',
            example: true
        )]
        #[Assert\NotNull(message: 'Le champ isPublished est obligatoire.')]
        public ?bool $isPublished = null,
    ) {
    }
}