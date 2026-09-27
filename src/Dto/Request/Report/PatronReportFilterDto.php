<?php

declare(strict_types=1);

namespace App\Dto\Request\Report;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'PatronReportFilterDto',
    description: 'Filtres pour les rapports du Patron (niveau Organisation).'
)]
final class PatronReportFilterDto extends ReportFilterDto
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
        #[OA\Property(description: 'Inclure les détails par ville', default: true)]
        public bool $includeCityBreakdown = true,

        #[OA\Property(description: 'Inclure les indicateurs financiers', default: true)]
        public bool $includeFinancials = true,

        #[OA\Property(description: 'Inclure les impayés', default: true)]
        public bool $includeArrears = true,

        #[OA\Property(description: 'Inclure les dépenses', default: true)]
        public bool $includeExpenses = true,
    ) {
        parent::__construct($periodFrom, $periodTo, $cityUuid, $parcelUuid, $buildingUuid, $unitUuid, $page, $limit, $sortBy, $sortOrder, $format);
    }
}