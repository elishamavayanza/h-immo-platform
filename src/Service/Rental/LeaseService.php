<?php

declare(strict_types=1);

namespace App\Service\Rental;

use App\Dto\Feedback;
use App\Dto\Request\Rental\LeaseFilterDto;
use App\Dto\Request\Rental\LeaseRequest;
use App\Entity\Identity\User;
use App\Entity\Property\Unit;
use App\Entity\Rental\Lease;
use App\Entity\Rental\Tenant;
use App\Enum\LeaseStatus;
use App\Mapper\Rental\LeaseMapper;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Property\CityRepository;
use App\Repository\Property\UnitRepository;
use App\Repository\Rental\LeaseRepository;
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
    ) {
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
        $this->leaseMapper->copyToEntity($request, $lease);

        if (!$this->persistLeaseExclusively($lease, null, $feedback)) {
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

        // Le conflit ne concerne que le passage au statut ACTIVE (ou son
        // maintien) : les mises à jour de montants sur un bail déjà actif
        // doivent rester possibles, d'où l'exclusion de ce bail.
        $this->leaseMapper->copyToEntity($request, $lease);

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
            oldValues: null, // Pourrait être enrichi avec les valeurs avant modification
            newValues: [
                'reference' => $lease->getReference(),
                'startDate' => $this->dateTime->format($lease->getStartDate(), 'Y-m-d'),
                'endDate' => $lease->getEndDate()?->format('Y-m-d'),
                'monthlyRent' => $lease->getMonthlyRent(),
                'currency' => $lease->getCurrency()->value,
            ],
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

    /**
     * Terminaison d'un bail : le statut passe à TERMINATED et la date de
     * rupture est mémorisée, ce qui libère l'unité pour un nouveau bail.
     */
    public function terminateLease(string $uuid, string $reason, User $currentUser): Feedback
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

        $lease->setStatus(LeaseStatus::TERMINATED);
        $lease->setTerminationDate($this->dateTime->now());
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
     * Vérifie qu'aucun autre bail ACTIVE n'occupe déjà l'unité, puis
     * enregistre le bail.
     *
     * Contrôle, verrou et écriture ont lieu dans UNE SEULE transaction.
     * C'est indispensable : un simple `SELECT` avant l'INSERT souffre
     * d'une fenêtre d'interleave — deux requêtes concurrentes peuvent
     * toutes deux ne voir aucun bail actif et en créer chacune un. Le
     * verrou pessimiste `SELECT ... FOR UPDATE` sur la ligne unité
     * sérialise ces transactions concurrentes, le second appelant
     * attendant que le premier ait commité avant de relire.
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
    private function persistLeaseExclusively(Lease $lease, ?Lease $exclude, Feedback $feedback): bool
    {
        $succeeded = $this->entityManager->wrapInTransaction(
            function () use ($lease, $exclude): bool {
                $this->unitRepository->lockForUpdate($lease->getUnit());

                if (
                    $lease->getStatus() === LeaseStatus::ACTIVE
                    && $this->leaseRepository->findActiveLeaseForUnit(
                        $lease->getUnit(),
                        $exclude?->getUuid()
                    ) !== null
                ) {
                    return false;
                }

                $this->entityManager->persist($lease);
                $this->entityManager->flush();

                return true;
            }
        );

        if (!$succeeded) {
            $feedback
                ->addError('unitUuid', 'Cette unité est déjà occupée par un bail actif.')
                ->setFlushDescriptionWithError(
                    'Une unité ne peut avoir qu\'un seul bail actif à la fois.'
                )
                ->setStatus(409);
        }

        return $succeeded;
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

        $lease->setStatus(LeaseStatus::ACTIVE);

        if (!$this->persistLeaseExclusively($lease, $lease, $feedback)) {
            return $feedback->autoInitFlush();
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
