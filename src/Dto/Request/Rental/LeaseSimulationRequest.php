<?php

declare(strict_types=1);

namespace App\Dto\Request\Rental;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/** Données temporaires d'aperçu : elles ne sont jamais persistées. */
final readonly class LeaseSimulationRequest
{
    public function __construct(
        #[Assert\Valid]
        #[OA\Property(ref: new OA\Schema(ref: '#/components/schemas/LeaseRequest'))]
        public LeaseRequest $lease,
        #[Assert\NotNull]
        #[OA\Property(description: 'Dernier jour à inclure dans l’aperçu, sans dépasser la fin du bail.', format: 'date')]
        public ?\DateTimeImmutable $throughDate = null,
        #[Assert\Valid]
        #[OA\Property(description: 'Règles candidates facultatives à comparer sans publication ni écriture.', nullable: true)]
        public ?RentalRuleSimulationCandidateRequest $ruleCandidate = null,
    ) {
    }
}
