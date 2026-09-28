<?php

declare(strict_types=1);

namespace App\Service\Rental;

use App\Dto\Feedback;
use App\Dto\Request\Rental\PaymentRequest;
use App\Entity\Identity\User;
use App\Entity\Rental\Payment;
use App\Entity\Rental\Rent;
use App\Enum\RentStatus;
use App\Mapper\Rental\PaymentMapper;
use App\Repository\Rental\PaymentRepository;
use App\Repository\Rental\RentRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * PaymentService
 *
 * Package : Rental Management
 *
 * Un paiement se rattache à une échéance, elle-même rattachée à un bail
 * et donc à une organization. La lecture d'un paiement par UUID ne doit
 * jamais court-circuiter cette chaîne : la résolution se fait globalement
 * (pour ne pas filtrer par organization avant de savoir laquelle
 * s'applique), puis l'accès est vérifié sur l'entité résolue.
 */
final readonly class PaymentService
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private RentRepository $rentRepository,
        private PaymentMapper $paymentMapper,
        private SecurityServiceInterface $securityService,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
    ) {
    }

    public function recordPayment(PaymentRequest $request, User $currentUser): Feedback
    {
        $feedback = new Feedback();

        $violations = $this->validator->validate($request, null, ['create']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setFlushDescriptionWithError('Les données du paiement sont invalides.')
                ->autoInitFlush();
        }

        $rent = $this->resolveRent($request->rentUuid, SecurityAction::CREATE_PAYMENT, $feedback);
        if ($rent === null) {
            return $feedback->autoInitFlush();
        }

        $payment = new Payment();
        $payment->setRent($rent);
        $payment->setCreatedBy($currentUser);
        $this->paymentMapper->copyToEntity($request, $payment);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        // Le statut de l'échéance (partiellement payée / soldée) est
        // recalculé à partir de la somme des paiements : il ne doit pas
        // être fourni par le client.
        $this->refreshRentStatus($rent);

        return $feedback
            ->setData($this->paymentMapper->toResponse($payment))
            ->setFlushDescription('Le paiement a été enregistré avec succès.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    public function getPaymentByUuid(string $uuid): Feedback
    {
        $feedback = new Feedback();

        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            return $feedback
                ->setErrorFlushDescription('Identifiant de paiement invalide.')
                ->setStatus(400)
                ->autoInitFlush();
        }

        $payment = $this->paymentRepository->findOneByUuid($parsed);

        if ($payment === null) {
            return $feedback
                ->setErrorFlushDescription('Paiement introuvable.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $this->securityService->checkPaymentAccess($payment, SecurityAction::VIEW_PAYMENT);

        return $feedback
            ->setData($this->paymentMapper->toResponse($payment))
            ->setFlushDescription('Paiement récupéré avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Liste paginée des paiements du périmètre autorisé.
     */
    public function listPayments(int $page = 1, int $limit = 20, ?string $search = null): Feedback
    {
        $feedback = new Feedback();

        $organizations = $this->securityService->getCurrentUserOrganizations();
        $items = [];

        foreach ($organizations as $organization) {
            if (!$this->securityService->canAccessOrganization($organization, SecurityAction::VIEW_PAYMENT)) {
                continue;
            }

            $result = $this->paymentRepository->findPaginatedByOrganization(
                $organization,
                $page,
                $limit,
                $search
            );

            foreach ($result['items'] as $payment) {
                if ($this->securityService->canAccessPayment($payment, SecurityAction::VIEW_PAYMENT)) {
                    $items[] = $this->paymentMapper->toResponse($payment);
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
            ->setFlushDescription('Paiements listés avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Recalcule le statut de l'échéance à partir des paiements enregistrés.
     *
     * Le montant versé est comparé au montant dû en utilisant des
     * comparaisons décimales exactes (bcpmath/scale 2) plutôt que des
     * flottants, afin d'éviter qu'un centime d'arrondi fasse basculer
     * l'échéance en « soldée » à tort.
     */
    private function refreshRentStatus(Rent $rent): void
    {
        $rent->syncStatus($this->paymentRepository->sumAmountByRent($rent));

        $this->entityManager->flush();
    }

    private function resolveRent(?string $uuid, SecurityAction $action, Feedback $feedback): ?Rent
    {
        if ($uuid === null || $uuid === '') {
            $feedback->addError('rentUuid', 'L\'échéance de loyer est obligatoire.');

            return null;
        }

        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            $feedback->addError('rentUuid', 'Identifiant d\'échéance invalide.');

            return null;
        }

        $rent = $this->rentRepository->findOneByUuid($parsed);

        if ($rent === null) {
            $feedback
                ->setErrorFlushDescription('Échéance de loyer introuvable.')
                ->setStatus(404);

            return null;
        }

        $this->securityService->checkRentAccess($rent, $action);

        return $rent;
    }
}
