<?php

declare(strict_types=1);

namespace App\Service\Rental;

use App\Dto\Feedback;
use App\Dto\Request\Rental\RentRequest;
use App\Entity\Identity\Organization;
use App\Entity\Rental\Lease;
use App\Entity\Rental\Rent;
use App\Mapper\Rental\RentMapper;
use App\Repository\Rental\LeaseRepository;
use App\Repository\Rental\RentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class RentService
{
    public function __construct(
        private RentRepository $rentRepository,
        private LeaseRepository $leaseRepository,
        private RentMapper $rentMapper,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
    ) {
    }

    public function createRent(RentRequest $request, Organization $organization): Feedback
    {
        $feedback = new Feedback();

        $violations = $this->validator->validate($request, null, ['create']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setFlushDescriptionWithError('Les données de l\'échéance sont invalides.')
                ->autoInitFlush();
        }

        /** @var Lease|null $lease */
        $lease = $this->leaseRepository->findOneBy(['uuid' => $request->leaseUuid, 'organization' => $organization]);
        if (!$lease) {
            return $feedback
                ->addError('leaseUuid', 'Contrat de bail non trouvé.')
                ->setFlushDescriptionWithError('Le bail spécifié n\'existe pas ou n\'appartient pas à votre organisation.')
                ->autoInitFlush();
        }

        // Contrôle d'unicité sur le couple (lease, period)
        $existingRent = $this->rentRepository->findOneBy([
            'lease' => $lease,
            'period' => $request->period,
        ]);

        if ($existingRent !== null) {
            return $feedback
                ->addError('period', 'Une échéance existe déjà pour ce mois et ce bail.')
                ->setFlushDescriptionWithError('L\'échéance pour cette période a déjà été générée.')
                ->autoInitFlush();
        }

        $rent = $this->rentMapper->toEntity($request, $lease);

        $this->entityManager->persist($rent);
        $this->entityManager->flush();

        return $feedback
            ->setData($this->rentMapper->toResponse($rent))
            ->setFlushDescription('L\'échéance de loyer a été créée avec succès.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    public function updateRent(string $uuid, RentRequest $request, Organization $organization): Feedback
    {
        $feedback = new Feedback();

        /** @var Rent|null $rent */
        $rent = $this->rentRepository->findOneByUuidAndOrganization($uuid, $organization);
        if (!$rent) {
            return $feedback
                ->setErrorFlushDescription('Échéance de loyer introuvable.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $violations = $this->validator->validate($request, null, ['update']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setFlushDescriptionWithError('Les données de mise à jour sont invalides.')
                ->autoInitFlush();
        }

        $rent = $this->rentMapper->toEntity($request, $rent->getLease(), $rent);

        $this->entityManager->flush();

        return $feedback
            ->setData($this->rentMapper->toResponse($rent))
            ->setFlushDescription('L\'échéance de loyer a été mise à jour avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function getRentByUuid(string $uuid, Organization $organization): Feedback
    {
        $feedback = new Feedback();

        /** @var Rent|null $rent */
        $rent = $this->rentRepository->findOneByUuidAndOrganization($uuid, $organization);
        if (!$rent) {
            return $feedback
                ->setErrorFlushDescription('Échéance de loyer introuvable.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        return $feedback
            ->setData($this->rentMapper->toResponse($rent))
            ->setFlushDescription('Échéance de loyer récupérée.')
            ->setStatus(200)
            ->autoInitFlush();
    }
}
