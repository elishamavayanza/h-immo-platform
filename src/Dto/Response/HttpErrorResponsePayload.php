<?php

declare(strict_types=1);

namespace App\Dto\Response;

use OpenApi\Attributes as OA;

/**
 * HttpErrorResponsePayload
 *
 * Structure d'erreur HTTP globale retournée lors d'exceptions non gérées
 * ou d'erreurs d'infrastructure (404 Not Found, 403 Forbidden, 500 Internal Server Error, etc.).
 */
#[OA\Schema(
    title: 'HttpErrorResponsePayload',
    description: 'Structure standard d\'une erreur HTTP globale.'
)]
final readonly class HttpErrorResponsePayload
{
    public function __construct(
        #[OA\Property(
            description: 'Code de statut HTTP de l\'erreur',
            example: 404
        )]
        public int $status,

        #[OA\Property(
            description: 'Libellé court de la catégorie d\'erreur',
            example: 'Not Found'
        )]
        public string $error,

        #[OA\Property(
            description: 'Message explicatif lisible par un humain ou le client',
            example: 'La ressource demandée n\'existe pas ou a été supprimée.'
        )]
        public string $message,

        #[OA\Property(
            description: 'Détails complémentaires de l\'exception (ex: stack trace en environnement dev, détails de validation en prod)',
            nullable: true
        )]
        public mixed $details = null,
    ) {
    }
}
