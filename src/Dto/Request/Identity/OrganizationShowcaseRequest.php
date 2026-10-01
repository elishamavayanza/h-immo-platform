<?php

declare(strict_types=1);

namespace App\Dto\Request\Identity;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * OrganizationShowcaseRequest
 *
 * Package : Identity & Access — DTO de requête
 *
 * Présentation publique de l'entreprise sur sa vitrine.
 *
 * Les trois champs sont facultatifs et absents ne veut pas dire « vide » :
 * c'est ce qui permet de corriger un seul champ sans renvoyer les autres. Pour
 * effacer réellement la description, le client envoie `""`, pas `null` — sinon
 * une omission et une mise à zéro seraient indiscernables côté service.
 */
#[OA\Schema(
    title: 'OrganizationShowcaseRequest',
    description: 'Paramètres de la vitrine publique d\'une entreprise.'
)]
final readonly class OrganizationShowcaseRequest
{
    public function __construct(
        #[OA\Property(
            description: 'Identifiant de la page publique, dans l\'URL',
            example: 'immo-plus',
            nullable: true,
            maxLength: 60,
            pattern: '^[a-z0-9]+(?:-[a-z0-9]+)*$'
        )]
        #[Assert\Length(max: 60)]
        #[Assert\Regex(
            pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            message: 'Le slug ne peut contenir que des minuscules, chiffres et tirets.'
        )]
        public ?string $slug = null,

        #[OA\Property(
            description: 'Texte de présentation de l\'entreprise',
            example: 'Location et vente de biens immobiliers à Kinshasa.',
            nullable: true
        )]
        #[Assert\Length(max: 2000)]
        public ?string $publicDescription = null,

        #[OA\Property(
            description: 'Afficher ou masquer la vitrine sur le site public',
            example: true,
            nullable: true
        )]
        public ?bool $isPubliclyListed = null,
    ) {
    }
}