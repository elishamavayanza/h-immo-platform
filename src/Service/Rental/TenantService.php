<?php

declare(strict_types=1);

namespace App\Service\Rental;

use App\Dto\Feedback;
use App\Dto\Request\Rental\TenantFilterDto;
use App\Dto\Request\Rental\TenantRequest;
use App\Entity\Identity\Organization;
use App\Dto\Response\Rental\TenantResponse;
use App\Entity\Property\City;
use App\Entity\Identity\User;
use App\Entity\Rental\Tenant;
use App\Enum\TenantType;
use App\Enum\OrganizationRole;
use App\Mapper\Rental\TenantMapper;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Property\CityRepository;
use App\Repository\Rental\TenantRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\System\AuditLogService;
use App\Service\System\DateTimeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * TenantService
 *
 * Package : Rental Management
 *
 * Portée : un locataire appartient à une Organization et n'existe que
 * dans le périmètre de celle-ci. Aucune méthode ne résout le tenant
 * depuis « l'organisation de l'utilisateur » : un compte peut être
 * membre de plusieurs organizations, le tenant cible est donc désigné
 * par `TenantRequest::$organizationUuid` puis validé.
 */
final readonly class TenantService
{
    public function __construct(
        private TenantRepository $tenantRepository,
        private OrganizationRepository $organizationRepository,
        private CityRepository $cityRepository,
        private TenantMapper $tenantMapper,
        private SecurityServiceInterface $securityService,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private AuditLogService $auditLogService,
        private DateTimeService $dateTime
    ) {
    }

    /**
     * Résout l'Organization cible et vérifie que l'appelant peut y créer
     * un locataire. Centralise la résolution + autorisation pour éviter de
     * dupliquer le couple dans chaque méthode.
     */
    private function resolveWritableOrganization(
        TenantRequest $request,
        Feedback $feedback
    ): ?Organization {
        $organizationUuid = $request->organizationUuid;

        if ($organizationUuid === null || $organizationUuid === '') {
            $feedback->addError('organizationUuid', 'L\'organisation est obligatoire.');

            return null;
        }

        try {
            $uuid = Uuid::fromString($organizationUuid);
        } catch (\InvalidArgumentException) {
            $feedback->addError('organizationUuid', 'L\'identifiant de l\'organisation est invalide.');

            return null;
        }

        $organization = $this->organizationRepository->findOneByUuid($uuid);

        if ($organization === null) {
            $feedback->addError('organizationUuid', 'Organisation introuvable.');

            return null;
        }

        // Lève une AccessDeniedException si l'appelant n'a pas le rôle
        // requis dans CETTE organisation (et pas seulement « dans une
        // organisation où il est membre »).
        $this->securityService->checkOrganizationAccess($organization, SecurityAction::CREATE_TENANT);

        return $organization;
    }

    private function resolveTenantCity(?string $cityUuid, Organization $organization, Feedback $feedback, bool $required): ?City
    {
        $role = $this->securityService->getOrganizationRole($this->securityService->getCurrentUser(), $organization);
        $required = $required || $role === OrganizationRole::ADMIN_VILLE;
        if (($cityUuid === null || $cityUuid === '') && $required) {
            $feedback->addError('cityUuid', 'La ville active est requise pour rattacher ce locataire.');

            return null;
        }
        if ($cityUuid === null || $cityUuid === '') {
            return null;
        }

        try {
            $city = $this->cityRepository->findOneByUuid(Uuid::fromString($cityUuid));
        } catch (\InvalidArgumentException) {
            $city = null;
        }
        if ($city === null || $city->getOrganization() !== $organization) {
            $feedback->addError('cityUuid', 'La ville doit appartenir à l’organisation du locataire.');

            return null;
        }

        $this->securityService->checkCityAccess($city, SecurityAction::CREATE_TENANT);

        return $city;
    }

    /**
     * Contraintes de saisie propres au type de locataire.
     */
    private function validateTenantType(TenantRequest $request, Feedback $feedback): bool
    {
        $type = $request->type ?? TenantType::INDIVIDUAL;

        if ($type === TenantType::INDIVIDUAL && ($request->firstName === null || $request->lastName === null)) {
            $feedback->addError('firstName', 'Le prénom et le nom sont requis pour une personne physique.');

            return false;
        }

        if ($type === TenantType::COMPANY && ($request->companyName === null || $request->companyName === '')) {
            $feedback->addError('companyName', 'La raison sociale est requise pour une entreprise.');

            return false;
        }

        return true;
    }

    public function createTenant(TenantRequest $request): Feedback
    {
        $feedback = new Feedback();

        $violations = $this->validator->validate($request, null, ['create']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setFlushDescriptionWithError('Les données du locataire sont invalides.')
                ->autoInitFlush();
        }

        if (!$this->validateTenantType($request, $feedback)) {
            return $feedback
                ->setFlushDescriptionWithError('Identité du locataire incomplète.')
                ->autoInitFlush();
        }

        $organization = $this->resolveWritableOrganization($request, $feedback);
        if ($organization === null) {
            return $feedback
                ->setFlushDescriptionWithError('Organisation de rattachement invalide.')
                ->autoInitFlush();
        }

        $tenant = new Tenant();
        $tenant->setOrganization($organization);
        $city = $this->resolveTenantCity(
            $request->cityUuid,
            $organization,
            $feedback,
            $this->securityService->getOrganizationRole($this->securityService->getCurrentUser(), $organization) === OrganizationRole::ADMIN_VILLE,
        );
        if (($request->cityUuid !== null || $this->securityService->getOrganizationRole($this->securityService->getCurrentUser(), $organization) === OrganizationRole::ADMIN_VILLE) && $city === null) {
            return $feedback->setStatus(422)->autoInitFlush();
        }
        $tenant->setCity($city);
        $this->tenantMapper->copyToEntity($request, $tenant);

        $this->entityManager->persist($tenant);
        $this->entityManager->flush();

        return $feedback
            ->setData($this->tenantMapper->toResponse($tenant))
            ->setFlushDescription('La fiche du locataire a été créée avec succès.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    public function updateTenant(string $uuid, TenantRequest $request): Feedback
    {
        $feedback = new Feedback();

        $tenant = $this->findTenant($uuid, $feedback);
        if ($tenant === null) {
            return $feedback->autoInitFlush();
        }

        $violations = $this->validator->validate($request, null, ['update']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setFlushDescriptionWithError('Les données de mise à jour du locataire sont invalides.')
                ->autoInitFlush();
        }

        // Autorisation sur l'entité réellement visée, pas sur l'organization
        // éventuellement fournie dans le payload : un attaquant ne doit pas
        // pouvoir déplacer un locataire vers une organization où il est
        // autorisé en observant un 200 au lieu d'un refus.
        $this->securityService->checkTenantAccess($tenant, SecurityAction::UPDATE_TENANT);

        $oldValues = [
            'type' => $tenant->getType()->value,
            'fullName' => $tenant->getFullName(),
            'companyName' => $tenant->getCompanyName(),
            'phone' => $tenant->getPhone(),
            'email' => $tenant->getEmail(),
            'address' => $tenant->getAddress(),
            'cityUuid' => $tenant->getCity()?->getUuid()->toRfc4122(),
        ];

        if ($request->cityUuid !== null) {
            $city = $this->resolveTenantCity($request->cityUuid, $tenant->getOrganization(), $feedback, false);
            if ($city === null) {
                return $feedback->setStatus(422)->autoInitFlush();
            }
            $tenant->setCity($city);
        }

        if ($request->organizationUuid !== null) {
            $target = $this->resolveWritableOrganization($request, $feedback);
            if ($target === null) {
                return $feedback
                    ->setFlushDescriptionWithError('Organisation de rattachement invalide.')
                    ->autoInitFlush();
            }

            if ($target !== $tenant->getOrganization()) {
                $feedback->addError(
                    'organizationUuid',
                    'Le rattachement du locataire à une autre organisation n\'est pas autorisé.'
                );

                return $feedback
                    ->setFlushDescriptionWithError('Changement d\'organisation refusé.')
                    ->autoInitFlush();
            }
        }

        $this->tenantMapper->copyToEntity($request, $tenant);
        $this->auditLogService->log(
            action: 'UPDATE_TENANT',
            entityType: Tenant::class,
            entityId: (int) $tenant->getId(),
            organization: $tenant->getOrganization(),
            user: $this->securityService->getCurrentUser(),
            oldValues: $oldValues,
            newValues: [
                'type' => $tenant->getType()->value,
                'fullName' => $tenant->getFullName(),
                'companyName' => $tenant->getCompanyName(),
                'phone' => $tenant->getPhone(),
                'email' => $tenant->getEmail(),
                'address' => $tenant->getAddress(),
                'cityUuid' => $tenant->getCity()?->getUuid()->toRfc4122(),
            ],
            flush: false,
        );
        $this->entityManager->flush();

        return $feedback
            ->setData($this->tenantMapper->toResponse($tenant))
            ->setFlushDescription('La fiche du locataire a été mise à jour avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function getTenantByUuid(string $uuid): Feedback
    {
        $feedback = new Feedback();

        $tenant = $this->findTenant($uuid, $feedback);
        if ($tenant === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkTenantAccess($tenant, SecurityAction::VIEW_TENANT);

        return $feedback
            ->setData($this->tenantMapper->toResponse($tenant))
            ->setFlushDescription('Locataire récupéré avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Liste paginée des locataires sur le périmètre réellement autorisé.
     *
     * Le périmètre provient de `SecurityService` : pour un PATRON ou un
     * ADMIN_IMMOBILIER, toutes les organizations de l'appelant ; pour un
     * administrateur de ville, restreint à ses villes attitrées. Le
     * filtrage est fait en SQL (une seule requête) plutôt qu'en
     * recontrôlant chaque ligne en PHP, afin de ne jamais fuir un
     * total incohérent avec les éléments réellement renvoyés.
     *
     * `organizationId` (optionnel) resserre la liste à une organisation
     * du périmètre. Une organisation hors périmètre renvoie une liste
     * vide : identifier « l'existence » d'un tenant d'un autre tenant
     * via le code de réponse serait une fuite d'information.
     */
    public function listTenants(?TenantFilterDto $filter = null): Feedback
    {
        $feedback = new Feedback();
        $filter ??= new TenantFilterDto();

        $organizations = $this->securityService->getCurrentUserOrganizations();
        if ($filter->organizationId !== null && $filter->organizationId !== '') {
            $restricted = $this->restrictToOrganization($filter->organizationId, $organizations);
            if ($restricted === null) {
                return $this->emptyListResponse($feedback, $filter->page, $filter->limit);
            }

            $organizations = $restricted;
        }

        $user = $this->securityService->getCurrentUser();
        $fullAccessOrganizations = [];
        $cityScopedOrganizations = [];
        foreach ($organizations as $organization) {
            if ($this->securityService->getOrganizationRole($user, $organization) === OrganizationRole::ADMIN_VILLE) {
                $cityScopedOrganizations[] = $organization;
            } else {
                $fullAccessOrganizations[] = $organization;
            }
        }
        $cityScopedOrganizationIds = array_map(static fn (Organization $organization): int => $organization->getId(), $cityScopedOrganizations);
        $allowedCities = array_values(array_filter(
            $this->securityService->getAccessibleCities() ?? [],
            static fn (City $city): bool => in_array($city->getOrganization()->getId(), $cityScopedOrganizationIds, true)
        ));

        $selectedCity = null;
        if ($filter->cityUuid !== null && $filter->cityUuid !== '') {
            try {
                $selectedCity = $this->cityRepository->findOneByUuid(Uuid::fromString($filter->cityUuid));
            } catch (\InvalidArgumentException) {
                $selectedCity = null;
            }
            $belongsToRequestedOrganization = $selectedCity !== null && in_array(
                $selectedCity->getOrganization()->getId(),
                array_map(static fn (Organization $organization): int => $organization->getId(), $organizations),
                true,
            );
            $isAssignedCity = $selectedCity !== null && ($this->securityService->getAccessibleCities() === null
                || in_array($selectedCity, $allowedCities, true)
                || !in_array($selectedCity->getOrganization()->getId(), $cityScopedOrganizationIds, true));
            if (!$belongsToRequestedOrganization || !$isAssignedCity) {
                return $this->emptyListResponse($feedback, $filter->page, $filter->limit);
            }
        } elseif ($cityScopedOrganizations !== []) {
            // Un ADMIN_VILLE multi-ville doit toujours travailler dans sa ville
            // active : une liste union de ses villes confondrait les espaces.
            return $this->emptyListResponse($feedback, $filter->page, $filter->limit);
        }

        $result = $this->tenantRepository->findPaginatedAccessible(
            $organizations,
            $allowedCities,
            $filter->page,
            $filter->limit,
            $filter->search,
            $filter->sortBy ?? 'fullName',
            $filter->sortOrder,
            $fullAccessOrganizations,
            $selectedCity,
        );

        return $feedback
            ->setData([
                'items' => array_map(
                    fn (Tenant $tenant): TenantResponse => $this->tenantMapper->toResponse($tenant),
                    $result['items']
                ),
                'total' => $result['total'],
                'page' => max(1, $filter->page),
                'limit' => $filter->limit,
            ])
            ->setFlushDescription('Locataires listés avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Archivage (suppression logique) d'un locataire.
     *
     * Un locataire archivé disparaît des listes mais conserve son
     * historique de baux : la suppression physique rendrait l'historique
     * de location illisible. L'opération est tracée dans le journal
     * d'audit.
     */
    public function archiveTenant(string $uuid, User $currentUser): Feedback
    {
        $feedback = new Feedback();

        $tenant = $this->findTenant($uuid, $feedback);
        if ($tenant === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkTenantAccess($tenant, SecurityAction::ARCHIVE_TENANT);

        if ($tenant->getDeletedAt() !== null) {
            return $feedback
                ->setErrorFlushDescription('Ce locataire est déjà archivé.')
                ->setStatus(409)
                ->autoInitFlush();
        }

        $tenant->softDelete();
        $this->auditLogService->log(
            action: 'ARCHIVE_TENANT',
            entityType: Tenant::class,
            entityId: $tenant->getId(),
            organization: $tenant->getOrganization(),
            user: $currentUser,
            oldValues: [
                'fullName' => $tenant->getFullName(),
                'companyName' => $tenant->getCompanyName(),
                'phone' => $tenant->getPhone(),
                'email' => $tenant->getEmail(),
            ],
            newValues: [
                'deletedAt' => $tenant->getDeletedAt() !== null
                    ? $this->dateTime->format($tenant->getDeletedAt(), 'Y-m-d H:i:s')
                    : null,
            ],
            flush: false,
        );
        $this->entityManager->flush();

        return $feedback
            ->setData($this->tenantMapper->toResponse($tenant))
            ->setFlushDescription('Le locataire a été archivé avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Restreint la liste des organizations du périmètre à celle demandée.
     *
     * Retourne `null` si l'UUID est invalide ou ne fait pas partie du
     * périmètre de l'appelant, ce qui déclenche une liste vide.
     *
     * @param list<Organization> $organizations
     *
     * @return list<Organization>|null
     */
    private function restrictToOrganization(
        string $uuid,
        array $organizations
    ): ?array {
        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $organization = $this->organizationRepository->findOneByUuid($parsed);

        if ($organization === null) {
            return null;
        }

        foreach ($organizations as $candidate) {
            if ($candidate->getId() === $organization->getId()) {
                return [$organization];
            }
        }

        return null;
    }

    private function emptyListResponse(Feedback $feedback, int $page, int $limit): Feedback
    {
        return $feedback
            ->setData([
                'items' => [],
                'total' => 0,
                'page' => max(1, $page),
                'limit' => $limit,
            ])
            ->setFlushDescription('Liste vide : organisation hors périmètre.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Résolution UUID → entité avec traduction des cas d'erreur.
     */
    private function findTenant(string $uuid, Feedback $feedback): ?Tenant
    {
        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            $feedback
                ->setErrorFlushDescription('Identifiant de locataire invalide.')
                ->setStatus(400);

            return null;
        }

        $tenant = $this->tenantRepository->findOneByUuid($parsed);

        if ($tenant === null) {
            $feedback
                ->setErrorFlushDescription('Locataire introuvable.')
                ->setStatus(404);
        }

        return $tenant;
    }
}
