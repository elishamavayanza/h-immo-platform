<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\EntityHydrator;
use App\Api\ResourceDefinition;
use App\Api\ResourceQueryBuilder;
use App\Dto\Request\PaginationQuery;
use App\Dto\Response\PaginatedResponse;
use App\Trait\FeedbackTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * AbstractResourceController
 *
 * Package : Controller\Api (socle commun)
 *
 * Porte, une seule fois, les 5 actions CRUD génériques (index, show,
 * create, update, delete) communes à toutes les ressources REST de
 * Soft-IMMO. Chaque ressource a SA PROPRE classe de contrôleur (ex.
 * `UnitController`, `LeaseController`, ...), qui hérite de celle-ci et
 * ne fait que déclarer :
 *   - le préfixe de route (`#[Route('/api/units', name: 'api_unit_')]`
 *     sur la classe fille) ;
 *   - sa `ResourceDefinition` (entité + DTO request/response + champs
 *     de recherche/tri), via `definition()`.
 *
 * Cette classe est abstraite : Symfony ignore les classes abstraites
 * lors du chargement des routes par attributs, elle n'est donc jamais
 * elle-même routée. Chaque responsabilité (une ressource = une classe)
 * reste ainsi séparée, sans dupliquer 13 fois la même logique CRUD.
 *
 * Point d'extension : `afterHydrate()` est un hook (no-op par défaut)
 * que certaines ressources redéfinissent pour une dérivation de champ
 * purement mécanique (ex. `LeaseController` recopie l'organisation du
 * locataire). Ce n'est jamais une règle métier décisionnelle : celles-ci
 * restent hors du contrôleur, dans une couche `Service/` dédiée.
 */
abstract class AbstractResourceController extends AbstractController
{
    use FeedbackTrait;

    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly ResourceQueryBuilder $queryBuilder,
        protected readonly EntityHydrator $hydrator,
        protected readonly SerializerInterface $serializer,
        protected readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Déclare la ressource gérée par ce contrôleur (entité, DTO,
     * champs de recherche/tri). Seule méthode qu'une classe fille
     * DOIT fournir.
     */
    abstract protected function definition(): ResourceDefinition;

    /**
     * Point d'extension optionnel, appelé juste après l'hydratation
     * de l'entité à partir du DTO de requête, avant persist()/flush().
     * No-op par défaut. À redéfinir uniquement pour une dérivation
     * mécanique de champ (jamais une décision métier).
     */
    protected function afterHydrate(object $entity, object $requestDto, Request $request, bool $isCreate): void
    {
        // Rien par défaut.
    }

    /**
     * GET /api/{prefix}
     */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $definition = $this->definition();

        $paginationQuery = new PaginationQuery(
            page: max(1, (int) $request->query->get('page', 1)),
            limit: (int) $request->query->get('limit', 10),
            search: $request->query->get('search'),
            sortBy: $request->query->get('sortBy', 'createdAt'),
            sortOrder: $request->query->get('sortOrder', 'DESC'),
        );

        $violations = $this->validator->validate($paginationQuery);

        if (count($violations) > 0) {
            return $this->respondWithViolations($violations, 'Paramètres de pagination invalides.');
        }

        $result = $this->queryBuilder->paginate($definition, $paginationQuery);

        $responseDtoClass = $definition->responseDtoClass;
        $items = array_map(
            static fn (object $entity): object => $responseDtoClass::fromEntity($entity),
            $result['items']
        );

        $paginated = PaginatedResponse::create(
            items: $items,
            page: $paginationQuery->page,
            limit: $paginationQuery->limit,
            totalItems: $result['totalItems'],
        );

        return $this->respondSuccess($paginated, 'Liste récupérée avec succès.');
    }

    /**
     * GET /api/{prefix}/{uuid}
     */
    #[Route('/{uuid}', name: 'show', methods: ['GET'])]
    public function show(string $uuid): JsonResponse
    {
        $definition = $this->definition();
        $entity = $this->findEntityOrFail($definition, $uuid);

        $responseDtoClass = $definition->responseDtoClass;

        return $this->respondSuccess($responseDtoClass::fromEntity($entity), 'Ressource récupérée avec succès.');
    }

    /**
     * POST /api/{prefix}
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $definition = $this->definition();

        if ($definition->requestDtoClass === null) {
            throw new MethodNotAllowedHttpException(['GET'], sprintf(
                'La ressource "%s" est en lecture seule.',
                $definition->slug
            ));
        }

        $requestDto = $this->serializer->deserialize(
            $request->getContent(),
            $definition->requestDtoClass,
            'json'
        );

        $violations = $this->validator->validate($requestDto, null, ['create']);

        if (count($violations) > 0) {
            return $this->respondWithViolations($violations, 'Les données soumises sont invalides.');
        }

        $entityClass = $definition->entityClass;
        /** @var object $entity */
        $entity = new $entityClass();

        $this->hydrator->hydrate($requestDto, $entity);
        $this->afterHydrate($entity, $requestDto, $request, isCreate: true);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $responseDtoClass = $definition->responseDtoClass;

        return $this->respondSuccess(
            $responseDtoClass::fromEntity($entity),
            'Ressource créée avec succès.',
            Response::HTTP_CREATED
        );
    }

    /**
     * PUT/PATCH /api/{prefix}/{uuid}
     *
     * Mise à jour partielle : une propriété du DTO restée à `null`
     * (donc non transmise) est ignorée par `EntityHydrator` — la
     * valeur actuelle de l'entité est conservée.
     */
    #[Route('/{uuid}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(string $uuid, Request $request): JsonResponse
    {
        $definition = $this->definition();

        if ($definition->requestDtoClass === null) {
            throw new MethodNotAllowedHttpException(['GET'], sprintf(
                'La ressource "%s" est en lecture seule.',
                $definition->slug
            ));
        }

        $entity = $this->findEntityOrFail($definition, $uuid);

        $requestDto = $this->serializer->deserialize(
            $request->getContent(),
            $definition->requestDtoClass,
            'json'
        );

        $violations = $this->validator->validate($requestDto, null, ['update']);

        if (count($violations) > 0) {
            return $this->respondWithViolations($violations, 'Les données soumises sont invalides.');
        }

        $this->hydrator->hydrate($requestDto, $entity);
        $this->afterHydrate($entity, $requestDto, $request, isCreate: false);

        $this->entityManager->flush();

        $responseDtoClass = $definition->responseDtoClass;

        return $this->respondSuccess($responseDtoClass::fromEntity($entity), 'Ressource mise à jour avec succès.');
    }

    /**
     * DELETE /api/{prefix}/{uuid}
     *
     * Suppression logique (`deletedAt`) si l'entité hérite de
     * `SoftDeletableEntity` (détecté par réflexion) ; suppression
     * physique sinon. Refusée (405) si `ResourceDefinition::$deletable`
     * est à `false` (ex. `payments`, `audit-logs`).
     */
    #[Route('/{uuid}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $uuid): JsonResponse
    {
        $definition = $this->definition();

        if (!$definition->deletable) {
            throw new MethodNotAllowedHttpException(['GET'], sprintf(
                'La suppression n\'est pas autorisée pour la ressource "%s".',
                $definition->slug
            ));
        }

        $entity = $this->findEntityOrFail($definition, $uuid);

        if (method_exists($entity, 'setDeletedAt')) {
            $entity->setDeletedAt(new \DateTimeImmutable());
        } else {
            $this->entityManager->remove($entity);
        }

        $this->entityManager->flush();

        return $this->respondSuccess(null, 'Ressource supprimée avec succès.');
    }

    /**
     * Résout l'entité correspondant à l'UUID demandé, ou lève une 404.
     */
    private function findEntityOrFail(ResourceDefinition $definition, string $uuid): object
    {
        if (!Uuid::isValid($uuid)) {
            throw new NotFoundHttpException(sprintf('Identifiant "%s" invalide.', $uuid));
        }

        $entity = $this->entityManager
            ->getRepository($definition->entityClass)
            ->findOneBy(['uuid' => Uuid::fromString($uuid)]);

        if ($entity === null) {
            throw new NotFoundHttpException(sprintf(
                'Ressource introuvable pour l\'identifiant "%s".',
                $uuid
            ));
        }

        return $entity;
    }
}
