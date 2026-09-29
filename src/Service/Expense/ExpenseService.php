<?php

declare(strict_types=1);

namespace App\Service\Expense;

use App\Dto\Feedback;
use App\Dto\Request\Expense\ExpenseRequest;
use App\Entity\Expense\Expense;
use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Entity\Property\Building;
use App\Entity\Property\City;
use App\Entity\Property\Parcel;
use App\Entity\Property\Unit;
use App\Entity\Staff\Worker;
use App\Enum\ExpenseCategory;
use App\Mapper\Expense\ExpenseMapper;
use App\Repository\Expense\ExpenseRepository;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Property\BuildingRepository;
use App\Repository\Property\CityRepository;
use App\Repository\Property\ParcelRepository;
use App\Repository\Property\UnitRepository;
use App\Repository\Staff\WorkerRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\System\AuditLogService;
use App\Service\System\DateTimeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * ExpenseService
 *
 * Package : Expense Management
 *
 * Règles métier centrales :
 * - `cityUuid` est obligatoire ; elle déduit l'Organization (jamais fournie par le client).
 * - Au plus une cible parmi parcel, building, unit ; elle doit appartenir à la ville fournie
 *   et à l'Organization déduite.
 * - `worker` est autorisé seulement pour la catégorie SALARY, et doit appartenir à la même
 *   Organization que la dépense.
 * - Montant strictement positif, devise obligatoire, `createdBy` = utilisateur courant.
 * - Pas de suppression logique (l'entité est volontairement non supprimable) :
 *   correction par annulation tracée dans l'audit (hors périmètre P1).
 * - ADMIN_VILLE limité à ses villes (couvert par `checkCityAccess`).
 */
final readonly class ExpenseService
{
    public function __construct(
        private ExpenseRepository $expenseRepository,
        private CityRepository $cityRepository,
        private ParcelRepository $parcelRepository,
        private BuildingRepository $buildingRepository,
        private UnitRepository $unitRepository,
        private WorkerRepository $workerRepository,
        private ExpenseMapper $expenseMapper,
        private SecurityServiceInterface $securityService,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private AuditLogService $auditLogService,
        private DateTimeService $dateTime,
    ) {
    }

    /**
     * Crée une nouvelle dépense.
     */
    public function createExpense(ExpenseRequest $request, User $currentUser): Feedback
    {
        $feedback = new Feedback();

        $violations = $this->validator->validate($request, null, ['create']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setFlushDescriptionWithError('Les données de la dépense sont invalides.')
                ->autoInitFlush();
        }

        // 1) Résoudre la ville (obligatoire) et déduire l'Organization
        $city = $this->resolveCity($request->cityUuid, SecurityAction::CREATE_EXPENSE, $feedback);
        if ($city === null) {
            return $feedback->autoInitFlush();
        }
        $organization = $city->getOrganization();

        // 2) Vérifier l'accès à l'Organization pour CREATE_EXPENSE
        $this->securityService->checkOrganizationAccess($organization, SecurityAction::CREATE_EXPENSE);

        // 3) Résoudre la cible unique (parcel/building/unit) si fournie
        $target = $this->resolveUniqueTarget(
            $request->parcelUuid,
            $request->buildingUuid,
            $request->unitUuid,
            $city,
            $organization,
            $feedback
        );
        if ($target === null && ($request->parcelUuid || $request->buildingUuid || $request->unitUuid)) {
            return $feedback->autoInitFlush();
        }

        // 4) Résoudre le worker si fourni
        $worker = null;
        if ($request->workerUuid !== null) {
            $worker = $this->resolveWorker($request->workerUuid, $organization, $feedback);
            if ($worker === null) {
                return $feedback->autoInitFlush();
            }
        }

        // 5) Valider la cohérence worker <-> category SALARY
        $category = \App\Enum\ExpenseCategory::from($request->category);
        $categoryError = $this->validateWorkerCategoryConsistency($worker, $category);
        if ($categoryError !== null) {
            $feedback
                ->addError('workerUuid', $categoryError)
                ->setFlushDescriptionWithError($categoryError);

            return $feedback->autoInitFlush();
        }

        // 6) Créer l'entité
        $expense = new Expense();
        $expense->setOrganization($organization);
        $expense->setCity($city);
        $expense->setCreatedBy($currentUser);
        $this->expenseMapper->copyToEntity($request, $expense);

        // Positionner les relations résolues
        if ($target !== null) {
            if ($target instanceof Parcel) {
                $expense->setParcel($target);
            } elseif ($target instanceof Building) {
                $expense->setBuilding($target);
            } elseif ($target instanceof Unit) {
                $expense->setUnit($target);
            }
        }
        if ($worker !== null) {
            $expense->setWorker($worker);
        }

        $this->entityManager->persist($expense);
        $this->entityManager->flush();

        // Log d'audit : création de la dépense
        $this->auditLogService->log(
            action: 'CREATE_EXPENSE',
            entityType: Expense::class,
            entityId: $expense->getId(),
            organization: $organization,
            user: $currentUser,
            oldValues: null,
            newValues: [
                'category' => $expense->getCategory()->value,
                'amount' => $expense->getAmount(),
                'currency' => $expense->getCurrency()->value,
                'exchangeRate' => $expense->getExchangeRate(),
                'originalAmount' => $expense->getOriginalAmount(),
                'originalCurrency' => $expense->getOriginalCurrency()?->value,
                'expenseDate' => $this->dateTime->format($expense->getExpenseDate(), 'Y-m-d'),
                'cityUuid' => $city->getUuid()->toRfc4122(),
                'reference' => $expense->getReference(),
            ],
        );

        return $feedback
            ->setData($this->expenseMapper->toResponse($expense))
            ->setFlushDescription('La dépense a été créée avec succès.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    /**
     * Met à jour une dépense existante.
     *
     * L'Organization, la ville et le createdBy ne sont pas modifiables.
     * Seuls category, amount, currency, expenseDate, periodStart/End,
     * method, supplier, reference, receiptNumber, notes et la cible
     * (parcel/building/unit) et worker peuvent changer, sous réserve
     * des mêmes règles de cohérence.
     */
    public function updateExpense(string $uuid, ExpenseRequest $request, User $currentUser): Feedback
    {
        $feedback = new Feedback();

        $violations = $this->validator->validate($request, null, ['update']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setFlushDescriptionWithError('Les données de mise à jour sont invalides.')
                ->autoInitFlush();
        }

        $expense = $this->findExpense($uuid, $feedback);
        if ($expense === null) {
            return $feedback->autoInitFlush();
        }

        // Contrôle d'accès sur l'entité existante
        $this->securityService->checkExpenseAccess($expense, SecurityAction::UPDATE_EXPENSE);

        // Si cityUuid est fourni et différent, il faut revalider tout le périmètre
        if ($request->cityUuid !== null) {
            $newCity = $this->resolveCity($request->cityUuid, SecurityAction::UPDATE_EXPENSE, $feedback);
            if ($newCity === null) {
                return $feedback->autoInitFlush();
            }
            if ($newCity !== $expense->getCity()) {
                // Changement de ville = changement d'Organization potentiel
                // Il faut revalider la cible et le worker
                $expense->setCity($newCity);
                $this->securityService->checkOrganizationAccess($newCity->getOrganization(), SecurityAction::UPDATE_EXPENSE);
            }
        }

        // Résoudre la cible unique si l'une des UUIDs est fournie
        $hasTargetChange = $request->parcelUuid !== null || $request->buildingUuid !== null || $request->unitUuid !== null;
        if ($hasTargetChange) {
            $target = $this->resolveUniqueTarget(
                $request->parcelUuid,
                $request->buildingUuid,
                $request->unitUuid,
                $expense->getCity(),
                $expense->getOrganization(),
                $feedback
            );
            if ($target === null) {
                return $feedback->autoInitFlush();
            }
            // Nettoyer les anciennes cibles
            $expense->setParcel(null);
            $expense->setBuilding(null);
            $expense->setUnit(null);
            if ($target instanceof Parcel) {
                $expense->setParcel($target);
            } elseif ($target instanceof Building) {
                $expense->setBuilding($target);
            } elseif ($target instanceof Unit) {
                $expense->setUnit($target);
            }
        }

        // Résoudre worker si fourni
        if ($request->workerUuid !== null) {
            $worker = $this->resolveWorker($request->workerUuid, $expense->getOrganization(), $feedback);
            if ($worker === null) {
                return $feedback->autoInitFlush();
            }
            $expense->setWorker($worker);
        } elseif ($hasTargetChange && $expense->getWorker() !== null) {
            // Si on change la cible, on nettoie le worker (cohérence sera revalidée)
            $expense->setWorker(null);
        }

        // Valider la cohérence worker <-> category
        if ($request->category !== null) {
            $category = \App\Enum\ExpenseCategory::from($request->category);
            $workerForValidation = $expense->getWorker(); // peut être null
            $categoryError = $this->validateWorkerCategoryConsistency($workerForValidation, $category);
            if ($categoryError !== null) {
                $feedback
                    ->addError('workerUuid', $categoryError)
                    ->setFlushDescriptionWithError($categoryError);

                return $feedback->autoInitFlush();
            }
        }

        // Appliquer les changements scalaires via le mapper
        $this->expenseMapper->copyToEntity($request, $expense);

        $this->entityManager->flush();

        // Log d'audit : mise à jour de la dépense
        $this->auditLogService->log(
            action: 'UPDATE_EXPENSE',
            entityType: Expense::class,
            entityId: $expense->getId(),
            organization: $expense->getOrganization(),
            user: $currentUser,
            oldValues: null,
            newValues: [
                'category' => $expense->getCategory()->value,
                'amount' => $expense->getAmount(),
                'currency' => $expense->getCurrency()->value,
                'exchangeRate' => $expense->getExchangeRate(),
                'originalAmount' => $expense->getOriginalAmount(),
                'originalCurrency' => $expense->getOriginalCurrency()?->value,
                'expenseDate' => $this->dateTime->format($expense->getExpenseDate(), 'Y-m-d'),
            ],
        );

        return $feedback
            ->setData($this->expenseMapper->toResponse($expense))
            ->setFlushDescription('La dépense a été mise à jour avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function getExpenseByUuid(string $uuid): Feedback
    {
        $feedback = new Feedback();

        $expense = $this->findExpense($uuid, $feedback);
        if ($expense === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkExpenseAccess($expense, SecurityAction::VIEW_EXPENSE);

        return $feedback
            ->setData($this->expenseMapper->toResponse($expense))
            ->setFlushDescription('Dépense trouvée.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function listExpenses(
        ?array $cityIds = null,
        ?int $page = 1,
        ?int $limit = 20,
        ?string $sortBy = 'expenseDate',
        ?string $sortOrder = 'DESC'
    ): Feedback {
        $feedback = new Feedback();

        // Vérifier l'accès aux villes demandées (si ADMIN_VILLE)
        if ($cityIds !== null && !empty($cityIds)) {
            foreach ($cityIds as $cityId) {
                try {
                    $city = $this->cityRepository->findOneById($cityId);
                    if ($city !== null) {
                        $this->securityService->checkCityAccess($city, SecurityAction::VIEW_EXPENSE);
                    }
                } catch (\Throwable) {
                    // Ville introuvable : ignorer, le repository renverra liste vide
                }
            }
        }

        $result = $this->expenseRepository->findByFilters(
            cityIds: $cityIds,
            page: $page,
            limit: $limit,
            sortBy: $sortBy,
            sortOrder: $sortOrder
        );

        $items = array_map(
            fn (Expense $e) => $this->expenseMapper->toResponse($e),
            $result['items']
        );

        return $feedback
            ->setData(['items' => $items, 'total' => $result['total']])
            ->setFlushDescription('Liste des dépenses.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Annule une dépense par contre-écriture (traçabilité audit).
     *
     * Crée une dépense de correction avec la MÊME catégorie que l'originale
     * (pour préserver les agrégats par catégorie), même montant, même devise,
     * même taux de change. Le montant n'est PAS négatif (DECIMAL unsigned),
     * l'annulation est tracée par l'action CANCEL_EXPENSE et les notes.
     *
     * L'entité Expense n'est PAS supprimable (pas de soft delete).
     */
    public function cancelExpense(string $uuid, string $reason, User $currentUser): Feedback
    {
        $feedback = new Feedback();

        $expense = $this->findExpense($uuid, $feedback);
        if ($expense === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkExpenseAccess($expense, SecurityAction::DELETE_EXPENSE);

        // Créer la contre-écriture (même catégorie, même devise, même taux)
        $cancellation = new Expense();
        $cancellation->setOrganization($expense->getOrganization());
        $cancellation->setCity($expense->getCity());
        $cancellation->setCreatedBy($currentUser);
        $cancellation->setCategory($expense->getCategory()); // MÊME catégorie
        $cancellation->setAmount($expense->getAmount());
        $cancellation->setCurrency($expense->getCurrency());
        $cancellation->setExchangeRate($expense->getExchangeRate());
        $cancellation->setOriginalAmount($expense->getOriginalAmount());
        $cancellation->setOriginalCurrency($expense->getOriginalCurrency());
        $cancellation->setExpenseDate($this->dateTime->now());
        $cancellation->setReference('ANNUL-' . $expense->getReference());
        $cancellation->setNotes("Annulation de la dépense {$expense->getReference()} (catégorie: {$expense->getCategory()->value}) : {$reason}");

        // Copier la cible et le worker si présents
        $cancellation->setParcel($expense->getParcel());
        $cancellation->setBuilding($expense->getBuilding());
        $cancellation->setUnit($expense->getUnit());
        $cancellation->setWorker($expense->getWorker());

        $this->entityManager->persist($cancellation);
        $this->entityManager->flush();

        // Log d'audit : annulation de la dépense
        $this->auditLogService->log(
            action: 'CANCEL_EXPENSE',
            entityType: Expense::class,
            entityId: $expense->getId(),
            organization: $expense->getOrganization(),
            user: $currentUser,
            oldValues: [
                'category' => $expense->getCategory()->value,
                'amount' => $expense->getAmount(),
                'currency' => $expense->getCurrency()->value,
                'exchangeRate' => $expense->getExchangeRate(),
                'originalAmount' => $expense->getOriginalAmount(),
                'originalCurrency' => $expense->getOriginalCurrency()?->value,
                'reference' => $expense->getReference(),
            ],
            newValues: [
                'category' => $cancellation->getCategory()->value, // même catégorie
                'amount' => $cancellation->getAmount(),
                'currency' => $cancellation->getCurrency()->value,
                'exchangeRate' => $cancellation->getExchangeRate(),
                'reference' => $cancellation->getReference(),
                'notes' => $cancellation->getNotes(),
            ],
        );

        return $feedback
            ->setData($this->expenseMapper->toResponse($cancellation))
            ->setFlushDescription('La dépense a été annulée par contre-écriture.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    /**
     * Résout la ville obligatoire et vérifie l'accès.
     */
    private function resolveCity(?string $cityUuid, SecurityAction $action, Feedback $feedback): ?City
    {
        if ($cityUuid === null || $cityUuid === '') {
            $feedback
                ->addError('cityUuid', 'La ville est obligatoire.')
                ->setFlushDescriptionWithError('Le paramètre "cityUuid" est obligatoire.')
                ->setStatus(400);

            return null;
        }

        try {
            $parsed = Uuid::fromString($cityUuid);
        } catch (\InvalidArgumentException) {
            $feedback
                ->addError('cityUuid', 'UUID de ville invalide.')
                ->setFlushDescriptionWithError('Le paramètre "cityUuid" n\'est pas un UUID valide.')
                ->setStatus(400);

            return null;
        }

        $city = $this->cityRepository->findOneByUuid($parsed);
        if ($city === null) {
            $feedback
                ->setErrorFlushDescription('Ville introuvable.')
                ->setStatus(404);

            return null;
        }

        try {
            $this->securityService->checkCityAccess($city, $action);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            $feedback
                ->setErrorFlushDescription($e->getMessage())
                ->setStatus(403);

            return null;
        }

        return $city;
    }

    /**
     * Résout la cible unique (parcel/building/unit) si l'une est fournie.
     * Vérifie qu'elle appartient à la ville et à l'Organization.
     */
    private function resolveUniqueTarget(
        ?string $parcelUuid,
        ?string $buildingUuid,
        ?string $unitUuid,
        City $city,
        Organization $organization,
        Feedback $feedback
    ): Parcel|Building|Unit|null {
        $targetsProvided = array_filter([
            'parcel' => $parcelUuid,
            'building' => $buildingUuid,
            'unit' => $unitUuid,
        ], fn ($v) => $v !== null && $v !== '');

        if (count($targetsProvided) > 1) {
            $feedback
                ->addError('target', 'Une seule cible parmi parcel/building/unit est autorisée.')
                ->setFlushDescriptionWithError('Au plus une cible parmi parcel, building, unit peut être fournie.')
                ->setStatus(422);

            return null;
        }

        if ($targetsProvided === []) {
            return null; // aucune cible fournie : c'est autorisé
        }

        foreach ($targetsProvided as $type => $uuid) {
            try {
                $parsed = Uuid::fromString($uuid);
            } catch (\InvalidArgumentException) {
                $feedback
                    ->addError($type . 'Uuid', "UUID de {$type} invalide.")
                    ->setFlushDescriptionWithError("Le paramètre \"{$type}Uuid\" n'est pas un UUID valide.")
                    ->setStatus(400);

                return null;
            }

            switch ($type) {
                case 'parcel':
                    $target = $this->parcelRepository->findOneByUuid($parsed);
                    if ($target === null || $target->getCity() !== $city) {
                        $feedback
                            ->addError('parcelUuid', 'Parcelle introuvable ou n\'appartient pas à la ville.')
                            ->setFlushDescriptionWithError('La parcelle doit appartenir à la ville fournie.')
                            ->setStatus(422);
                        return null;
                    }
                    return $target;

                case 'building':
                    $target = $this->buildingRepository->findOneByUuid($parsed);
                    if ($target === null || $target->getParcel()->getCity() !== $city) {
                        $feedback
                            ->addError('buildingUuid', 'Immeuble introuvable ou n\'appartient pas à la ville.')
                            ->setFlushDescriptionWithError('L\'immeuble doit appartenir à la ville fournie.')
                            ->setStatus(422);
                        return null;
                    }
                    return $target;

                case 'unit':
                    $target = $this->unitRepository->findOneByUuid($parsed);
                    if ($target === null || $target->getBuilding()->getParcel()->getCity() !== $city) {
                        $feedback
                            ->addError('unitUuid', 'Unité introuvable ou n\'appartient pas à la ville.')
                            ->setFlushDescriptionWithError('L\'unité doit appartenir à la ville fournie.')
                            ->setStatus(422);
                        return null;
                    }
                    return $target;
            }
        }

        return null;
    }

    /**
     * Résout un worker et vérifie qu'il appartient à l'Organization.
     */
    private function resolveWorker(string $workerUuid, Organization $organization, Feedback $feedback): ?Worker
    {
        try {
            $parsed = Uuid::fromString($workerUuid);
        } catch (\InvalidArgumentException) {
            $feedback
                ->addError('workerUuid', 'UUID de travailleur invalide.')
                ->setFlushDescriptionWithError('Le paramètre "workerUuid" n\'est pas un UUID valide.')
                ->setStatus(400);

            return null;
        }

        $worker = $this->workerRepository->findOneByUuid($parsed);
        if ($worker === null || $worker->getOrganization() !== $organization) {
            $feedback
                ->addError('workerUuid', 'Travailleur introuvable ou n\'appartient pas à l\'organisation.')
                ->setFlushDescriptionWithError('Le travailleur doit appartenir à la même organisation que la dépense.')
                ->setStatus(422);

            return null;
        }

        return $worker;
    }

    /**
     * Valide la cohérence : worker requis pour SALARY, interdit pour les autres.
     */
    private function validateWorkerCategoryConsistency(?Worker $worker, \App\Enum\ExpenseCategory $category): ?string
    {
        if ($category === ExpenseCategory::SALARY) {
            if ($worker === null) {
                return 'Un travailleur est obligatoire pour une dépense de catégorie SALARY.';
            }
        } elseif ($worker !== null) {
            return 'Un travailleur ne doit être renseigné que pour les dépenses de catégorie SALARY.';
        }

        return null;
    }

    /**
     * Trouve une dépense par UUID.
     */
    private function findExpense(string $uuid, Feedback $feedback): ?Expense
    {
        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            $feedback
                ->addError('uuid', 'Identifiant de dépense invalide.')
                ->setFlushDescriptionWithError('L\'identifiant de la dépense n\'est pas un UUID valide.')
                ->setStatus(400);

            return null;
        }

        $expense = $this->expenseRepository->findOneByUuid($parsed);

        if ($expense === null) {
            $feedback
                ->setErrorFlushDescription('Dépense introuvable.')
                ->setStatus(404);
        }

        return $expense;
    }
}