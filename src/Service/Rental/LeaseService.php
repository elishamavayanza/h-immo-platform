<?php

declare(strict_types=1);

namespace App\Service\Rental;

use App\Dto\Feedback;
use App\Dto\Request\Rental\LeaseRequest;
use App\Dto\Response\Rental\LeaseResponse;
use App\Entity\Identity\Organization;
use App\Entity\Property\Unit;
use App\Entity\Rental\Lease;
use App\Entity\Rental\Tenant;
use App\Enum\LeaseStatus;
use App\Mapper\Rental\LeaseMapper;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Property\UnitRepository;
use App\Repository\Rental\LeaseRepository;
use App\Repository\Rental\TenantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class LeaseService
{
    public function __construct(
        private LeaseRepository $leaseRepository,
        private OrganizationRepository $organizationRepository,
        private TenantRepository $tenantRepository,
        private UnitRepository $unitRepository,
        private LeaseMapper $leaseMapper,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
    ) {
    }

    public function createLease(LeaseRequest $request, Organization $organization): Feedback
    {
        $feedback = new Feedback();

        $violations = $this->validator->validate($request, null, ['create']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setFlushDescriptionWithError('Les données du bail sont invalides.')
                ->autoInitFlush();
        }

        /** @var Tenant|null $tenant */
        $tenant = $this->tenantRepository->findOneBy(['uuid' => $request->tenantUuid]);
        if (!$tenant) {
            return $feedback
                ->addError('tenantUuid', 'Locataire non trouvé.')
                ->setFlushDescriptionWithError('Le locataire spécifié n\'existe pas.')
                ->autoInitFlush();
        }

        /** @var Unit|null $unit */
        $unit = $this->unitRepository->findOneBy(['uuid' => $request->unitUuid]);
        if (!$unit) {
            return $feedback
                ->addError('unitUuid', 'Unité locative non trouvée.')
                ->setFlushDescriptionWithError('L\'unité locative spécifiée n\'existe pas.')
                ->autoInitFlush();
        }

        // Règle métier : Vérification d'un bail actif sur la même Unit
        if ($request->status === LeaseStatus::ACTIVE) {
            $activeLeaseExists = $this->leaseRepository->hasActiveLeaseForUnit($unit);
            if ($activeLeaseExists) {
                return $feedback
                    ->addError('unitUuid', 'Cette unité a déjà un bail actif.')
                    ->setFlushDescriptionWithError('Une unité ne peut avoir qu\'un seul bail actif à la fois.')
                    ->autoInitFlush();
            }
        }

        $lease = $this->leaseMapper->toEntity($request, $organization, $tenant, $unit);

        $this->entityManager->persist($lease);
        $this->entityManager->flush();

        return $feedback
            ->setData($this->leaseMapper->toResponse($lease))
            ->setFlushDescription('Le contrat de bail a été créé avec succès.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    public function updateLease(string $uuid, LeaseRequest $request, Organization $organization): Feedback
    {
        $feedback = new Feedback();

        /** @var Lease|null $lease */
        $lease = $this->leaseRepository->findOneBy(['uuid' => $uuid, 'organization' => $organization]);
        if (!$lease) {
            return $feedback
                ->setErrorFlushDescription('Contrat de bail non trouvé.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $violations = $this->validator->validate($request, null, ['update']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setFlushDescriptionWithError('Les données de mise à jour du bail sont invalides.')
                ->autoInitFlush();
        }

        $tenant = $request->tenantUuid
            ? $this->tenantRepository->findOneBy(['uuid' => $request->tenantUuid])
            : $lease->getTenant();

        $unit = $request->unitUuid
            ? $this->unitRepository->findOneBy(['uuid' => $request->unitUuid])
            : $lease->getUnit();

        // Contrôle de conflit si passage au statut ACTIVE
        if ($request->status === LeaseStatus::ACTIVE && $lease->getStatus() !== LeaseStatus::ACTIVE) {
            if ($this->leaseRepository->hasActiveLeaseForUnit($unit, $lease->getId())) {
                return $feedback
                    ->addError('status', 'Unité déjà occupée sous un autre bail actif.')
                    ->setFlushDescriptionWithError('Impossible d\'activer ce bail : l\'unité est déjà sous un contrat actif.')
                    ->autoInitFlush();
            }
        }

        $lease = $this->leaseMapper->toEntity($request, $organization, $tenant, $unit, $lease);

        $this->entityManager->flush();

        return $feedback
            ->setData($this->leaseMapper->toResponse($lease))
            ->setFlushDescription('Le contrat de bail a été mis à jour avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function getLeaseByUuid(string $uuid, Organization $organization): Feedback
    {
        $feedback = new Feedback();

        /** @var Lease|null $lease */
        $lease = $this->leaseRepository->findOneBy(['uuid' => $uuid, 'organization' => $organization]);
        if (!$lease) {
            return $feedback
                ->setErrorFlushDescription('Contrat de bail introuvable.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        return $feedback
            ->setData($this->leaseMapper->toResponse($lease))
            ->setFlushDescription('Contrat de bail récupéré.')
            ->setStatus(200)
            ->autoInitFlush();
    }
}
