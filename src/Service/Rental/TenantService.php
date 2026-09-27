<?php

declare(strict_types=1);

namespace App\Service\Rental;

use App\Dto\Feedback;
use App\Dto\Request\Rental\TenantRequest;
use App\Entity\Identity\Organization;
use App\Entity\Rental\Tenant;
use App\Enum\TenantType;
use App\Mapper\Rental\TenantMapper;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Rental\TenantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class TenantService
{
    public function __construct(
        private TenantRepository $tenantRepository,
        private OrganizationRepository $organizationRepository,
        private TenantMapper $tenantMapper,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
    ) {
    }

    public function createTenant(TenantRequest $request, Organization $organization): Feedback
    {
        $feedback = new Feedback();

        $violations = $this->validator->validate($request, null, ['create']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setFlushDescriptionWithError('Les données du locataire sont invalides.')
                ->autoInitFlush();
        }

        // Validation métier complémentaire selon le type de locataire
        if ($request->type === TenantType::INDIVIDUAL && (empty($request->firstName) || empty($request->lastName))) {
            return $feedback
                ->addError('firstName', 'Le prénom et le nom sont requis pour une personne physique.')
                ->addError('lastName', 'Le prénom et le nom sont requis pour une personne physique.')
                ->setFlushDescriptionWithError('Identité du locataire incomplète.')
                ->autoInitFlush();
        }

        if ($request->type === TenantType::COMPANY && empty($request->companyName)) {
            return $feedback
                ->addError('companyName', 'La raison sociale est requise pour une entreprise.')
                ->setFlushDescriptionWithError('Nom d\'entreprise manquant.')
                ->autoInitFlush();
        }

        $tenant = $this->tenantMapper->toEntity($request, $organization);

        $this->entityManager->persist($tenant);
        $this->entityManager->flush();

        return $feedback
            ->setData($this->tenantMapper->toResponse($tenant))
            ->setFlushDescription('La fiche du locataire a été créée avec succès.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    public function updateTenant(string $uuid, TenantRequest $request, Organization $organization): Feedback
    {
        $feedback = new Feedback();

        /** @var Tenant|null $tenant */
        $tenant = $this->tenantRepository->findOneBy(['uuid' => $uuid, 'organization' => $organization]);
        if (!$tenant) {
            return $feedback
                ->setErrorFlushDescription('Locataire non trouvé.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $violations = $this->validator->validate($request, null, ['update']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setFlushDescriptionWithError('Les données de mise à jour du locataire sont invalides.')
                ->autoInitFlush();
        }

        $tenant = $this->tenantMapper->toEntity($request, $organization, $tenant);

        $this->entityManager->flush();

        return $feedback
            ->setData($this->tenantMapper->toResponse($tenant))
            ->setFlushDescription('La fiche du locataire a été mise à jour avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function getTenantByUuid(string $uuid, Organization $organization): Feedback
    {
        $feedback = new Feedback();

        /** @var Tenant|null $tenant */
        $tenant = $this->tenantRepository->findOneBy(['uuid' => $uuid, 'organization' => $organization]);
        if (!$tenant) {
            return $feedback
                ->setErrorFlushDescription('Locataire introuvable.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        return $feedback
            ->setData($this->tenantMapper->toResponse($tenant))
            ->setFlushDescription('Locataire récupéré avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }
}
