<?php

declare(strict_types=1);

namespace App\Service\Rental;

use App\Dto\Feedback;
use App\Dto\Request\Rental\PaymentFilterDto;
use App\Dto\Request\Rental\PaymentRequest;
use App\Entity\Identity\User;
use App\Entity\Rental\Payment;
use App\Entity\Rental\Rent;
use App\Enum\RentStatus;
use App\Mapper\Rental\PaymentMapper;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Property\CityRepository;
use App\Repository\Rental\PaymentRepository;
use App\Repository\Rental\RentRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\System\AuditLogService;
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
 *
 * Corrections P1-3 :
 * - Transaction avec verrou pessimiste sur l'échéance (Rent) pour éviter
 *   les conditions de course entre paiements simultanés.
 * - Comparaisons décimales exactes via bcmath (pas de float).
 * - Refus si montant > reste à payer (pas de surpaiement silencieux).
 * - Devise du paiement doit correspondre à celle de l'échéance.
 * - Validation Assert\Regex sur le montant (chiffres + max 2 décimales).
 * - Refus si bail non ACTIVE ou échéance déjà PAID.
 * - Annulation par contre-écriture (CANCEL_PAYMENT) disponible.
 */
final readonly class PaymentService
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private RentRepository $rentRepository,
        private CityRepository $cityRepository,
        private OrganizationRepository $organizationRepository,
        private PaymentMapper $paymentMapper,
        private SecurityServiceInterface $securityService,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private AuditLogService $auditLogService
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

        // Résoudre l'échéance AVANT la transaction pour les vérifications préliminaires
        $rent = $this->resolveRent($request->rentUuid, SecurityAction::CREATE_PAYMENT, $feedback);
        if ($rent === null) {
            return $feedback->autoInitFlush();
        }

        // Vérifications métier AVANT la transaction (rapides, pas de verrou)
        $preCheckError = $this->preValidatePayment($rent, $request);
        if ($preCheckError !== null) {
            $feedback
                ->addError($preCheckError['field'], $preCheckError['message'])
                ->setFlushDescriptionWithError($preCheckError['message'])
                ->setStatus($preCheckError['status'])
                ->autoInitFlush();

            return $feedback;
        }

        // Exécution dans une transaction avec verrou pessimiste sur l'échéance
        $success = $this->entityManager->wrapInTransaction(
            function () use ($rent, $request, $currentUser, $feedback): bool {
                // Re-verrouiller l'échéance en mode pessimiste
                $lockedRent = $this->rentRepository->lockForUpdate($rent);
                if ($lockedRent === null) {
                    $feedback
                        ->setErrorFlushDescription('L\'échéance a été modifiée ou supprimée par un autre processus.')
                        ->setStatus(409);

                    return false;
                }

                // Re-vérifier l'état après verrouillage (double-check)
                $postLockError = $this->preValidatePayment($lockedRent, $request);
                if ($postLockError !== null) {
                    $feedback
                        ->addError($postLockError['field'], $postLockError['message'])
                        ->setFlushDescriptionWithError($postLockError['message'])
                        ->setStatus($postLockError['status']);

                    return false;
                }

                // Enregistrer le paiement
                $payment = new Payment();
                $payment->setRent($lockedRent);
                $payment->setCreatedBy($currentUser);
                $this->paymentMapper->copyToEntity($request, $payment);

                $this->entityManager->persist($payment);

                // Log d'audit : création du paiement
                $this->auditLogService->log(
                    action: 'CREATE_PAYMENT',
                    entityType: Payment::class,
                    entityId: $payment->getId(),
                    organization: $lockedRent->getLease()->getOrganization(),
                    user: $currentUser,
                    oldValues: null,
                    newValues: [
                        'amount' => $payment->getAmount(),
                        'currency' => $payment->getCurrency()->value,
                        'paymentDate' => $payment->getPaymentDate()->format('Y-m-d\TH:i:s'),
                        'method' => $payment->getMethod()->value,
                        'rentUuid' => $lockedRent->getUuid()->toRfc4122(),
                        'reference' => $payment->getReference(),
                    ],
                );

                // Le flush unique ici persiste le paiement ET met à jour le statut
                // via refreshRentStatus appelé après.
                $this->refreshRentStatus($lockedRent);

                // Stocker pour le retour
                $feedback->setData($this->paymentMapper->toResponse($payment));

                return true;
            }
        );

        if (!$success) {
            return $feedback->autoInitFlush();
        }

        return $feedback
            ->setFlushDescription('Le paiement a été enregistré avec succès.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    /**
     * Vérifications métier communes (utilisées avant et après verrou).
     *
     * @return array{field: string, message: string, status: int}|null
     */
    private function preValidatePayment(Rent $rent, PaymentRequest $request): ?array
    {
        // 1) Bail doit être ACTIVE
        $lease = $rent->getLease();
        if ($lease->getStatus() !== \App\Enum\LeaseStatus::ACTIVE) {
            return [
                'field' => 'rentUuid',
                'message' => 'Impossible d\'enregistrer un paiement : le bail n\'est pas actif.',
                'status' => 409,
            ];
        }

        // 2) Échéance ne doit pas être déjà PAID (on peut payer du PARTIALLY_PAID ou PENDING/OVERDUE)
        if ($rent->getStatus() === RentStatus::PAID) {
            return [
                'field' => 'rentUuid',
                'message' => 'Cette échéance est déjà soldée. Aucun paiement supplémentaire n\'est accepté.',
                'status' => 409,
            ];
        }

        // 3) Devise du paiement = devise de l'échéance
        if ($request->currency !== $rent->getCurrency()) {
            return [
                'field' => 'currency',
                'message' => 'La devise du paiement doit correspondre à celle de l\'échéance (' . $rent->getCurrency()->value . ').',
                'status' => 422,
            ];
        }

        // 4) Montant strictement positif (déjà validé par Assert\Regex dans le DTO)
        // 5) Montant ne doit pas dépasser le reste à payer
        $due = $rent->getAmount();
        $paid = $this->paymentRepository->sumAmountByRent($rent);
        $remaining = bcsub($due, $paid, 2);
        $paymentAmount = $request->amount;

        if (bccomp($paymentAmount, $remaining, 2) > 0) {
            return [
                'field' => 'amount',
                'message' => "Le montant du paiement ({$paymentAmount}) dépasse le reste à payer ({$remaining}).",
                'status' => 422,
            ];
        }

        return null;
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
    public function listPayments(?PaymentFilterDto $filter = null): Feedback
    {
        $feedback = new Feedback();

        $filter ??= new \App\Dto\Request\Rental\PaymentFilterDto();

        // Récupérer les organisations et villes accessibles
        $organizations = $this->securityService->getCurrentUserOrganizations();
        $organizationIds = array_map(fn($o) => $o->getId(), $organizations);

        $cityIds = [];
        foreach ($organizations as $org) {
            $cities = $this->cityRepository->findActiveByOrganization($org);
            foreach ($cities as $city) {
                if ($this->securityService->canAccessCity($city, SecurityAction::VIEW_PAYMENT)) {
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

        $result = $this->paymentRepository->findPaginatedByOrganizationsAndCities(
            organizationIds: $targetOrgIds,
            cityIds: $cityIds,
            page: $filter->page,
            limit: $filter->limit,
            sortBy: $filter->sortBy,
            sortOrder: $filter->sortOrder
        );

        // Appliquer le contrôle d'accès par paiement
        $items = array_filter($result['items'], function (Payment $payment): bool {
            return $this->securityService->canAccessPayment($payment, SecurityAction::VIEW_PAYMENT);
        });

        $items = array_map(fn(Payment $p) => $this->paymentMapper->toResponse($p), $items);

        return $feedback
            ->setData([
                'items' => array_values($items),
                'total' => count($items),
                'page' => max(1, $filter->page),
                'limit' => $filter->limit,
            ])
            ->setFlushDescription('Paiements listés avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Annule un paiement par contre-écriture.
     *
     * Crée un paiement négatif (via correction) pour annuler l'effet du paiement
     * original, puis recalcule le statut de l'échéance. Le paiement original
     * n'est PAS supprimé (traçabilité complète).
     */
    public function cancelPayment(string $uuid, string $reason, User $currentUser): Feedback
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

        $this->securityService->checkPaymentAccess($payment, SecurityAction::CANCEL_PAYMENT);

        $rent = $payment->getRent();

        // Vérifier que l'annulation ne ferait pas passer le total payé sous zéro
        $currentPaid = $this->paymentRepository->sumAmountByRent($rent);
        $cancelAmount = $payment->getAmount();
        $newPaid = bcsub($currentPaid, $cancelAmount, 2);

        if (bccomp($newPaid, '0.00', 2) < 0) {
            return $feedback
                ->addError('amount', 'L\'annulation ferait passer le total payé en négatif.')
                ->setFlushDescriptionWithError('Annulation impossible : montant total payé deviendrait négatif.')
                ->setStatus(409)
                ->autoInitFlush();
        }

        // Créer la contre-écriture (paiement de correction)
        $correction = new Payment();
        $correction->setRent($rent);
        $correction->setCreatedBy($currentUser);
        $correction->setAmount($cancelAmount);
        $correction->setCurrency($payment->getCurrency());
        $correction->setPaymentDate(new \DateTimeImmutable());
        $correction->setMethod($payment->getMethod());
        $correction->setReference('ANNUL-' . $payment->getReference());
        $correction->setReceiptNumber($payment->getReceiptNumber());
        $correction->setNotes("Annulation du paiement {$payment->getReference()} : {$reason}");

        $this->entityManager->persist($correction);

        // Log d'audit : annulation du paiement
        $this->auditLogService->log(
            action: 'CANCEL_PAYMENT',
            entityType: Payment::class,
            entityId: $payment->getId(),
            organization: $rent->getLease()->getOrganization(),
            user: $currentUser,
            oldValues: [
                'amount' => $payment->getAmount(),
                'reference' => $payment->getReference(),
            ],
            newValues: [
                'amount' => $correction->getAmount(),
                'reference' => $correction->getReference(),
                'notes' => $correction->getNotes(),
            ],
        );

        $this->refreshRentStatus($rent);
        $this->entityManager->flush();

        return $feedback
            ->setData($this->paymentMapper->toResponse($correction))
            ->setFlushDescription('Le paiement a été annulé par contre-écriture.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    /**
     * Recalcule le statut de l'échéance à partir des paiements enregistrés.
     *
     * Utilise bcmath pour des comparaisons décimales exactes (scale 2)
     * afin d'éviter qu'un centime d'arrondi fasse basculer l'échéance
     * en « soldée » à tort.
     *
     * Le statut ne descend JAMAIS : un loyer PAID reste PAID même si on
     * annule un paiement (la correction créera une nouvelle échéance si besoin).
     * Ici on ne fait que monter : PENDING -> PARTIALLY_PAID -> PAID.
     * OVERDUE est géré par Rent::syncStatus selon la date.
     */
    private function refreshRentStatus(Rent $rent): void
    {
        // On ne fait PAS descendre le statut : une fois PAID, reste PAID
        // (l'annulation crée une contre-écriture, pas une suppression).
        $currentStatus = $rent->getStatus();
        if ($currentStatus === RentStatus::PAID) {
            return;
        }

        $rent->syncStatus($this->paymentRepository->sumAmountByRent($rent));
        // flush géré par l'appelant (dans la transaction)
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