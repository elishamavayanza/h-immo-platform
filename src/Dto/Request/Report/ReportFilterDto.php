<?php

declare(strict_types=1);

namespace App\Dto\Request\Report;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'ReportFilterDto',
    description: 'Critères de filtrage communs pour les rapports administratifs.'
)]
class ReportFilterDto
{
    public function __construct(
        #[OA\Property(description: 'Date de début de la période', format: 'date', nullable: true, example: '2026-01-01')]
        #[Assert\Type(\DateTimeImmutable::class)]
        public ?\DateTimeImmutable $periodFrom = null,

        #[OA\Property(description: 'Date de fin de la période', format: 'date', nullable: true, example: '2026-12-31')]
        #[Assert\Type(\DateTimeImmutable::class)]
        public ?\DateTimeImmutable $periodTo = null,

        #[OA\Property(description: 'UUID de la ville', format: 'uuid', nullable: true)]
        public ?string $cityUuid = null,

        #[OA\Property(description: 'UUID de la parcelle', format: 'uuid', nullable: true)]
        public ?string $parcelUuid = null,

        #[OA\Property(description: 'UUID de l\'immeuble', format: 'uuid', nullable: true)]
        public ?string $buildingUuid = null,

        #[OA\Property(description: 'UUID de l\'unité', format: 'uuid', nullable: true)]
        public ?string $unitUuid = null,

        #[OA\Property(description: 'Numéro de page', default: 1)]
        #[Assert\Positive]
        public int $page = 1,

        #[OA\Property(description: 'Nombre d\'éléments par page', default: 20)]
        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 20,

        #[OA\Property(description: 'Champ de tri', example: 'createdAt', nullable: true)]
        public ?string $sortBy = 'createdAt',

        #[OA\Property(description: 'Direction du tri', example: 'DESC', default: 'DESC')]
        #[Assert\Choice(choices: ['ASC', 'DESC', 'asc', 'desc'])]
        public string $sortOrder = 'DESC',

        #[OA\Property(description: 'Format de sortie : json ou pdf', example: 'json', default: 'json')]
        #[Assert\Choice(choices: ['json', 'pdf'])]
        public string $format = 'json',
    ) {
    }
}