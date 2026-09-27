<?php

declare(strict_types=1);

namespace App\Dto\Request\Report;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'AdminVilleReportFilterDto',
    description: 'Filtres pour les rapports de l\'Administrateur de Ville (niveau Ville).'
)]
final class AdminVilleReportFilterDto extends ReportFilterDto
{
    public function __construct(
        ?\DateTimeImmutable $periodFrom = null,
        ?\DateTimeImmutable $periodTo = null,
        ?string $cityUuid = null,
        ?string $parcelUuid = null,
        ?string $buildingUuid = null,
        ?string $unitUuid = null,
        int $page = 1,
        int $limit = 20,
        ?string $sortBy = 'createdAt',
        string $sortOrder = 'DESC',
        string $format = 'json',
        #[OA\Property(description: 'Inclure les travailleurs de la ville', default: true)]
        public bool $includeWorkers = true,

        #[OA\Property(description: 'Inclure les activités réalisées', default: true)]
        public bool $includeActivities = true,
    ) {
        parent::__construct($periodFrom, $periodTo, $cityUuid, $parcelUuid, $buildingUuid, $unitUuid, $page, $limit, $sortBy, $sortOrder, $format);
    }
}