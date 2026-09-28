<?php

declare(strict_types=1);

namespace App\Dto\Request\Staff;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * WorkerAssignmentEndRequest
 *
 * Package : Staff Management — DTO de requête
 *
 * Date de fin pour terminer une affectation.
 */
#[OA\Schema(
    title: 'WorkerAssignmentEndRequest',
    description: 'Date de fin d\'affectation.'
)]
final readonly class WorkerAssignmentEndRequest
{
    public function __construct(
        #[OA\Property(
            description: 'Date de fin de l\'affectation',
            format: 'date',
            example: '2026-12-31'
        )]
        #[Assert\NotBlank]
        public ?\DateTimeImmutable $endDate = null,
    ) {
    }
}