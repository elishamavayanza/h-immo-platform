<?php

declare(strict_types=1);

namespace App\Dto\Request\System;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(title: 'ClientErrorReportRequest', description: 'Rapport technique expurgé d’une erreur inattendue survenue dans l’interface.')]
final readonly class ClientErrorReportRequest
{
    public function __construct(
        #[OA\Property(description: 'Origine technique de l’erreur', enum: ['react_render', 'window_error', 'unhandled_rejection', 'api_network'])]
        #[Assert\Choice(choices: ['react_render', 'window_error', 'unhandled_rejection', 'api_network'])]
        public string $kind,

        #[OA\Property(description: 'Message technique, sans données saisies par l’utilisateur', maxLength: 1200)]
        #[Assert\NotBlank]
        #[Assert\Length(max: 1200)]
        public string $message,

        #[OA\Property(description: 'Pile JavaScript tronquée', maxLength: 7000, nullable: true)]
        #[Assert\Length(max: 7000)]
        public ?string $stack = null,

        #[OA\Property(description: 'Fichier, ligne et colonne de la source si fournis par le navigateur', maxLength: 500, nullable: true)]
        #[Assert\Length(max: 500)]
        public ?string $sourceLocation = null,

        #[OA\Property(description: 'Pile des composants React au moment du rendu', maxLength: 4000, nullable: true)]
        #[Assert\Length(max: 4000)]
        public ?string $componentStack = null,

        #[OA\Property(description: 'Dernières actions d’interface, sans valeurs de formulaire', maxLength: 1800, nullable: true)]
        #[Assert\Length(max: 1800)]
        public ?string $actionTrail = null,

        #[OA\Property(description: 'Chemin interne de la page', maxLength: 500, nullable: true)]
        #[Assert\Length(max: 500)]
        public ?string $route = null,

        #[OA\Property(description: 'Horodatage ISO fourni par le navigateur', maxLength: 40, nullable: true)]
        #[Assert\Length(max: 40)]
        public ?string $occurredAt = null,

        #[OA\Property(description: 'Navigateur et système, tronqués', maxLength: 300, nullable: true)]
        #[Assert\Length(max: 300)]
        public ?string $userAgent = null,
    ) {
    }
}
