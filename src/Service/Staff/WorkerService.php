<?php

declare(strict_types=1);

namespace App\Service\Staff;

use App\Dto\Feedback;
use App\Dto\Request\Staff\WorkerRequest;
use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Entity\Property\City;
use App\Entity\Staff\Worker;
use App\Mapper\Staff\WorkerMapper;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Property\CityRepository;
use App\Repository\Staff\WorkerRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\System\AuditLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * WorkerService
 *
 * Package : Staff Management
 *
 * Règles métier :
 * - L'Organization est déduite : pour PATRON/ADMIN_IMMOBILIER, c'est l'Organization
 *   du compte ; pour ADMIN_VILLE, c'est l'Organization de la ville qu'il gère.
 * - Pas de suppression logique : l'entité est volontairement non supprimable
 *   (traçabilité, liens vers WorkerAssignment). Correction par mise à jour.
 * - ADMIN_VILLE ne gère pas directement les Workers (uniquement via
 *   WorkerAssignment). Le contrôle `checkWorkerAccess` refuse l'accès à
 *   l'ADMIN_VILLE sur la fiche Worker, ce qui est cohérent.
 */
final readonly class WorkerService
{
    public function __construct(
        private WorkerRepository $workerRepository,
        private OrganizationRepository $organizationRepository,
        private CityRepository $cityRepository,
        private WorkerMapper $workerMapper,
        private SecurityServiceInterface $securityService,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private AuditLogService $auditLogService
    ) {
    }

    public function createWorker(WorkerRequest $request, User $currentUser): Feedback
    {
        $feedback = new Feedback();

        $violations = $this->validator->validate($request, null, ['create']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setFlushDescriptionWithError('Les données du travailleur sont invalides.')
                ->autoInitFlush();
        }

        // Déduire l'Organization selon le rôle
        $organization = $this->resolveOrganization($currentUser, $feedback);
        if ($organization === null) {
            return $feedback->autoInitFlush();
        }

        // Vérifier l'accès CREATE_WORKER sur l'Organization
        $this->securityService->checkOrganizationAccess($organization, SecurityAction::CREATE_WORKER);

        // Vérifier l'unicité nationalId par Organization si fourni
        if ($request->nationalId !== null && $request->nationalId !== '') {
            $existing = $this->workerRepository->findOneByNationalIdAndOrganization(
                $request->nationalId,
                $organization
            );
            if ($existing !== null) {
                return $feedback
                    ->addError('nationalId', 'Un travailleur avec ce numéro d\'identité existe déjà dans cette organisation.')
                    ->setFlushDescriptionWithError('Identité nationale déjà utilisée dans cette organisation.')
                    ->setStatus(409)
                    ->autoInitFlush();
            }
        }

        $worker = new Worker();
        $worker->setOrganization($organization);
        $this->workerMapper->copyToEntity($request, $worker);

        $this->entityManager->persist($worker);
        $this->entityManager->flush();

        // Log d'audit : création du travailleur
        $this->auditLogService->log(
            action: 'CREATE_WORKER',
            entityType: Worker::class,
            entityId: $worker->getId(),
            organization: $organization,
            user: $currentUser,
            oldValues: null,
            newValues: [
                'fullName' => $worker->getFullName(),
                'phone' => $worker->getPhone(),
                'email' => $worker->getEmail(),
                'nationalId' => $worker->getNationalId(),
            ],
        );

        return $feedback
            ->setData($this->workerMapper->toResponse($worker))
            ->setFlushDescription('Le travailleur a été créé avec succès.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    public function updateWorker(string $uuid, WorkerRequest $request, User $currentUser): Feedback
    {
        $feedback = new Feedback();

        $violations = $this->validator->validate($request, null, ['update']);
        if (count($violations) > 0) {
            return $feedback
                ->bind($violations)
                ->setFlushDescriptionWithError('Les données de mise à jour sont invalides.')
                ->autoInitFlush();
        }

        $worker = $this->findWorker($uuid, $feedback);
        if ($worker === null) {
            return $feedback->autoInitFlush();
        }

        // Contrôle d'accès : l'Organization du worker doit être accessible
        $this->securityService->checkWorkerAccess($worker, SecurityAction::UPDATE_WORKER);

        // Vérifier l'unicité nationalId si changé
        if ($request->nationalId !== null && $request->nationalId !== '' && $request->nationalId !== $worker->getNationalId()) {
            $existing = $this->workerRepository->findOneByNationalIdAndOrganization(
                $request->nationalId,
                $worker->getOrganization()
            );
            if ($existing !== null) {
                return $feedback
                    ->addError('nationalId', 'Un travailleur avec ce numéro d\'identité existe déjà dans cette organisation.')
                    ->setFlushDescriptionWithError('Identité nationale déjà utilisée dans cette organisation.')
                    ->setStatus(409)
                    ->autoInitFlush();
            }
        }

        $this->workerMapper->copyToEntity($request, $worker);

        $this->entityManager->flush();

        // Log d'audit : mise à jour du travailleur
        $this->auditLogService->log(
            action: 'UPDATE_WORKER',
            entityType: Worker::class,
            entityId: $worker->getId(),
            organization: $worker->getOrganization(),
            user: $currentUser,
            oldValues: null,
            newValues: [
                'fullName' => $worker->getFullName(),
                'phone' => $worker->getPhone(),
                'email' => $worker->getEmail(),
                'nationalId' => $worker->getNationalId(),
                'address' => $worker->getAddress(),
                'notes' => $worker->getNotes(),
            ],
        );

        return $feedback
            ->setData($this->workerMapper->toResponse($worker))
            ->setFlushDescription('Le travailleur a été mis à jour avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function getWorkerByUuid(string $uuid): Feedback
    {
        $feedback = new Feedback();

        $worker = $this->findWorker($uuid, $feedback);
        if ($worker === null) {
            return $feedback->autoInitFlush();
        }

        $this->securityService->checkWorkerAccess($worker, SecurityAction::VIEW_WORKER);

        return $feedback
            ->setData($this->workerMapper->toResponse($worker))
            ->setFlushDescription('Travailleur trouvé.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function listWorkers(?Uuid $organizationId = null, int $page = 1, int $limit = 20): Feedback
    {
        $feedback = new Feedback();

        $orgs = $this->securityService->getCurrentUserOrganizations();
        if ($organizationId !== null) {
            // Vérifier que l'utilisateur a accès à cette organization
            $targetOrg = $this->organizationRepository->findOneByUuid($organizationId);
            if ($targetOrg === null || !$this->securityService->canAccessOrganization($targetOrg, SecurityAction::VIEW_WORKER)) {
                return $feedback
                    ->addError('organizationId', 'Organisation non accessible.')
                    ->setFlushDescriptionWithError('Vous n\'avez pas accès à cette organisation.')
                    ->setStatus(403)
                    ->autoInitFlush();
            }
            $orgs = [$targetOrg];
        }

        $items = [];
        $total = 0;
        foreach ($orgs as $org) {
            if (!$this->securityService->canAccessOrganization($org, SecurityAction::VIEW_WORKER)) {
                continue;
            }
            $result = $this->workerRepository->findByOrganization($org->getUuid());
            $total += count($result);
            $items = array_merge($items, $result);
        }

        // Pagination manuelle (simple)
        $offset = ($page - 1) * $limit;
        $paginated = array_slice($items, $offset, $limit);

        $data = array_map(fn (Worker $w) => $this->workerMapper->toResponse($w), $paginated);

        return $feedback
            ->setData(['items' => $data, 'total' => $total])
            ->setFlushDescription('Liste des travailleurs.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Déduit l'Organization selon le rôle de l'utilisateur.
     * - PATRON / ADMIN_IMMOBILIER : leurs Organizations accessibles
     * - SUPER_ADMIN : nécessite organizationUuid en paramètre (non géré ici, à voir)
     * - ADMIN_VILLE : ne peut PAS créer de Worker directement (refusé par checkWorkerAccess)
     */
    private function resolveOrganization(User $currentUser, Feedback $feedback): ?Organization
    {
        $orgs = $this->securityService->getCurrentUserOrganizations();

        if ($orgs === []) {
            $feedback
                ->setErrorFlushDescription('Aucune organisation accessible.')
                ->setStatus(403);

            return null;
        }

        // Pour ADMIN_VILLE, checkWorkerAccess refusera l'accès (cf. SecurityService)
        // On prend la première Organization accessible pour PATRON/ADMIN_IMMOBILIER
        // SUPER_ADMIN n'est pas géré ici (nécessiterait un paramètre explicite)

        // Le contrôle `checkWorkerAccess` qui suit validera l'accès réel
        return $orgs[0];
    }

    private function findWorker(string $uuid, Feedback $feedback): ?Worker
    {
        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            $feedback
                ->addError('uuid', 'Identifiant de travailleur invalide.')
                ->setFlushDescriptionWithError('L\'identifiant du travailleur n\'est pas un UUID valide.')
                ->setStatus(400);

            return null;
        }

        $worker = $this->workerRepository->findOneByUuid($parsed);
        if ($worker === null) {
            $feedback
                ->setErrorFlushDescription('Travailleur introuvable.')
                ->setStatus(404);
        }

        return $worker;
    }
}