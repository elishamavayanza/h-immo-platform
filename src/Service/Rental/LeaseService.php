<?php

declare(strict_types=1);

namespace App\Service\Rental;

use App\Dto\Feedback;
use App\Dto\Request\Rental\LeaseFilterDto;
use App\Dto\Request\Rental\LeaseRequest;
use App\Dto\Request\Rental\LeaseSimulationRequest;
use App\Dto\Request\Rental\LeaseOccupantRequest;
use App\Dto\Request\Rental\LeaseOccupantDepartureRequest;
use App\Entity\Identity\User;
use App\Entity\Property\Unit;
use App\Entity\Rental\Lease;
use App\Entity\Rental\LeaseOccupant;
use App\Entity\Rental\Tenant;
use App\Enum\LeaseStatus;
use App\Mapper\Rental\LeaseMapper;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Property\CityRepository;
use App\Repository\Property\UnitRepository;
use App\Repository\Rental\LeaseRepository;
use App\Repository\Rental\LeaseOccupantRepository;
use App\Repository\Rental\TenantRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\System\AuditLogService;
use App\Service\System\DateTimeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * LeaseService
 *
 * Package : Rental Management
 *
 * Règle métier centrale : une unité ne peut avoir qu'UN SEUL bail actif à
 * la fois, tout en conservant l'historique complet des baux terminés.
 * Cette contrainte est vérifiée de façon atomique (voir
 * `assertNoCompetingActiveLease()`).
 */
final readonly class LeaseService
{
    public function __construct(
        private LeaseRepository $leaseRepository,
        private LeaseOccupantRepository $leaseOccupantRepository,
        private OrganizationRepository $organizationRepository,
        private TenantRepository $tenantRepository,
        private UnitRepository $unitRepository,
        private CityRepository $cityRepository,
        private LeaseMapper $leaseMapper,
        private SecurityServiceInterface $securityService,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private AuditLogService $auditLogService,
        private DateTimeService $dateTime,
        private RentScheduleCalculator $scheduleCalculator,
    ) {
    }

    /** Calcule un aperçu d’échéancier en mémoire, sans persister le bail ni ses échéances. */
    public function simulateLeaseSchedule(LeaseSimulationRequest $request, User $currentUser): Feedback
    {
        $feedback = new Feedback();
        $violations = $this->validator->validate($request);
        if (count($violations) > 0) {
            return $feedback->bind($violations)->setFlushDescriptionWithError('Les paramètres de simulation sont invalides.')->setStatus(422)->autoInitFlush();
        }

        $payload = $request->lease;
        $violations = $this->validator->validate($payload, null, ['create']);
        if (count($violations) > 0) {
            return $feedback->bind($violations)->setFlushDescriptionWithError('Les données du bail à simuler sont invalides.')->setStatus(422)->autoInitFlush();
        }
        if ($request->throughDate === null || $request->throughDate < $payload->startDate) {
            return $feedback->addError('throughDate', 'La fin de l’aperçu doit être égale ou postérieure au début du bail.')
                ->setFlushDescriptionWithError('La période de simulation est invalide.')->setStatus(422)->autoInitFlush();
        }
        if ($payload->endDate !== null && $payload->endDate < $payload->startDate) {
            return $feedback->addError('endDate', 'La date de fin doit être égale ou postérieure à la date de début.')
                ->setFlushDescriptionWithError('La période contractuelle du bail est invalide.')->setStatus(422)->autoInitFlush();
        }

        $tenant = $this->resolveTenant($payload->tenantUuid, SecurityAction::VIEW_TENANT, $feedback);
        if ($tenant === null) return $feedback->autoInitFlush();
        $unit = $this->resolveUnit($payload->unitUuid, SecurityAction::VIEW_UNIT, $feedback);
        if ($unit === null) return $feedback->autoInitFlush();
        $organization = $tenant->getOrganization();
        if ($unit->getBuilding()->getParcel()->getCity()->getOrganization() !== $organization) {
            return $feedback->addError('unitUuid', 'Le locataire et l’unité doivent appartenir à la même organisation.')
                ->setFlushDescriptionWithError('Le bail ne peut pas réunir des ressources de plusieurs organisations.')
                ->setStatus(422)->autoInitFlush();
        }
        $this->securityService->checkOrganizationAccess($organization, SecurityAction::CREATE_LEASE);

        $lease = (new Lease())->setOrganization($organization)->setTenant($tenant)->setUnit($unit);
        $selectedUnits = [$unit];
        foreach (array_unique($payload->additionalUnitUuids) as $unitUuid) {
            $extra = $this->resolveUnit($unitUuid, SecurityAction::VIEW_UNIT, $feedback);
            if ($extra === null) return $feedback->autoInitFlush();
            if ($extra->getBuilding()->getParcel()->getCity()->getOrganization() !== $organization
                || $extra->getBuilding()->getParcel()->getCity() !== $unit->getBuilding()->getParcel()->getCity()) {
                return $feedback->addError('additionalUnitUuids', 'Les espaces supplémentaires doivent appartenir à la même organisation et à la même ville.')
                    ->setFlushDescriptionWithError('Le périmètre du bail à simuler est incohérent.')
                    ->setStatus(422)->autoInitFlush();
            }
            if ($extra !== $unit) {
                $lease->addAdditionalUnit($extra);
                $selectedUnits[] = $extra;
            }
        }
        foreach ($selectedUnits as $index => $selected) {
            foreach (array_slice($selectedUnits, $index + 1) as $other) {
                if ($this->unitRepository->isDescendantOf($selected, $other) || $this->unitRepository->isDescendantOf($other, $selected)) {
                    return $feedback->addError('additionalUnitUuids', 'Un espace parent et son sous-espace ne peuvent pas être inclus ensemble.')
                        ->setFlushDescriptionWithError('Le périmètre du bail à simuler est incohérent.')
                        ->setStatus(422)->autoInitFlush();
                }
            }
        }
        $this->leaseMapper->copyToEntity($payload, $lease);
        if (!$this->attachOccupants($payload->occupants, $tenant, $lease, $feedback)) {
            return $feedback->autoInitFlush();
        }

        try {
            $candidate = null;
            if ($request->ruleCandidate !== null) {
                $rule = $request->ruleCandidate;
                if ($rule->effectiveFrom === null || $rule->dueMode === null || $rule->dueDay === null
                    || $rule->firstPartialPolicy === null || $rule->lastPartialPolicy === null
                    || $rule->prorationMethod === null || $rule->roundingRule === null) {
                    return $feedback->addError('ruleCandidate', 'Tous les paramètres de la règle candidate sont obligatoires.')
                        ->setFlushDescriptionWithError('La règle candidate est incomplète.')
                        ->setStatus(422)->autoInitFlush();
                }
                if ($rule->effectiveFrom->format('d') !== '01') {
                    return $feedback->addError('ruleCandidate.effectiveFrom', 'La date d’effet candidate doit être le premier jour d’un mois.')
                        ->setFlushDescriptionWithError('La date d’effet candidate est invalide.')
                        ->setStatus(422)->autoInitFlush();
                }
                $candidate = new RentalRuleCandidate(
                    $organization,
                    $rule->effectiveFrom,
                    new RentalRuleSet($rule->dueMode, $rule->dueDay, $rule->firstPartialPolicy, $rule->lastPartialPolicy, $rule->prorationMethod, $rule->roundingRule),
                );
            }
            $installments = $this->scheduleCalculator->calculate($lease, $request->throughDate, $candidate);
        } catch (\DomainException $exception) {
            return $feedback->addError('schedule', $exception->getMessage())
                ->setFlushDescriptionWithError('L’aperçu ne peut pas être calculé avec les règles financières disponibles.')
                ->setStatus(422)->autoInitFlush();
        }

        $rows = array_map(static function (CalculatedRentInstallment $item): array {
            $rules = $item->rules;
            return [
                'periodStart' => $item->period->periodStart->format('Y-m-d'),
                'coveredStart' => $item->period->coveredStart->format('Y-m-d'),
                'coveredEnd' => $item->period->coveredEnd->format('Y-m-d'),
                'amount' => $item->amount,
                'currency' => $item->currency->value,
                'dueDate' => $item->dueDate->format('Y-m-d'),
                'coveredDays' => $item->coveredDays,
                'daysInMonth' => $item->daysInMonth,
                'rules' => [
                    'dueMode' => ['value' => $rules->dueMode->value, 'source' => $rules->dueModeSource->value],
                    'dueDay' => ['value' => $rules->dueDay, 'source' => $rules->dueDaySource->value],
                    'firstPartialPolicy' => ['value' => $rules->firstPartialPolicy->value, 'source' => $rules->firstPartialPolicySource->value],
                    'lastPartialPolicy' => ['value' => $rules->lastPartialPolicy->value, 'source' => $rules->lastPartialPolicySource->value],
                    'prorationMethod' => ['value' => $rules->prorationMethod->value, 'source' => $rules->prorationMethodSource->value],
                    'roundingRule' => ['value' => $rules->roundingRule->value, 'source' => $rules->roundingRuleSource->value],
                    'organizationVersion' => $rules->organizationVersionNumber,
                    'candidateApplied' => $rules->candidateApplied,
                ],
            ];
        }, $installments);

        return $feedback->setData(['calculatorVersion' => RentScheduleCalculator::CALCULATOR_VERSION, 'items' => $rows])
            ->setFlushDescription('Aperçu calculé sans créer de bail, d’échéance ou de paiement.')
            ->setStatus(200)->autoInitFlush();
    }

    public function createLease(LeaseRequest $request, User $currentUser): Feedback
    {
        $feedback = new Feedback();

        $violations = $this->validator->validate($request, null, ['create']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setFlushDescriptionWithError('Les données du bail sont invalides.')
                ->autoInitFlush();
        }

        if ($request->endDate !== null && $request->endDate < $request->startDate) {
            return $feedback
                ->addError('endDate', 'La date de fin doit être égale ou postérieure à la date de début.')
                ->setFlushDescriptionWithError('La période contractuelle du bail est invalide.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $tenant = $this->resolveTenant($request->tenantUuid, SecurityAction::VIEW_TENANT, $feedback);
        if ($tenant === null) {
            return $feedback->autoInitFlush();
        }

        $unit = $this->resolveUnit($request->unitUuid, SecurityAction::VIEW_UNIT, $feedback);
        if ($unit === null) {
            return $feedback->autoInitFlush();
        }

        // Vérifier la cohérence organisationnelle : l'unité et le locataire
        // doivent appartenir à la même Organization. Sans ce contrôle,
        // un utilisateur multi-organisations ou un SUPER_ADMIN pourrait
        // créer un bail croisé entre organisations.
        if ($unit->getBuilding()->getParcel()->getCity()->getOrganization() !== $tenant->getOrganization()) {
            return $feedback
                ->addError('unitUuid', 'L\'unité ne appartient pas à la même organisation que le locataire.')
                ->setFlushDescriptionWithError('L\'unité et le locataire doivent appartenir à la même organisation.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        // L'organization du bail est celle du locataire : on ne la déduit
        // pas du client, elle découle de l'entité déjà autorisée.
        $organization = $tenant->getOrganization();
        $this->securityService->checkOrganizationAccess($organization, SecurityAction::CREATE_LEASE);

        $lease = new Lease();
        $lease->setOrganization($organization);
        $lease->setTenant($tenant);
        $lease->setUnit($unit);
        $selectedUnits = [$unit];
        foreach (array_unique($request->additionalUnitUuids) as $unitUuid) {
            $extra = $this->resolveUnit($unitUuid, SecurityAction::VIEW_UNIT, $feedback);
            if ($extra === null) return $feedback->autoInitFlush();
            if ($extra->getBuilding()->getParcel()->getCity()->getOrganization() !== $organization) {
                return $feedback->addError('additionalUnitUuids', 'Tous les espaces doivent appartenir à la même organisation.')
                    ->setFlushDescriptionWithError('Le bail ne peut pas réunir des espaces de plusieurs organisations.')
                    ->setStatus(422)->autoInitFlush();
            }
            if ($extra->getBuilding()->getParcel()->getCity() !== $unit->getBuilding()->getParcel()->getCity()) {
                return $feedback->addError('additionalUnitUuids', 'Les espaces d’un même bail doivent se trouver dans une seule ville.')
                    ->setFlushDescriptionWithError('Créez un contrat distinct pour les espaces situés dans une autre ville.')
                    ->setStatus(422)->autoInitFlush();
            }
            if ($extra === $unit) continue;
            $lease->addAdditionalUnit($extra);
            $selectedUnits[] = $extra;
        }
        foreach ($selectedUnits as $index => $selected) {
            foreach (array_slice($selectedUnits, $index + 1) as $other) {
                if ($this->unitRepository->isDescendantOf($selected, $other) || $this->unitRepository->isDescendantOf($other, $selected)) {
                    return $feedback->addError('additionalUnitUuids', 'Un espace parent et son sous-espace ne peuvent pas être inclus ensemble dans le même bail.')
                        ->setFlushDescriptionWithError('Choisissez soit l’espace complet, soit ses subdivisions louées séparément.')
                        ->setStatus(422)->autoInitFlush();
                }
            }
        }
        $this->leaseMapper->copyToEntity($request, $lease);
        $entityViolations = $this->validator->validate($lease);
        if (count($entityViolations) > 0) {
            return $feedback->bind($entityViolations)->setFlushDescriptionWithError('Les règles particulières du bail sont invalides.')->setStatus(422)->autoInitFlush();
        }
        if (!$this->attachOccupants($request->occupants, $tenant, $lease, $feedback)) return $feedback->autoInitFlush();

        if (!$this->persistLeaseExclusively($lease, null, $feedback, $selectedUnits)) {
            return $feedback->autoInitFlush();
        }

        // Log d'audit : création du bail
        $this->auditLogService->log(
            action: 'CREATE_LEASE',
            entityType: Lease::class,
            entityId: $lease->getId(),
            organization: $tenant->getOrganization(),
            user: $currentUser,
            oldValues: null,
            newValues: [
                'reference' => $lease->getReference(),
                'startDate' => $this->dateTime->format($lease->getStartDate(), 'Y-m-d'),
                'endDate' => $lease->getEndDate()?->format('Y-m-d'),
                'monthlyRent' => $lease->getMonthlyRent(),
                'currency' => $lease->getCurrency()->value,
                'tenantUuid' => $tenant->getUuid()->toRfc4122(),
                'unitUuid' => $lease->getUnit()->getUuid()->toRfc4122(),
            ],
        );

        return $feedback
            ->setData($this->leaseMapper->toResponse($lease))
            ->setFlushDescription('Le contrat de bail a été créé avec succès.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    public function updateLease(string $uuid, LeaseRequest $request, User $currentUser): Feedback
    {
        $feedback = new Feedback();

        $lease = $this->findLease($uuid, $feedback);
        if ($lease === null) {
            return $feedback->autoInitFlush();
        }

        $violations = $this->validator->validate($request, null, ['update']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setFlushDescriptionWithError('Les données de mise à jour du bail sont invalides.')
                ->autoInitFlush();
        }

        $this->securityService->checkLeaseAccess($lease, SecurityAction::UPDATE_LEASE);
        if ($request->additionalUnitUuids !== [] || $request->occupants !== []) {
            return $feedback->addError('unitIds', 'Le périmètre et les occupants d’un bail créé sont conservés dans son historique.')
                ->setFlushDescriptionWithError('Utilisez l’opération d’ajout d’occupant ou un nouveau bail pour préserver la traçabilité.')
                ->autoInitFlush();
        }

        $effectiveStartDate = $request->startDate ?? $lease->getStartDate();
        $effectiveEndDate = $request->endDate ?? $lease->getEndDate();
        if ($effectiveEndDate !== null && $effectiveEndDate < $effectiveStartDate) {
            return $feedback
                ->addError('endDate', 'La date de fin doit être égale ou postérieure à la date de début.')
                ->setFlushDescriptionWithError('La période contractuelle du bail est invalide.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        // Un changement de locataire ou d'unité est traité comme un
        // changement de périmètre : l'entité cible doit être accessible,
        // sinon un bail d'une organization A pourrait être rattaché à un
        // locataire d'une organization B.
        $tenant = $lease->getTenant();
        if ($request->tenantUuid !== null) {
            $resolved = $this->resolveTenant($request->tenantUuid, SecurityAction::VIEW_TENANT, $feedback);
            if ($resolved === null) {
                return $feedback->autoInitFlush();
            }
            $tenant = $resolved;
        }

        $unit = $lease->getUnit();
        if ($request->unitUuid !== null) {
            $resolved = $this->resolveUnit($request->unitUuid, SecurityAction::VIEW_UNIT, $feedback);
            if ($resolved === null) {
                return $feedback->autoInitFlush();
            }
            $unit = $resolved;
        }

        if ($tenant !== $lease->getTenant() || $unit !== $lease->getUnit()) {
            $feedback->addError(
                'unitUuid',
                'La modification du locataire ou de l\'unité d\'un bail existant n\'est pas autorisée.'
            );

            return $feedback
                ->setFlushDescriptionWithError('Rattachement du bail non modifiable.')
                ->autoInitFlush();
        }

        $oldValues = $this->leaseAuditValues($lease);
        $this->leaseMapper->copyToEntity($request, $lease);
        $entityViolations = $this->validator->validate($lease);
        if (count($entityViolations) > 0) {
            return $feedback->bind($entityViolations)->setFlushDescriptionWithError('Les règles particulières du bail sont invalides.')->setStatus(422)->autoInitFlush();
        }

        if (!$this->persistLeaseExclusively($lease, $lease, $feedback)) {
            return $feedback->autoInitFlush();
        }

        // Log d'audit : mise à jour du bail
        $this->auditLogService->log(
            action: 'UPDATE_LEASE',
            entityType: Lease::class,
            entityId: $lease->getId(),
            organization: $lease->getOrganization(),
            user: $currentUser,
            oldValues: $oldValues,
            newValues: $this->leaseAuditValues($lease),
        );

        return $feedback
            ->setData($this->leaseMapper->toResponse($lease))
            ->setFlushDescription('Le contrat de bail a été mis à jour avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function getLeaseByUuid(string $uuid): Feedback
    {
        $feedback = new Feedback();

        $lease = $this->findLease($uuid, $feedback);
        if ($lease === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkLeaseAccess($lease, SecurityAction::VIEW_LEASE);

        return $feedback
            ->setData($this->leaseMapper->toResponse($lease))
            ->setFlushDescription('Contrat de bail récupéré.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function addOccupant(string $uuid, LeaseOccupantRequest $request, User $currentUser): Feedback
    {
        $feedback = new Feedback();
        $lease = $this->findLease($uuid, $feedback);
        if ($lease === null) return $feedback->autoInitFlush();

        $this->securityService->checkLeaseAccess($lease, SecurityAction::UPDATE_LEASE);
        if (!in_array($lease->getStatus(), [LeaseStatus::DRAFT, LeaseStatus::ACTIVE], true)) {
            return $feedback->addError('status', 'Un bail clôturé ne peut plus recevoir de nouveaux occupants.')
                ->setFlushDescriptionWithError('Enregistrez un nouveau bail pour tracer une nouvelle occupation.')
                ->autoInitFlush();
        }
        $violations = $this->validator->validate($request);
        if (count($violations) > 0) {
            return $feedback->bind($violations)->setFlushDescriptionWithError('Les données de l’occupant sont invalides.')->autoInitFlush();
        }

        if (!$this->attachOccupants([[
            'tenantUuid' => $request->tenantUuid,
            'role' => $request->role,
            'startDate' => $request->startDate,
            'endDate' => $request->endDate,
        ]], $lease->getTenant(), $lease, $feedback)) {
            return $feedback->setFlushDescriptionWithError('L’occupant n’a pas pu être rattaché à ce bail.')->autoInitFlush();
        }

        $this->entityManager->flush();
        $this->auditLogService->log(
            action: 'ADD_LEASE_OCCUPANT',
            entityType: Lease::class,
            entityId: $lease->getId(),
            organization: $lease->getOrganization(),
            user: $currentUser,
            oldValues: null,
            newValues: ['tenantUuid' => $request->tenantUuid, 'role' => $request->role, 'startDate' => $request->startDate, 'endDate' => $request->endDate],
        );

        return $feedback->setData($this->leaseMapper->toResponse($lease))
            ->setFlushDescription('L’occupant a été ajouté à l’historique du bail.')
            ->autoInitFlush()->setStatus(201);
    }

    public function recordOccupantDeparture(string $leaseUuid, string $occupantUuid, LeaseOccupantDepartureRequest $request, User $currentUser): Feedback
    {
        $feedback = new Feedback();
        $lease = $this->findLease($leaseUuid, $feedback);
        if ($lease === null) return $feedback->autoInitFlush();

        $this->securityService->checkLeaseAccess($lease, SecurityAction::UPDATE_LEASE);
        if ($lease->getStatus() !== LeaseStatus::ACTIVE) {
            return $feedback->addError('lease', 'Le départ individuel ne peut être enregistré que sur un bail actif.')
                ->setFlushDescriptionWithError('Le bail sélectionné n’est pas actif.')
                ->autoInitFlush();
        }
        $violations = $this->validator->validate($request);
        if (count($violations) > 0) {
            return $feedback->bind($violations)->setFlushDescriptionWithError('Les données de sortie sont invalides.')->autoInitFlush();
        }

        try {
            $occupant = $this->leaseOccupantRepository->findForLease(Uuid::fromString($occupantUuid), $lease);
        } catch (\InvalidArgumentException) {
            $occupant = null;
        }
        if ($occupant === null) {
            return $feedback->setErrorFlushDescription('La personne ne fait pas partie du bail sélectionné.')->autoInitFlush()->setStatus(404);
        }

        $departureDate = $this->dateTime->parseDate($request->effectiveDate);
        if ($departureDate === null || $departureDate < $occupant->getStartDate() || $departureDate > $this->dateTime->today()) {
            return $feedback->addError('effectiveDate', 'La date doit être comprise entre le début de présence et aujourd’hui.')
                ->setFlushDescriptionWithError('La date de sortie est invalide.')
                ->autoInitFlush();
        }
        if ($occupant->getEndDate() !== null) {
            return $feedback->addError('occupant', 'La sortie de cette personne est déjà enregistrée.')
                ->setFlushDescriptionWithError('Cette présence est déjà clôturée.')
                ->autoInitFlush()->setStatus(409);
        }

        $this->entityManager->wrapInTransaction(function () use ($occupant, $departureDate, $request, $lease, $currentUser): void {
            $occupant->setEndDate($departureDate);
            $this->entityManager->flush();
            $this->auditLogService->log(
                action: 'END_LEASE_OCCUPANT_PRESENCE',
                entityType: LeaseOccupant::class,
                entityId: (int) $occupant->getId(),
                organization: $lease->getOrganization(),
                user: $currentUser,
                oldValues: ['endDate' => null],
                newValues: [
                    'leaseUuid' => $lease->getUuid()->toRfc4122(),
                    'tenantUuid' => $occupant->getTenant()->getUuid()->toRfc4122(),
                    'effectiveDate' => $this->dateTime->format($departureDate, 'Y-m-d'),
                    'reason' => $request->reason,
                ],
            );
        });

        return $feedback->setData([
            'occupantUuid' => $occupant->getUuid()->toRfc4122(),
            'tenantUuid' => $occupant->getTenant()->getUuid()->toRfc4122(),
            'endDate' => $this->dateTime->format($departureDate, 'Y-m-d'),
        ])->setFlushDescription('La sortie de l’occupant est enregistrée. Le bail et les autres occupants restent actifs.')
            ->setStatus(200)->autoInitFlush();
    }

    /**
     * Terminaison d'un bail : le statut passe à TERMINATED et la date de
     * rupture est mémorisée, ce qui libère l'unité pour un nouveau bail.
     */
    public function terminateLease(string $uuid, string $reason, User $currentUser, ?string $effectiveDate = null): Feedback
    {
        $feedback = new Feedback();

        $lease = $this->findLease($uuid, $feedback);
        if ($lease === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkLeaseAccess($lease, SecurityAction::TERMINATE_LEASE);

        if ($lease->getStatus() !== LeaseStatus::ACTIVE) {
            $feedback->addError('status', 'Seul un bail actif peut être résilié.');

            return $feedback
                ->setFlushDescriptionWithError('Résiliation impossible : le bail n\'est pas actif.')
                ->autoInitFlush();
        }

        $terminationDate = $effectiveDate === null ? $this->dateTime->today() : $this->dateTime->parseDate($effectiveDate);
        if ($terminationDate === null || $terminationDate > $this->dateTime->today() || $terminationDate < $lease->getStartDate()) {
            return $feedback
                ->addError('effectiveDate', 'La date de résiliation doit être valide, comprise entre le début du bail et aujourd’hui.')
                ->setFlushDescriptionWithError('La date effective de résiliation est invalide.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $lease->setStatus(LeaseStatus::TERMINATED);
        $lease->setTerminationDate($terminationDate);
        $lease->setTerminationReason($reason !== '' ? $reason : null);

        $this->entityManager->flush();

        // Log d'audit : résiliation du bail
        $this->auditLogService->log(
            action: 'TERMINATE_LEASE',
            entityType: Lease::class,
            entityId: $lease->getId(),
            organization: $lease->getOrganization(),
            user: $currentUser,
            oldValues: [
                'status' => 'ACTIVE',
            ],
            newValues: [
                'status' => 'TERMINATED',
                'terminationDate' => $this->dateTime->format($lease->getTerminationDate(), 'Y-m-d'),
                'terminationReason' => $lease->getTerminationReason(),
            ],
        );

        return $feedback
            ->setData($this->leaseMapper->toResponse($lease))
            ->setFlushDescription('Le bail a été résilié.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Liste paginée des baux du périmètre autorisé.
     */
    public function listLeases(?LeaseFilterDto $filter = null): Feedback
    {
        $feedback = new Feedback();

        $filter ??= new \App\Dto\Request\Rental\LeaseFilterDto();

        // Récupérer les organisations et villes accessibles
        $organizations = $this->securityService->getCurrentUserOrganizations();
        $organizationIds = array_map(fn($o) => $o->getId(), $organizations);

        $cityIds = [];
        foreach ($organizations as $org) {
            $cities = $this->cityRepository->findActiveByOrganization($org);
            foreach ($cities as $city) {
                if ($this->securityService->canAccessCity($city, SecurityAction::VIEW_LEASE)) {
                    $cityIds[] = $city->getId();
                }
            }
        }

        // Si l'utilisateur est ADMIN_VILLE, il a déjà des cityIds restreints
        // Sinon, on utilise toutes les villes de ses organizations
        if ($cityIds === []) {
            // Pas de ville accessible = pas de bail visible
            return $feedback
                ->setData(['items' => [], 'total' => 0, 'page' => 1, 'limit' => 20])
                ->setFlushDescription('Aucune ville accessible.')
                ->setStatus(200)
                ->autoInitFlush();
        }

        // Filtrage optionnel par organizationId si fourni
        $targetOrgIds = $organizationIds;
        if ($filter->organizationId !== null) {
            try {
                $uuid = \Symfony\Component\Uid\Uuid::fromString($filter->organizationId);
                $org = $this->organizationRepository->findOneByUuid($uuid);
                if ($org !== null && in_array($org->getId(), $organizationIds, true)) {
                    $targetOrgIds = [$org->getId()];
                } else {
                    $targetOrgIds = []; // Organisation non accessible
                }
            } catch (\InvalidArgumentException) {
                $targetOrgIds = [];
            }
        }

        // Filtrage optionnel par unitId, tenantId, status
        // Note: ces filtres ne sont pas encore supportés par le repository,
        // ils sont appliqués en post-traitement pour l'instant.
        // TODO: les ajouter dans le repository pour éviter le sur-fetching.

        // Un tableau d'identifiants vide doit signifier « aucun résultat », et non
        // « pas de filtre » : les repositories traitent `null` comme l'absence de
        // filtre, mais un `[]` arrivait jusqu'à eux et était ignoré via !empty(),
        // ce qui renvoyait les lignes des organisations du périmètre de l'appelant
        // au lieu d'une liste vide. Sans ce retour early, un `organizationId`
        // hors périmètre se comportait comme si le filtre n'avait pas été fourni.
        if ($targetOrgIds === []) {
            return $feedback
                ->setData(['items' => [], 'total' => 0, 'page' => max(1, $filter->page), 'limit' => $filter->limit])
                ->setFlushDescription('Aucune organisation accessible pour ce filtre.')
                ->setStatus(200)
                ->autoInitFlush();
        }

        $result = $this->leaseRepository->findPaginatedByOrganizationsAndCities(
            organizationIds: $targetOrgIds,
            cityIds: $cityIds,
            page: $filter->page,
            limit: $filter->limit,
            sortBy: $filter->sortBy,
            sortOrder: $filter->sortOrder
        );

        // Appliquer les filtres post-répertoire (unitId, tenantId, status)
        $items = array_filter($result['items'], function (Lease $lease) use ($filter): bool {
            if ($filter->unitId !== null) {
                try {
                    if ($lease->getUnit()->getUuid()->toRfc4122() !== $filter->unitId) {
                        return false;
                    }
                } catch (\Throwable) {
                    return false;
                }
            }
            if ($filter->tenantId !== null) {
                try {
                    if ($lease->getTenant()->getUuid()->toRfc4122() !== $filter->tenantId) {
                        return false;
                    }
                } catch (\Throwable) {
                    return false;
                }
            }
            if ($filter->status !== null) {
                if ($lease->getStatus()->value !== $filter->status) {
                    return false;
                }
            }
            // Vérification d'accès finale
            return $this->securityService->canAccessLease($lease, SecurityAction::VIEW_LEASE);
        });

        $items = array_map(fn(Lease $l) => $this->leaseMapper->toResponse($l), $items);

        return $feedback
            ->setData([
                'items' => array_values($items),
                'total' => count($items), // Note: total après filtres post-répertoire
                'page' => max(1, $filter->page),
                'limit' => $filter->limit,
            ])
            ->setFlushDescription('Baux listés avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Vérifie qu'aucun bail actif ne porte sur cette unité, ses parents ou
     * ses sous-espaces, puis enregistre le bail.
     *
     * Le contrôle, le verrouillage de la racine commune et l'écriture sont
     * atomiques. Des espaces frères partagent le verrou pour éviter une
     * course, mais ne sont pas considérés incompatibles par la règle métier.
     *
     * Le verrou doit être maintenu jusqu'au commit, c'est pourquoi il ne
     * peut pas être_factorisé dans une méthode appelée séparément.
     *
     * `$exclude` est le bail en cours de mise à jour, à ignorer : sans
     * cela un bail déjà actif se détecterait lui-même comme conflit et
     * toute modification de son montant serait refusée.
     *
     * @return bool `false` si un conflit a été signalé dans $feedback
     */
    private function persistLeaseExclusively(Lease $lease, ?Lease $exclude, Feedback $feedback, array $selectedUnits = [], bool $checkOperationalAvailability = false): bool
    {
        $selectedUnits = $selectedUnits ?: [$lease->getUnit(), ...$lease->getAdditionalUnits()->toArray()];
        $hasUnavailableSpace = false;
        $succeeded = $this->entityManager->wrapInTransaction(
            function () use ($lease, $exclude, $selectedUnits, $checkOperationalAvailability, &$hasUnavailableSpace): bool {
                $overlappingSpaces = [];
                $roots = [];
                foreach ($selectedUnits as $unit) {
                    $root = $this->unitRepository->findSpaceRoot($unit);
                    $roots[$root->getId()] = $root;
                    foreach ($this->unitRepository->findOverlappingSpaces($unit) as $overlap) {
                        $overlappingSpaces[$overlap->getId()] = $overlap;
                    }
                }
                ksort($roots);
                foreach ($roots as $root) $this->unitRepository->lockForUpdate($root);

                if ($checkOperationalAvailability) {
                    foreach ($overlappingSpaces as $space) {
                        if ($space->getOperationalStatus() !== \App\Enum\UnitOperationalStatus::AVAILABLE) {
                            $hasUnavailableSpace = true;
                            break;
                        }
                    }
                }

                if ($lease->getStatus() === LeaseStatus::ACTIVE && $hasUnavailableSpace) {
                    if ($checkOperationalAvailability) {
                        $lease->setStatus(LeaseStatus::DRAFT);
                    }
                    return false;
                }

                if ($lease->getStatus() === LeaseStatus::ACTIVE && $this->leaseRepository->findActiveLeaseOverlappingPeriodForRelatedSpaces(
                    array_values($overlappingSpaces),
                    $lease->getStartDate(),
                    $lease->getEndDate(),
                    $exclude?->getUuid(),
                ) !== null) {
                    if ($checkOperationalAvailability) {
                        $lease->setStatus(LeaseStatus::DRAFT);
                    }
                    return false;
                }

                $this->entityManager->persist($lease);
                $this->entityManager->flush();

                return true;
            }
        );

        if (!$succeeded) {
            $feedback
                ->addError('unitUuid', $hasUnavailableSpace
                    ? 'Un espace du contrat est réservé ou indisponible.'
                    : 'Cet espace ou un espace parent/enfant est déjà occupé par un bail actif.')
                ->setFlushDescriptionWithError($hasUnavailableSpace
                    ? 'Un bail actif ne peut pas porter sur un espace actuellement indisponible.'
                    : 'Des espaces imbriqués ne peuvent pas être loués simultanément lorsque leurs usages se chevauchent.')
                ->setStatus(409);
        }

        return $succeeded;
    }

    /** Valide les occupants avant l'écriture; leur rôle financier reste celui du contrat. */
    private function attachOccupants(array $occupants, Tenant $primaryTenant, Lease $lease, Feedback $feedback): bool
    {
        $seen = [(string) $primaryTenant->getUuid() => true];
        foreach ($occupants as $index => $item) {
            if (!is_array($item) || !is_string($item['tenantUuid'] ?? null) || !in_array($item['role'] ?? null, ['co_tenant', 'occupant'], true)) {
                $feedback->addError('occupants', sprintf('La personne %d doit avoir un UUID et un rôle valide.', $index + 1));
                continue;
            }
            $occupantTenant = $this->resolveTenant($item['tenantUuid'], SecurityAction::VIEW_TENANT, $feedback);
            if ($occupantTenant === null) continue;
            $uuid = (string) $occupantTenant->getUuid();
            if (isset($seen[$uuid]) || $occupantTenant->getOrganization() !== $primaryTenant->getOrganization()) {
                $feedback->addError('occupants', 'Une personne est répétée ou appartient à une autre organisation.');
                continue;
            }
            $start = $this->dateTime->parseDate((string) ($item['startDate'] ?? $lease->getStartDate()->format('Y-m-d')));
            $end = isset($item['endDate']) && $item['endDate'] !== '' ? $this->dateTime->parseDate((string) $item['endDate']) : null;
            $contractEnd = $lease->getTerminationDate() ?? $lease->getEndDate();
            if ($start === null || (isset($item['endDate']) && $item['endDate'] !== '' && $end === null)
                || ($end !== null && $end < $start)
                || ($start !== null && $start < $lease->getStartDate())
                || ($contractEnd !== null && $start !== null && $start > $contractEnd)
                || ($contractEnd !== null && $end !== null && $end > $contractEnd)) {
                $feedback->addError('occupants', sprintf('Les dates de la personne %d sont invalides.', $index + 1));
                continue;
            }
            if ($this->leaseOccupantRepository->hasOverlappingPresence($occupantTenant, $lease, $start, $end)) {
                $feedback->addError('occupants', sprintf('La période de présence de la personne %d chevauche une présence déjà enregistrée dans ce bail.', $index + 1));
                continue;
            }
            $entry = new \App\Entity\Rental\LeaseOccupant();
            $entry->setOrganization($lease->getOrganization())->setTenant($occupantTenant)->setRole($item['role'])->setStartDate($start)->setEndDate($end);
            $lease->addOccupant($entry);
            $seen[$uuid] = true;
        }

        return count($feedback->getErrors()) === 0;
    }

    /** @return array<string, scalar|null> */
    private function leaseAuditValues(Lease $lease): array
    {
        return [
            'reference' => $lease->getReference(),
            'startDate' => $this->dateTime->format($lease->getStartDate(), 'Y-m-d'),
            'endDate' => $lease->getEndDate() === null ? null : $this->dateTime->format($lease->getEndDate(), 'Y-m-d'),
            'monthlyRent' => $lease->getMonthlyRent(),
            'depositAmount' => $lease->getDepositAmount(),
            'currency' => $lease->getCurrency()->value,
            'notes' => $lease->getNotes(),
            'status' => $lease->getStatus()->value,
        ];
    }

    /**
     * Active un bail. Transition DRAFT -> ACTIVE.
     *
     * Elle est nécessaire parce que le statut n'est plus saisissable dans
     * `LeaseRequest` : sans endpoint dédié, un bail resterait DRAFT en
     * permanence et ne produirait ni échéance ni impayé.
     *
     * L'exclusivité est vérifiée comme à la création : l'unité ne peut
     * porter qu'un bail actif, et la vérification doit rester atomique.
     */
    public function activateLease(string $uuid, User $currentUser): Feedback
    {
        $feedback = new Feedback();

        $lease = $this->findLease($uuid, $feedback);
        if ($lease === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkLeaseAccess($lease, SecurityAction::ACTIVATE_LEASE);

        if ($lease->getStatus() !== LeaseStatus::DRAFT) {
            // `autoInitFlush()` réimpose 422 dès qu'il y a une erreur :
            // le 409 doit être posé APRÈS, sinon il est perdu.
            return $feedback
                ->addError('status', 'Seul un bail à l\'état brouillon peut être activé.')
                ->setFlushDescriptionWithError(sprintf(
                    'Activation impossible : le bail est à l\'état %s.',
                    $lease->getStatus()->value
                ))
                ->autoInitFlush()
                ->setStatus(409);
        }

        if ($lease->getEndDate() !== null && $lease->getEndDate() < $this->dateTime->today()) {
            return $feedback
                ->addError('endDate', 'Un bail dont la période est déjà terminée ne peut pas être activé.')
                ->setFlushDescriptionWithError('Activation impossible : la date de fin du bail est passée.')
                ->autoInitFlush()
                ->setStatus(409);
        }

        $lease->setStatus(LeaseStatus::ACTIVE);

        if (!$this->persistLeaseExclusively($lease, $lease, $feedback, checkOperationalAvailability: true)) {
            $lease->setStatus(LeaseStatus::DRAFT);
            return $feedback->autoInitFlush()->setStatus(409);
        }

        // Log d'audit : activation du bail
        $this->auditLogService->log(
            action: 'ACTIVATE_LEASE',
            entityType: Lease::class,
            entityId: $lease->getId(),
            organization: $lease->getOrganization(),
            user: $currentUser,
            oldValues: [
                'status' => 'DRAFT',
            ],
            newValues: [
                'status' => 'ACTIVE',
            ],
        );

        return $feedback
            ->setData($this->leaseMapper->toResponse($lease))
            ->setFlushDescription('Le bail a été activé.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Annule un bail. Transition DRAFT -> CANCELLED.
     *
     * Réservé à un bail jamais activé : un bail qui a tourné ne
     * s'annule pas, il se résilie, ce qui laisse une trace et libère
     * l'unité dans le même geste.
     */
    public function cancelLease(string $uuid, string $reason, User $currentUser): Feedback
    {
        $feedback = new Feedback();

        $lease = $this->findLease($uuid, $feedback);
        if ($lease === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkLeaseAccess($lease, SecurityAction::CANCEL_LEASE);

        if ($lease->getStatus() !== LeaseStatus::DRAFT) {
            return $feedback
                ->addError('status', 'Seul un bail à l\'état brouillon peut être annulé.')
                ->setFlushDescriptionWithError(sprintf(
                    'Annulation impossible : le bail est à l\'état %s. Utilisez la résiliation.',
                    $lease->getStatus()->value
                ))
                ->autoInitFlush()
                ->setStatus(409);
        }

        $lease->setStatus(LeaseStatus::CANCELLED);
        $lease->setTerminationDate($this->dateTime->now());
        $lease->setTerminationReason($reason !== '' ? $reason : null);

        $this->entityManager->flush();

        // Log d'audit : annulation du bail
        $this->auditLogService->log(
            action: 'CANCEL_LEASE',
            entityType: Lease::class,
            entityId: $lease->getId(),
            organization: $lease->getOrganization(),
            user: $currentUser,
            oldValues: [
                'status' => 'DRAFT',
            ],
            newValues: [
                'status' => 'CANCELLED',
                'terminationDate' => $this->dateTime->format($lease->getTerminationDate(), 'Y-m-d'),
                'terminationReason' => $lease->getTerminationReason(),
            ],
        );

        return $feedback
            ->setData($this->leaseMapper->toResponse($lease))
            ->setFlushDescription('Le bail a été annulé.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    private function resolveTenant(?string $uuid, SecurityAction $action, Feedback $feedback): ?Tenant
    {
        if ($uuid === null || $uuid === '') {
            $feedback->addError('tenantUuid', 'Le locataire est obligatoire.');

            return null;
        }

        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            $feedback->addError('tenantUuid', 'Identifiant de locataire invalide.');

            return null;
        }

        $tenant = $this->tenantRepository->findOneByUuid($parsed);

        if ($tenant === null) {
            $feedback
                ->setErrorFlushDescription('Locataire introuvable.')
                ->setStatus(404);

            return null;
        }

        $this->securityService->checkTenantAccess($tenant, $action);

        return $tenant;
    }

    private function resolveUnit(?string $uuid, SecurityAction $action, Feedback $feedback): ?Unit
    {
        if ($uuid === null || $uuid === '') {
            $feedback->addError('unitUuid', 'L\'unité locative est obligatoire.');

            return null;
        }

        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            $feedback->addError('unitUuid', 'Identifiant d\'unité invalide.');

            return null;
        }

        $unit = $this->unitRepository->findOneByUuid($parsed);

        if ($unit === null) {
            $feedback
                ->setErrorFlushDescription('Unité locative introuvable.')
                ->setStatus(404);

            return null;
        }

        $this->securityService->checkUnitAccess($unit, $action);

        return $unit;
    }

    private function findLease(string $uuid, Feedback $feedback): ?Lease
    {
        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            $feedback
                ->setErrorFlushDescription('Identifiant de bail invalide.')
                ->setStatus(400);

            return null;
        }

        $lease = $this->leaseRepository->findOneByUuid($parsed);

        if ($lease === null) {
            $feedback
                ->setErrorFlushDescription('Contrat de bail introuvable.')
                ->setStatus(404);
        }

        return $lease;
    }
}
