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
use App\Service\System\DateTimeService;
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
        private AuditLogService $auditLogService,
        private DateTimeService $dateTime,
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
                // L'identifiant interne est nécessaire pour rattacher le journal
                // d'audit au paiement, sans sortir de la transaction comptable.
                $this->entityManager->flush();

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
                        'exchangeRate' => $payment->getExchangeRate(),
                        'originalAmount' => $payment->getOriginalAmount(),
                        'originalCurrency' => $payment->getOriginalCurrency()?->value,
                        'paymentDate' => $this->dateTime->format($payment->getPaymentDate(), 'Y-m-d\TH:i:s'),
                        'method' => $payment->getMethod()->value,
                        'rentUuid' => $lockedRent->getUuid()->toRfc4122(),
                        'reference' => $payment->getReference(),
                    ],
                );

                // Le flush final de la transaction persiste aussi le statut recalculé.
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

        // 3) Gestion des devises : si paiement cross-devises, exiger taux + montant original
        $rentCurrency = $rent->getCurrency();
        $paymentCurrency = $request->currency;
        $sameCurrency = $paymentCurrency === $rentCurrency;

        if (!$sameCurrency) {
            // Paiement cross-devises : exiger taux de change, montant original, devise originale
            if ($request->exchangeRate === null) {
                return [
                    'field' => 'exchangeRate',
                    'message' => 'Un taux de change est obligatoire pour un paiement dans une devise différente de l\'échéance.',
                    'status' => 422,
                ];
            }
            if ($request->originalAmount === null) {
                return [
                    'field' => 'originalAmount',
                    'message' => 'Le montant original dans la devise d\'origine est obligatoire pour un paiement cross-devises.',
                    'status' => 422,
                ];
            }
            if ($request->originalCurrency === null) {
                return [
                    'field' => 'originalCurrency',
                    'message' => 'La devise d\'origine est obligatoire pour un paiement cross-devises.',
                    'status' => 422,
                ];
            }
            if ($request->originalCurrency !== $rentCurrency) {
                return [
                    'field' => 'originalCurrency',
                    'message' => 'La devise d\'origine doit correspondre à la devise de l\'échéance (' . $rentCurrency->value . ').',
                    'status' => 422,
                ];
            }

            // Valider que le taux est positif
            if (bccomp($request->exchangeRate, '0', 8) <= 0) {
                return [
                    'field' => 'exchangeRate',
                    'message' => 'Le taux de change doit être strictement positif.',
                    'status' => 422,
                ];
            }

            // Vérifier la cohérence : montant_paiement = montant_original * taux (arrondi 2 décimales)
            $expectedPaymentAmount = bcmul($request->originalAmount, $request->exchangeRate, 2);
            if (bccomp($expectedPaymentAmount, $request->amount, 2) !== 0) {
                return [
                    'field' => 'amount',
                    'message' => "Le montant du paiement ({$request->amount}) ne correspond pas au montant original ({$request->originalAmount}) converti au taux {$request->exchangeRate} (attendu : {$expectedPaymentAmount}).",
                    'status' => 422,
                ];
            }

            // Le montant à comparer au reste à payer est le montant dans la devise de l'échéance (request->amount)
            $paymentAmountInRentCurrency = $request->amount;
        } else {
            // Même devise : pas de taux requis, le montant est déjà dans la devise de l'échéance
            if ($request->exchangeRate !== null || $request->originalAmount !== null || $request->originalCurrency !== null) {
                return [
                    'field' => 'exchangeRate',
                    'message' => 'Les champs de taux de change ne doivent pas être fournis pour un paiement dans la même devise que l\'échéance.',
                    'status' => 422,
                ];
            }
            $paymentAmountInRentCurrency = $request->amount;
        }

        // 4) Montant strictement positif (déjà validé par Assert\Regex dans le DTO)
        // 5) Montant ne doit pas dépasser le reste à payer (comparaison en devise de l'échéance)
        $due = $rent->getAmount();
        $paid = $this->paymentRepository->sumAmountByRent($rent);
        $remaining = bcsub($due, $paid, 2);

        if (bccomp($paymentAmountInRentCurrency, $remaining, 2) > 0) {
            return [
                'field' => 'amount',
                'message' => "Le montant du paiement ({$paymentAmountInRentCurrency}) dépasse le reste à payer ({$remaining}).",
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

        if ($filter->from !== null && $filter->to !== null && $filter->from > $filter->to) {
            return $feedback
                ->setErrorFlushDescription('La date de fin doit être égale ou postérieure à la date de début.')
                ->setStatus(422)
                ->autoInitFlush();
        }

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
            sortOrder: $filter->sortOrder,
            from: $filter->from,
            to: $filter->to,
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
     * Crée un paiement de montant NÉGATIF (`-montant`), relié à l'original par
     * `reversalOf`. Tous les agrégats (`SUM(amount)`) restent ainsi justes sans
     * avoir à filtrer les annulations : la contre-écriture se SOUSTRAIT au lieu
     * de s'additionner. Le paiement original n'est ni supprimé ni modifié
     * (comptabilité à écritures immuables).
     *
     * Une écriture ne peut être annulée qu'une seule fois : le contrôle est
     * doublé par la contrainte d'unicité sur `reversal_of_id`, qui tranche
     * même en cas d'annulations concurrentes (verrou pessimiste + 409).
     */
    public function cancelPayment(string $uuid, string $reason, User $currentUser): Feedback
    {
        $feedback = new Feedback();

        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            return $feedback
                ->setErrorFlushDescription('Identifiant de paiement invalide.')
                ->autoInitFlush()
                ->setStatus(400);
        }

        $payment = $this->paymentRepository->findOneByUuid($parsed);
        if ($payment === null) {
            return $feedback
                ->setErrorFlushDescription('Paiement introuvable.')
                ->autoInitFlush()
                ->setStatus(404);
        }

        $this->securityService->checkPaymentAccess($payment, SecurityAction::CANCEL_PAYMENT);

        // Toute la mutation comptable est atomique et verrouillée : deux
        // annulations simultanées du même paiement ne peuvent pas créer deux
        // contre-écritures.
        $success = $this->entityManager->wrapInTransaction(
            function () use ($payment, $reason, $currentUser, $feedback): bool {
                $locked = $this->paymentRepository->lockForUpdate($payment);
                if ($locked === null) {
                    $feedback
                        ->setErrorFlushDescription('Le paiement a été modifié ou supprimé par un autre processus.')
                        ->setStatus(409);

                    return false;
                }

                // Une contre-écriture n'est pas annulable, et une écriture déjà
                // annulée ne l'est pas deux fois (la contrainte UNIQUE sur
                // `reversal_of_id` garantit l'invariant au niveau base).
                if ($locked->isReversal()) {
                    $feedback
                        ->addError('amount', 'Cette écriture est déjà une contre-écriture et ne peut être annulée.')
                        ->setFlushDescriptionWithError('Annulation impossible : cette écriture est une contre-écriture.')
                        ->setStatus(409);

                    return false;
                }
                if ($locked->isReversed()) {
                    $feedback
                        ->addError('amount', 'Ce paiement a déjà été annulé.')
                        ->setFlushDescriptionWithError('Annulation impossible : ce paiement est déjà annulé.')
                        ->setStatus(409);

                    return false;
                }

                $rent = $locked->getRent();

                // Garde-fou : l'annulation ne doit pas faire passer le total payé
                // sous zéro. Avec une contre-écriture négative, c'est désormais
                // une sécurité et non plus un cas normal.
                $currentPaid = $this->paymentRepository->sumAmountByRent($rent);
                $cancelAmount = $locked->getAmount();
                $newPaid = bcsub($currentPaid, $cancelAmount, 2);

                if (bccomp($newPaid, '0.00', 2) < 0) {
                    $feedback
                        ->addError('amount', 'L\'annulation ferait passer le total payé en négatif.')
                        ->setFlushDescriptionWithError('Annulation impossible : montant total payé deviendrait négatif.')
                        ->setStatus(409);

                    return false;
                }

                // Contre-écriture : montant négatif, relié à l'original. On ne
                // recopie PAS `receiptNumber` (un numéro de reçu ne peut désigner
                // deux écritures).
                $correction = new Payment();
                $correction->setRent($rent);
                $correction->setCreatedBy($currentUser);
                $correction->setAmount(bcmul($cancelAmount, '-1', 2));
                $correction->setCurrency($locked->getCurrency());
                $correction->setPaymentDate($this->dateTime->now());
                $correction->setMethod($locked->getMethod());
                $correction->setReference(substr('ANNUL-' . ($locked->getReference() ?? $locked->getUuid()->toRfc4122()), 0, 100));
                $correction->setReversalOf($locked);
                $correction->setNotes("Annulation du paiement {$locked->getReference()} : {$reason}");

                $this->entityManager->persist($correction);
                $this->entityManager->flush();

                // Log d'audit : annulation du paiement
                $this->auditLogService->log(
                    action: 'CANCEL_PAYMENT',
                    entityType: Payment::class,
                    entityId: $locked->getId(),
                    organization: $rent->getLease()->getOrganization(),
                    user: $currentUser,
                    oldValues: [
                        'amount' => $locked->getAmount(),
                        'currency' => $locked->getCurrency()->value,
                        'exchangeRate' => $locked->getExchangeRate(),
                        'originalAmount' => $locked->getOriginalAmount(),
                        'originalCurrency' => $locked->getOriginalCurrency()?->value,
                        'reference' => $locked->getReference(),
                    ],
                    newValues: [
                        'amount' => $correction->getAmount(),
                        'currency' => $correction->getCurrency()->value,
                        'reference' => $correction->getReference(),
                        'notes' => $correction->getNotes(),
                        'reversalOfUuid' => $locked->getUuid()->toRfc4122(),
                    ],
                );

                // Recalcul du statut d'échéance : il peut désormais DESCENDRE
                // (PAID -> PARTIALLY_PAID -> PENDING) puisque la somme payée
                // diminue réellement.
                $this->refreshRentStatus($rent);
                $this->entityManager->flush();

                $feedback->setData($this->paymentMapper->toResponse($correction));

                return true;
            }
        );

        if (!$success) {
            // Tous les échecs de la transaction sont des conflits métier (409).
            if (!$feedback->hasErrors()) {
                $feedback->setErrorFlushDescription('Annulation impossible : conflit comptable (annulation concurrente ou donnée modifiée).');
            }

            return $feedback->autoInitFlush()->setStatus(409);
        }

        return $feedback
            ->setFlushDescription('Le paiement a été annulé par contre-écriture.')
            ->autoInitFlush()
            ->setStatus(201);
    }

    /**
     * Recalcule le statut de l'échéance à partir des paiements réellement
     * enregistrés.
     *
     * Le statut peut MONTER comme DESCENDRE : une contre-écriture négative
     * diminue la somme payée, donc une échéance PAID peut redevenir
     * PARTIALLY_PAID ou PENDING après annulation. La règle historique « le
     * statut ne descend jamais » masquait précisément le bug d'annulation.
     *
     * OVERDUE n'est jamais persisté : il est dérivé à la lecture par
     * `Rent::getComputedStatus()` selon la date. `Rent::syncStatus()` ne pose
     * donc que PENDING / PARTIALLY_PAID / PAID.
     */
    private function refreshRentStatus(Rent $rent): void
    {
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
