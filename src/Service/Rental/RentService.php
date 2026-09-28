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
    public function listOverdueRents(?RentOverdueFilterDto $filter = null): Feedback
    {
        $feedback = new Feedback();

        $filter ??= new \App\Dto\Request\Rental\RentOverdueFilterDto();

        // Récupérer les organisations et villes accessibles
        $organizations = $this->securityService->getCurrentUserOrganizations();
        $organizationIds = array_map(fn($o) => $o->getId(), $organizations);

        $cityIds = [];
        foreach ($organizations as $org) {
            $cities = $this->cityRepository->findActiveByOrganization($org);
            foreach ($cities as $city) {
                if ($this->securityService->canAccessCity($city, SecurityAction::VIEW_RENT)) {
                    $cityIds[] = $city->getId();
                }
            }
        }

        if ($cityIds === []) {
            return $feedback
                ->setData(['items' => [], 'total' => 0, 'page' => 1, 'limit' => 20])
                ->setFlushDescription('Aucune ville accessible.')
                ->setStatus(200)
                ->autoInitFlush();
        }

        // Filtrage optionnel par organizationId
        $targetOrgIds = $organizationIds;
        if ($filter->organizationId !== null) {
            try {
                $uuid = \Symfony\Component\Uid\Uuid::fromString($filter->organizationId);
                $org = $this->organizationRepository->findOneByUuid($uuid);
                if ($org !== null && in_array($org->getId(), $organizationIds, true)) {
                    $targetOrgIds = [$org->getId()];
                } else {
                    $targetOrgIds = [];
                }
            } catch (\InvalidArgumentException) {
                $targetOrgIds = [];
            }
        }

        $result = $this->rentRepository->findOverduePaginatedByOrganizationsAndCities(
            organizationIds: $targetOrgIds,
            cityIds: $cityIds,
            page: $filter->page,
            limit: $filter->limit,
            sortBy: $filter->sortBy,
            sortOrder: $filter->sortOrder
        );

        // Appliquer le contrôle d'accès par échéance
        $items = array_filter($result['items'], function (Rent $rent): bool {
            return $this->securityService->canAccessRent($rent, SecurityAction::VIEW_RENT);
        });

        $items = array_map(fn(Rent $r) => $this->rentMapper->toResponse($r), $items);

        return $feedback
            ->setData([
                'items' => array_values($items),
                'total' => count($items),
                'page' => max(1, $filter->page),
                'limit' => $filter->limit,
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
