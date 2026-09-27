<?php

declare(strict_types=1);

namespace App\Service\Rental;

use App\Dto\Feedback;
use App\Dto\Request\Rental\TenantRequest;
use App\Entity\Identity\Organization;
use App\Dto\Response\Rental\TenantResponse;
use App\Entity\Rental\Tenant;
use App\Enum\TenantType;
use App\Mapper\Rental\TenantMapper;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Rental\TenantRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
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
        private TenantMapper $tenantMapper,
        private SecurityServiceInterface $securityService,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
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
     */
    public function listTenants(int $page = 1, int $limit = 20, ?string $search = null): Feedback
    {
        $feedback = new Feedback();

        $organizations = $this->securityService->getCurrentUserOrganizations();
        $allowedCities = $this->securityService->getAccessibleCities();

        $result = $this->tenantRepository->findPaginatedAccessible(
            $organizations,
            $allowedCities,
            $page,
            $limit,
            $search
        );

        return $feedback
            ->setData([
                'items' => array_map(
                    fn (Tenant $tenant): TenantResponse => $this->tenantMapper->toResponse($tenant),
                    $result['items']
                ),
                'total' => $result['total'],
                'page' => max(1, $page),
                'limit' => $limit,
            ])
            ->setFlushDescription('Locataires listés avec succès.')
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
