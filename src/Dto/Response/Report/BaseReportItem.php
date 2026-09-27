<?php

declare(strict_types=1);

namespace App\Dto\Response\Report;

use OpenApi\Attributes as OA;

/**
 * BaseReportItem
 *
 * Élément de base pour les lignes de rapport (paginables).
 */
#[OA\Schema(title: 'BaseReportItem')]
abstract class BaseReportItem
{
    public function __construct(
        #[OA\Property(description: 'UUID de l\'entité', format: 'uuid')]
        public string $uuid,

        #[OA\Property(description: 'Date de création', format: 'date-time')]
        public \DateTimeImmutable $createdAt,
    ) {
    }
}