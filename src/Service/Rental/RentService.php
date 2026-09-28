<?php

declare(strict_types=1);

namespace App\Service\Rental;

use App\Dto\Feedback;
use App\Dto\Request\Rental\RentRequest;
use App\Entity\Rental\Lease;
use App\Entity\Rental\Rent;
use App\Mapper\Rental\RentMapper;
use App\Repository\Rental\LeaseRepository;
use App\Repository\Rental\PaymentRepository;
use App\Repository\Rental\RentRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * RentService
 *
 * Package : Rental Management
 *
 * Une échéance (Rent) hérite de son périmètre du bail (Lease), lequel
 * hérite du sien de l'Organization. Le service ne reçoit donc jamais
 * d'Organization en paramètre : celle-ci est déduite de la chaîne
 * d'entités, ce qui élimine la possibilité qu'un client désigne une
 * organization pour rattacher une échéance à un bail d'une autre.
 */
final readonly class RentService
{
    public function __construct(
        private RentRepository $rentRepository,
        private LeaseRepository $leaseRepository,
        private PaymentRepository $paymentRepository,
        private RentMapper $rentMapper,
        private SecurityServiceInterface $securityService,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
    ) {
    }

    public function createRent(RentRequest $request): Feedback
    {
        $feedback = new Feedback();

        $violations = $this->validator->validate($request, null, ['create']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setFlushDescriptionWithError('Les données de l\'échéance sont invalides.')
                ->autoInitFlush();
        }

        $lease = $this->resolveLease($request->leaseUuid, SecurityAction::CREATE_RENT, $feedback);
        if ($lease === null) {
            return $feedback->autoInitFlush();
        }

        // Unicité du couple (bail, période) : elle est déjà garantie par un
        // index unique en base, mais elle est vérifiée ici pour renvoyer un
        // 422 exploitable au lieu d'une violation de contrainte SQL.
        if ($request->period !== null
            && $this->rentRepository->findOneByLeaseAndPeriod($lease, $request->period) !== null
        ) {
            return $feedback
                ->addError('period', 'Une échéance existe déjà pour ce mois et ce bail.')
                ->setFlushDescriptionWithError('L\'échéance pour cette période a déjà été générée.')
                ->setStatus(409)
                ->autoInitFlush();
        }

        $rent = new Rent();
        $rent->setLease($lease);
        $this->rentMapper->copyToEntity($request, $rent);
        $rent->syncStatus($this->paymentRepository->sumAmountByRent($rent));

        $this->entityManager->persist($rent);
        $this->entityManager->flush();

        return $feedback
            ->setData($this->rentMapper->toResponse($rent))
            ->setFlushDescription('L\'échéance de loyer a été créée avec succès.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    public function updateRent(string $uuid, RentRequest $request): Feedback
    {
        $feedback = new Feedback();

        $rent = $this->findRent($uuid, $feedback);
        if ($rent === null) {
            return $feedback->autoInitFlush();
        }

        $violations = $this->validator->validate($request, null, ['update']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setFlushDescriptionWithError('Les données de mise à jour sont invalides.')
                ->autoInitFlush();
        }

        $this->securityService->checkRentAccess($rent, SecurityAction::UPDATE_RENT);

        // Rattacher une échéance existante à un autre bail est refusé : cela
        // déplacerait un montant d'une échéance vers une autre organization
        // en contournant le contrôle d'accès du bail cible.
        if ($request->leaseUuid !== null) {
            $target = $this->resolveLease($request->leaseUuid, SecurityAction::VIEW_LEASE, $feedback);

            if ($target === null) {
                return $feedback->autoInitFlush();
            }

            if ($target !== $rent->getLease()) {
                $feedback->addError(
                    'leaseUuid',
                    'Le rattachement d\'une échéance à un autre bail n\'est pas autorisé.'
                );

                return $feedback
                    ->setFlushDescriptionWithError('Changement de bail refusé.')
                    ->autoInitFlush();
            }
        }

        $this->rentMapper->copyToEntity($request, $rent);

        // Changer le montant ou la date d'exigibilité change ce que doit
        // être le statut : il est recalculé sur les paiements réellement
        // enregistrés, jamais sur une valeur fournie par le client.
        $rent->syncStatus($this->paymentRepository->sumAmountByRent($rent));

        $this->entityManager->flush();

        return $feedback
            ->setData($this->rentMapper->toResponse($rent))
            ->setFlushDescription('L\'échéance de loyer a été mise à jour avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function getRentByUuid(string $uuid): Feedback
    {
        $feedback = new Feedback();

        $rent = $this->findRent($uuid, $feedback);
        if ($rent === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkRentAccess($rent, SecurityAction::VIEW_RENT);

        return $feedback
            ->setData($this->rentMapper->toResponse($rent))
            ->setFlushDescription('Échéance de loyer récupérée.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Liste paginée des échéances en retard sur le périmètre autorisé.
     */
    public function listOverdueRents(int $page = 1, int $limit = 20): Feedback
    {
        $feedback = new Feedback();

        $organizations = $this->securityService->getCurrentUserOrganizations();
        $items = [];

        foreach ($organizations as $organization) {
            if (!$this->securityService->canAccessOrganization($organization, SecurityAction::VIEW_RENT)) {
                continue;
            }

            foreach ($this->rentRepository->findOverdueByOrganization($organization) as $rent) {
                if ($this->securityService->canAccessRent($rent, SecurityAction::VIEW_RENT)) {
                    $items[] = $this->rentMapper->toResponse($rent);
                }
            }
        }

        return $feedback
            ->setData([
                'items' => $items,
                'total' => count($items),
                'page' => max(1, $page),
                'limit' => $limit,
            ])
            ->setFlushDescription('Échéances en retard listées avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    private function resolveLease(?string $uuid, SecurityAction $action, Feedback $feedback): ?Lease
    {
        if ($uuid === null || $uuid === '') {
            $feedback->addError('leaseUuid', 'Le contrat de bail est obligatoire.');

            return null;
        }

        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            $feedback->addError('leaseUuid', 'Identifiant de bail invalide.');

            return null;
        }

        $lease = $this->leaseRepository->findOneByUuid($parsed);

        if ($lease === null) {
            $feedback
                ->setErrorFlushDescription('Contrat de bail introuvable.')
                ->setStatus(404);

            return null;
        }

        $this->securityService->checkLeaseAccess($lease, $action);

        return $lease;
    }

    private function findRent(string $uuid, Feedback $feedback): ?Rent
    {
        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            $feedback
                ->setErrorFlushDescription('Identifiant d\'échéance invalide.')
                ->setStatus(400);

            return null;
        }

        $rent = $this->rentRepository->findOneByUuid($parsed);

        if ($rent === null) {
            $feedback
                ->setErrorFlushDescription('Échéance de loyer introuvable.')
                ->setStatus(404);
        }

        return $rent;
    }
}
