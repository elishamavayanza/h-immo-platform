<?php

declare(strict_types=1);

namespace App\Service\Rental;

use App\Dto\Feedback;
use App\Dto\Request\Rental\ResidentialMovementRequest;
use App\Dto\Request\Rental\ResidentialMovementCorrectionRequest;
use App\Entity\Property\Unit;
use App\Entity\Rental\Lease;
use App\Entity\Rental\ResidentialMovement;
use App\Entity\Rental\Tenant;
use App\Enum\ResidentialMovementStatus;
use App\Enum\ResidentialMovementType;
use App\Enum\LeaseStatus;
use App\Repository\Property\UnitRepository;
use App\Repository\Rental\LeaseRepository;
use App\Repository\Rental\ResidentialMovementRepository;
use App\Repository\Rental\TenantRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\System\AuditLogService;
use App\Service\System\DateTimeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class ResidentialMovementService
{
    public function __construct(
        private ResidentialMovementRepository $movements,
        private TenantRepository $tenants,
        private LeaseRepository $leases,
        private UnitRepository $units,
        private SecurityServiceInterface $security,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private AuditLogService $auditLog,
        private DateTimeService $dateTime,
    ) {
    }

    public function history(string $tenantUuid): Feedback
    {
        $feedback = new Feedback();
        $tenant = $this->findTenant($tenantUuid, $feedback);
        if ($tenant === null) {
            return $feedback->autoInitFlush();
        }
        $this->security->checkTenantAccess($tenant, SecurityAction::VIEW_TENANT);

        return $feedback
            ->setData(array_map(fn (ResidentialMovement $movement): array => $this->toArray($movement), $this->movements->findHistoryForTenant($tenant)))
            ->setFlushDescription('Historique résidentiel récupéré.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function record(string $tenantUuid, ResidentialMovementRequest $request): Feedback
    {
        $feedback = new Feedback();
        $violations = $this->validator->validate($request);
        if (count($violations) > 0) {
            return $feedback->bind($violations)->setFlushDescriptionWithError('Les données du mouvement sont invalides.')->autoInitFlush();
        }

        $tenant = $this->findTenant($tenantUuid, $feedback);
        if ($tenant === null) {
            return $feedback->autoInitFlush();
        }
        $this->security->checkTenantAccess($tenant, SecurityAction::UPDATE_TENANT);

        if ($request->status === ResidentialMovementStatus::COMPLETED && $request->effectiveDate === null) {
            return $feedback->addError('effectiveDate', 'Une date effective est requise pour un mouvement réalisé.')
                ->setFlushDescriptionWithError('La date réelle du mouvement est obligatoire.')
                ->autoInitFlush();
        }
        if (in_array($request->status, [ResidentialMovementStatus::ANNOUNCED, ResidentialMovementStatus::PLANNED], true) && $request->plannedDate === null) {
            return $feedback->addError('plannedDate', 'Une date prévue est requise pour un mouvement annoncé ou planifié.')
                ->setFlushDescriptionWithError('La date prévue du mouvement est obligatoire.')
                ->autoInitFlush();
        }
        if ($request->effectiveDateEstimated && $request->effectiveDate === null) {
            return $feedback->addError('effectiveDate', 'Une date estimée doit être renseignée.')
                ->setFlushDescriptionWithError('La date estimée est absente.')
                ->autoInitFlush();
        }
        if ($request->effectiveDate !== null && $request->effectiveDate > $this->dateTime->today()) {
            return $feedback->addError('effectiveDate', 'La date effective ne peut pas être future.')
                ->setFlushDescriptionWithError('La date effective du mouvement est invalide.')
                ->autoInitFlush();
        }
        if ($request->type === ResidentialMovementType::TRANSFER && ($request->previousUnitUuid === null || $request->nextUnitUuid === null)) {
            return $feedback->addError('nextUnitUuid', 'Un transfert doit indiquer le logement quitté et le nouveau logement.')
                ->setFlushDescriptionWithError('Les deux logements du transfert sont requis.')
                ->autoInitFlush();
        }
        $requiredUnits = match ($request->type) {
            ResidentialMovementType::MOVE_IN,
            ResidentialMovementType::RETURN => ['nextUnitUuid' => $request->nextUnitUuid],
            ResidentialMovementType::DEPARTURE_ANNOUNCED,
            ResidentialMovementType::DEPARTURE_COMPLETED,
            ResidentialMovementType::TEMPORARY_LEAVE => ['previousUnitUuid' => $request->previousUnitUuid],
            default => [],
        };
        foreach ($requiredUnits as $field => $uuid) {
            if ($uuid === null) {
                return $feedback->addError($field, 'Le logement concerné par ce mouvement est obligatoire.')
                    ->setFlushDescriptionWithError('Sélectionnez le logement concerné avant d’enregistrer le mouvement.')
                    ->autoInitFlush();
            }
        }

        $previous = $this->resolveUnit($request->previousUnitUuid, $tenant, 'previousUnitUuid', $feedback);
        $next = $this->resolveUnit($request->nextUnitUuid, $tenant, 'nextUnitUuid', $feedback);
        if (($request->previousUnitUuid !== null && $previous === null) || ($request->nextUnitUuid !== null && $next === null)) {
            return $feedback->setStatus(422)->autoInitFlush();
        }

        $completesDeparture = $request->type === ResidentialMovementType::DEPARTURE_COMPLETED
            && $request->status === ResidentialMovementStatus::COMPLETED;
        $completesTransfer = $request->type === ResidentialMovementType::TRANSFER
            && $request->status === ResidentialMovementStatus::COMPLETED;
        if ($completesTransfer && $request->nextLeaseUuid === null) {
            return $feedback->addError('nextLeaseUuid', 'Sélectionnez le bail brouillon préparé pour le nouveau logement.')
                ->setFlushDescriptionWithError('Un transfert effectif nécessite un nouveau bail et ses conditions financières.')
                ->setStatus(422)->autoInitFlush();
        }
        $lease = null;
        if ($request->leaseUuid !== null) {
            try {
                $lease = $this->leases->findOneByUuid(Uuid::fromString($request->leaseUuid));
            } catch (\InvalidArgumentException) {
                $lease = null;
            }
            if ($lease === null || $lease->getTenant() !== $tenant) {
                return $feedback->addError('leaseUuid', 'Le bail indiqué ne correspond pas à ce locataire.')
                    ->setFlushDescriptionWithError('Le bail associé est invalide.')
                    ->setStatus(422)
                    ->autoInitFlush();
            }
            $this->security->checkLeaseAccess(
                $lease,
                $completesDeparture || $completesTransfer
                    ? SecurityAction::TERMINATE_LEASE
                    : SecurityAction::VIEW_LEASE,
            );
        }

        if (($completesDeparture || $completesTransfer) && $lease === null && $previous !== null) {
            $activeLease = $this->leases->findActiveLeaseForUnit($previous);
            if ($activeLease !== null && $activeLease->getTenant() === $tenant) {
                $lease = $activeLease;
                $this->security->checkLeaseAccess($lease, SecurityAction::TERMINATE_LEASE);
            }
        }

        $nextLease = null;
        if ($completesTransfer) {
            try {
                $nextLease = $this->leases->findOneByUuid(Uuid::fromString((string) $request->nextLeaseUuid));
            } catch (\InvalidArgumentException) {
                $nextLease = null;
            }
            if ($lease === null || $lease->getTenant() !== $tenant || $lease->getStatus() !== LeaseStatus::ACTIVE) {
                return $feedback->addError('leaseUuid', 'Le logement quitté doit être couvert par un bail actif de ce locataire.')
                    ->setFlushDescriptionWithError('Le bail actuel du transfert est introuvable ou inactif.')
                    ->setStatus(422)->autoInitFlush();
            }
            if ($nextLease === null || $nextLease->getTenant() !== $tenant || $nextLease->getOrganization() !== $tenant->getOrganization()) {
                return $feedback->addError('nextLeaseUuid', 'Le nouveau bail doit appartenir au même locataire et à la même organisation.')
                    ->setFlushDescriptionWithError('Le bail de destination est invalide.')
                    ->setStatus(422)->autoInitFlush();
            }
            $this->security->checkLeaseAccess($nextLease, SecurityAction::ACTIVATE_LEASE);
            if ($nextLease->getStatus() !== LeaseStatus::DRAFT || $request->effectiveDate === null || $nextLease->getStartDate() != $request->effectiveDate) {
                return $feedback->addError('nextLeaseUuid', 'Le bail de destination doit être en brouillon et commencer à la date effective du transfert.')
                    ->setFlushDescriptionWithError('Vérifiez le statut et la date de début du nouveau bail.')
                    ->setStatus(422)->autoInitFlush();
            }
            $destinationUnits = [$nextLease->getUnit(), ...$nextLease->getAdditionalUnits()->toArray()];
            if ($next === null || !in_array($next, $destinationUnits, true)) {
                return $feedback->addError('nextUnitUuid', 'Le logement choisi ne fait pas partie du bail de destination.')
                    ->setFlushDescriptionWithError('Le bail et le nouveau logement ne correspondent pas.')
                    ->setStatus(422)->autoInitFlush();
            }
        }

        if ($lease !== null && $previous !== null) {
            $leaseUnits = [$lease->getUnit(), ...$lease->getAdditionalUnits()->toArray()];
            if (!in_array($previous, $leaseUnits, true)) {
                return $feedback->addError('previousUnitUuid', 'Le logement quitté ne fait pas partie du bail sélectionné.')
                    ->setFlushDescriptionWithError('Le bail et le logement quitté ne correspondent pas.')
                    ->setStatus(422)
                    ->autoInitFlush();
            }
        }

        if (($completesDeparture || $completesTransfer) && $lease !== null && $lease->getStatus() === LeaseStatus::ACTIVE) {
            $effectiveDate = $request->effectiveDate;
            if ($effectiveDate === null || $effectiveDate < $lease->getStartDate()) {
                return $feedback->addError('effectiveDate', 'La date de départ doit être égale ou postérieure au début du bail.')
                    ->setFlushDescriptionWithError('La date de départ ne correspond pas à la période du bail.')
                    ->setStatus(422)
                    ->autoInitFlush();
            }
        }

        $movement = (new ResidentialMovement())
            ->setOrganization($tenant->getOrganization())
            ->setTenant($tenant)
            ->setRecordedBy($this->security->getCurrentUser())
            ->setType($request->type)
            ->setStatus($request->status)
            ->setLease($lease)
            ->setNextLease($nextLease)
            ->setPreviousUnit($previous)
            ->setNextUnit($next)
            ->setPlannedDate($request->plannedDate)
            ->setEffectiveDate($request->effectiveDate)
            ->setEffectiveDateEstimated($request->effectiveDateEstimated)
            ->setReason($request->reason)
            ->setDetails($request->details);

        $closedLease = null;
        $transferApplied = false;
        $transferConflict = false;
        $this->entityManager->wrapInTransaction(function () use ($movement, $completesDeparture, $completesTransfer, $lease, $nextLease, $request, $tenant, &$closedLease, &$transferApplied, &$transferConflict): void {
            if ($completesTransfer && $lease !== null && $nextLease !== null) {
                $destinationUnits = [$nextLease->getUnit(), ...$nextLease->getAdditionalUnits()->toArray()];
                $roots = [];
                $destinationSpaces = [];
                foreach ($destinationUnits as $destinationUnit) {
                    $root = $this->units->findSpaceRoot($destinationUnit);
                    $roots[$root->getId()] = $root;
                    foreach ($this->units->findOverlappingSpaces($destinationUnit) as $space) {
                        $destinationSpaces[$space->getId()] = $space;
                    }
                }
                ksort($roots);
                foreach ($roots as $root) $this->units->lockForUpdate($root);
                foreach ($destinationSpaces as $space) {
                    if ($space->getOperationalStatus() !== \App\Enum\UnitOperationalStatus::AVAILABLE) {
                        $transferConflict = true;
                        return;
                    }
                }
                if ($this->leases->findActiveLeaseForRelatedSpaces(array_values($destinationSpaces), $lease->getUuid()) !== null) {
                    $transferConflict = true;
                    return;
                }

                $lease->setStatus(LeaseStatus::TERMINATED)
                    ->setTerminationDate($request->effectiveDate)
                    ->setTerminationReason($request->reason ?: 'Transfert résidentiel');
                $nextLease->setStatus(LeaseStatus::ACTIVE);
                $closedLease = $lease;
                $transferApplied = true;
            }
            if ($completesDeparture && $lease !== null && $lease->getStatus() === LeaseStatus::ACTIVE) {
                $lease->setStatus(LeaseStatus::TERMINATED);
                $lease->setTerminationDate($request->effectiveDate);
                $lease->setTerminationReason($request->reason ?: 'Départ effectif enregistré');
                $closedLease = $lease;
            }

            $this->entityManager->persist($movement);
            $this->entityManager->flush();
            $this->auditLog->log(
                action: 'RECORD_RESIDENTIAL_MOVEMENT',
                entityType: ResidentialMovement::class,
                entityId: $movement->getId(),
                organization: $tenant->getOrganization(),
                user: $this->security->getCurrentUser(),
                newValues: [
                    'type' => $movement->getType()->value,
                    'status' => $movement->getStatus()->value,
                    'tenantUuid' => $tenant->getUuid()->toRfc4122(),
                    'leaseUuid' => $lease?->getUuid()->toRfc4122(),
                    'nextLeaseUuid' => $nextLease?->getUuid()->toRfc4122(),
                ],
            );

            if ($closedLease !== null) {
                $terminationDate = $closedLease->getTerminationDate();
                $this->auditLog->log(
                    action: 'TERMINATE_LEASE',
                    entityType: Lease::class,
                    entityId: $closedLease->getId(),
                    organization: $closedLease->getOrganization(),
                    user: $this->security->getCurrentUser(),
                    oldValues: ['status' => LeaseStatus::ACTIVE->value],
                    newValues: [
                        'status' => LeaseStatus::TERMINATED->value,
                        'terminationDate' => $terminationDate === null ? null : $this->dateTime->format($terminationDate, 'Y-m-d'),
                        'terminationReason' => $closedLease->getTerminationReason(),
                        'movementUuid' => $movement->getUuid()->toRfc4122(),
                    ],
                );
            }
            if ($transferApplied && $nextLease !== null) {
                $this->auditLog->log(
                    action: 'ACTIVATE_LEASE',
                    entityType: Lease::class,
                    entityId: $nextLease->getId(),
                    organization: $nextLease->getOrganization(),
                    user: $this->security->getCurrentUser(),
                    oldValues: ['status' => LeaseStatus::DRAFT->value],
                    newValues: [
                        'status' => LeaseStatus::ACTIVE->value,
                        'transferUuid' => $movement->getUuid()->toRfc4122(),
                        'previousLeaseUuid' => $lease?->getUuid()->toRfc4122(),
                    ],
                );
            }
        });

        if ($transferConflict) {
            return $feedback->addError('nextLeaseUuid', 'Le logement de destination est indisponible ou vient d’être loué.')
                ->setFlushDescriptionWithError('Le transfert a été annulé; les deux baux et l’historique restent inchangés.')
                ->autoInitFlush()->setStatus(409);
        }

        return $feedback->setData($this->toArray($movement))
            ->setFlushDescription($transferApplied
                ? 'Le transfert est enregistré : l’ancien bail est clôturé et le bail préparé du nouveau logement est activé. Les loyers et paiements restent dans leurs baux respectifs.'
                : ($closedLease !== null
                ? 'Le départ est enregistré et le bail a été clôturé à la date effective. Les loyers, paiements et dépôts déjà enregistrés sont conservés.'
                : 'Le mouvement a été enregistré dans l’historique. Aucun contrat ni montant financier n’a été modifié.'))
            ->autoInitFlush()
            ->setStatus(201);
    }

    public function correct(string $tenantUuid, string $movementUuid, ResidentialMovementCorrectionRequest $request): Feedback
    {
        $feedback = new Feedback();
        $tenant = $this->findTenant($tenantUuid, $feedback);
        if ($tenant === null) return $feedback->autoInitFlush();
        $this->security->checkTenantAccess($tenant, SecurityAction::UPDATE_TENANT);
        $violations = $this->validator->validate($request);
        if (count($violations) > 0) return $feedback->bind($violations)->setFlushDescriptionWithError('Les données de correction sont invalides.')->autoInitFlush();

        try {
            $movement = $this->movements->findOneForTenant(Uuid::fromString($movementUuid), $tenant);
        } catch (\InvalidArgumentException) {
            $movement = null;
        }
        if ($movement === null) return $feedback->setErrorFlushDescription('Mouvement introuvable.')->autoInitFlush()->setStatus(404);
        if ($movement->getStatus() === ResidentialMovementStatus::COMPLETED || $request->status === ResidentialMovementStatus::COMPLETED) {
            return $feedback->addError('status', 'Un mouvement réalisé ne peut pas être réécrit ; consignez une opération compensatrice autorisée.')
                ->setFlushDescriptionWithError('La correction directe d’un mouvement réalisé est interdite.')
                ->autoInitFlush()->setStatus(409);
        }
        if ($request->effectiveDate !== null && $request->effectiveDate > $this->dateTime->today()) {
            return $feedback->addError('effectiveDate', 'La date effective ne peut pas être future.')
                ->setFlushDescriptionWithError('La date réelle est invalide.')->autoInitFlush();
        }
        if (in_array($request->status, [ResidentialMovementStatus::ANNOUNCED, ResidentialMovementStatus::PLANNED], true) && $request->plannedDate === null) {
            return $feedback->addError('plannedDate', 'Une date prévue est obligatoire pour cet état.')
                ->setFlushDescriptionWithError('La date prévue manque.')->autoInitFlush();
        }
        if ($request->type === ResidentialMovementType::TRANSFER && ($request->previousUnitUuid === null || $request->nextUnitUuid === null)) {
            return $feedback->addError('nextUnitUuid', 'Une correction de transfert doit conserver les deux logements.')
                ->setFlushDescriptionWithError('Les logements du transfert sont requis.')->autoInitFlush();
        }

        $previous = $this->resolveUnit($request->previousUnitUuid, $tenant, 'previousUnitUuid', $feedback);
        $next = $this->resolveUnit($request->nextUnitUuid, $tenant, 'nextUnitUuid', $feedback);
        if (($request->previousUnitUuid !== null && $previous === null) || ($request->nextUnitUuid !== null && $next === null)) return $feedback->autoInitFlush();
        $lease = $this->resolveLease($request->leaseUuid, $tenant, 'leaseUuid', $feedback);
        $nextLease = $this->resolveLease($request->nextLeaseUuid, $tenant, 'nextLeaseUuid', $feedback);
        if (($request->leaseUuid !== null && $lease === null) || ($request->nextLeaseUuid !== null && $nextLease === null)) return $feedback->autoInitFlush();

        $oldValues = $this->toArray($movement);
        $newValues = [
            'type' => $request->type->value, 'status' => $request->status->value,
            'leaseUuid' => $lease?->getUuid()->toRfc4122(), 'nextLeaseUuid' => $nextLease?->getUuid()->toRfc4122(),
            'previousUnitUuid' => $previous?->getUuid()->toRfc4122(), 'nextUnitUuid' => $next?->getUuid()->toRfc4122(),
            'plannedDate' => $request->plannedDate?->format('Y-m-d'), 'effectiveDate' => $request->effectiveDate?->format('Y-m-d'),
            'effectiveDateEstimated' => $request->effectiveDateEstimated, 'reason' => $request->reason, 'details' => $request->details,
            'correctionReason' => $request->correctionReason,
        ];
        $this->entityManager->wrapInTransaction(function () use ($movement, $request, $lease, $nextLease, $previous, $next, $tenant, $oldValues, $newValues): void {
            $movement->setType($request->type)->setStatus($request->status)->setLease($lease)->setNextLease($nextLease)
                ->setPreviousUnit($previous)->setNextUnit($next)->setPlannedDate($request->plannedDate)
                ->setEffectiveDate($request->effectiveDate)->setEffectiveDateEstimated($request->effectiveDateEstimated)
                ->setReason($request->reason)->setDetails($request->details)->setCorrectionReason(trim($request->correctionReason));
            $this->entityManager->flush();
            $this->auditLog->log(
                action: 'CORRECT_RESIDENTIAL_MOVEMENT', entityType: ResidentialMovement::class, entityId: (int) $movement->getId(),
                organization: $tenant->getOrganization(), user: $this->security->getCurrentUser(),
                oldValues: $oldValues, newValues: $newValues,
            );
        });

        return $feedback->setData($this->toArray($movement))->setFlushDescription('Le mouvement a été corrigé ; les anciennes et nouvelles valeurs sont conservées dans l’audit.')
            ->autoInitFlush()->setStatus(200);
    }

    private function findTenant(string $uuid, Feedback $feedback): ?Tenant
    {
        try {
            $tenant = $this->tenants->findOneByUuid(Uuid::fromString($uuid));
        } catch (\InvalidArgumentException) {
            $tenant = null;
        }
        if ($tenant === null) {
            $feedback->setErrorFlushDescription('Locataire introuvable.')->setStatus(404);
        }

        return $tenant;
    }

    private function resolveUnit(?string $uuid, Tenant $tenant, string $field, Feedback $feedback): ?Unit
    {
        if ($uuid === null) {
            return null;
        }
        try {
            $unit = $this->units->findOneByUuid(Uuid::fromString($uuid));
        } catch (\InvalidArgumentException) {
            $unit = null;
        }
        if ($unit === null || $unit->getBuilding()->getParcel()->getCity()->getOrganization() !== $tenant->getOrganization()) {
            $feedback->addError($field, 'Le logement doit appartenir à la même organisation que le locataire.');

            return null;
        }
        $this->security->checkUnitAccess($unit, SecurityAction::VIEW_UNIT);

        return $unit;
    }

    private function resolveLease(?string $uuid, Tenant $tenant, string $field, Feedback $feedback): ?Lease
    {
        if ($uuid === null) return null;
        try { $lease = $this->leases->findOneByUuid(Uuid::fromString($uuid)); } catch (\InvalidArgumentException) { $lease = null; }
        if ($lease === null || $lease->getTenant() !== $tenant) {
            $feedback->addError($field, 'Le bail doit appartenir au même locataire et à la même organisation.');
            return null;
        }
        $this->security->checkLeaseAccess($lease, SecurityAction::VIEW_LEASE);
        return $lease;
    }

    private function toArray(ResidentialMovement $movement): array
    {
        return [
            'uuid' => $movement->getUuid()->toRfc4122(),
            'type' => $movement->getType()->value,
            'status' => $movement->getStatus()->value,
            'leaseUuid' => $movement->getLease()?->getUuid()->toRfc4122(),
            'nextLeaseUuid' => $movement->getNextLease()?->getUuid()->toRfc4122(),
            'previousUnitUuid' => $movement->getPreviousUnit()?->getUuid()->toRfc4122(),
            'previousUnitReference' => $movement->getPreviousUnit()?->getReference(),
            'nextUnitUuid' => $movement->getNextUnit()?->getUuid()->toRfc4122(),
            'nextUnitReference' => $movement->getNextUnit()?->getReference(),
            'plannedDate' => $movement->getPlannedDate()?->format('Y-m-d'),
            'effectiveDate' => $movement->getEffectiveDate()?->format('Y-m-d'),
            'effectiveDateEstimated' => $movement->isEffectiveDateEstimated(),
            'reason' => $movement->getReason(),
            'details' => $movement->getDetails(),
            'correctionReason' => $movement->getCorrectionReason(),
            'recordedAt' => $this->dateTime->format($movement->getCreatedAt(), 'Y-m-d H:i:s'),
            'recordedBy' => $movement->getRecordedBy()->getFullName(),
        ];
    }
}
