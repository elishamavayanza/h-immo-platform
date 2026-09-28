<?php

declare(strict_types=1);

namespace App\Dto\Request\Report;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'AdminImmobilierReportFilterDto',
    description: 'Filtres pour les rapports de l\'Administrateur Immobilier (niveau Patrimoine).'
)]
final class AdminImmobilierReportFilterDto extends ReportFilterDto
{
    public function __construct(
        #[OA\Property(
            description: 'UUID public de l\'organisation concernée. OBLIGATOIRE : le rapport porte sur une organisation, et un compte peut appartenir à plusieurs. Sans cet identifiant, il faudrait en choisir une arbitrairement, et le compte verrait le rapport d\'une société qui n\'est pas la sienne.',
            format: 'uuid',
            example: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'
        )]
        public ?string $organizationUuid = null,
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
        #[OA\Property(description: 'Inclure les unités disponibles', default: true)]
        public bool $includeAvailableUnits = true,

        #[OA\Property(description: 'Inclure l\'évolution de l\'occupation', default: true)]
        public bool $includeOccupancyEvolution = true,

        #[OA\Property(description: 'Inclure les dépenses liées aux biens', default: true)]
        public bool $includePropertyExpenses = true,
    ) {
        parent::__construct($periodFrom, $periodTo, $cityUuid, $parcelUuid, $buildingUuid, $unitUuid, $page, $limit, $sortBy, $sortOrder, $format);
    }
}