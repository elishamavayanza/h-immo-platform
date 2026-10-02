<?php

declare(strict_types=1);

namespace App\Controller\Api\Public;

use App\Dto\Feedback;
use App\Dto\Request\PaginationQuery;
use App\Dto\Response\Property\PublicListingResponse;
use App\Dto\Response\System\PublicShowcaseResponse;
use App\Service\Property\PublicShowcaseService;
use App\Trait\FeedbackTrait;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PublicShowcaseController
 *
 * Package : Public — Vitrine immobilière sans authentification.
 *
 * Endpoints accessibles sans jeton : la seule protection est le bornage
 * structurel des requêtes (Organization → is_published + pas de bail ACTIVE).
 * Aucune donnée de gestion n'est exposée, ni identifiants internes.
 *
 * Endpoints :
 * - GET /api/public/organizations/{slug}/showcase      : présentation d'une entreprise
 * - GET /api/public/organizations/{slug}/listings      : annonces disponibles (paginées, filtrées)
 * - GET /api/public/organizations/{slug}/listings/{uuid} : détail d'une annonce
 */
#[Route('/api/public', name: 'api_public_')]
#[OA\Tag(name: 'Public Showcase', description: 'Vitrine immobilière publique, sans authentification.')]
final class PublicShowcaseController extends AbstractController
{
    use FeedbackTrait;

    public function __construct(
        private readonly PublicShowcaseService $publicShowcaseService,
    ) {
    }

    #[Route('/organizations/{slug}/showcase', name: 'showcase', methods: ['GET'])]
    #[OA\Get(
        summary: 'Présentation publique d\'une entreprise',
        description: 'Retourne les informations affichées sur la page d\'accueil de la vitrine (logo, description, coordonnées, nombre d\'unités disponibles).'
    )]
    #[OA\Parameter(name: 'slug', description: 'Slug de la vitrine (ex: immo-plus)', in: 'path', required: true)]
    #[OA\Response(
        response: 200,
        description: 'Vitrine trouvée',
        content: new OA\JsonContent(ref: new Model(type: PublicShowcaseResponse::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Entreprise introuvable, vitrine masquée, ou slug non attribué',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    public function showcase(string $slug): JsonResponse
    {
        $organization = $this->publicShowcaseService->findListedOrganizationBySlug($slug);

        if ($organization === null) {
            return $this->json(
                (new Feedback())
                    ->setFlushDescription('Entreprise introuvable ou vitrine non publiée.')
                    ->setStatus(404)
                    ->autoInitFlush(),
                404
            );
        }

        $showcase = $this->publicShowcaseService->getShowcase($organization);

        return $this->json(
            (new Feedback())
                ->setData($showcase)
                ->setStatus(200)
                ->autoInitFlush(),
            200
        );
    }

    #[Route('/organizations/{slug}/listings', name: 'listings', methods: ['GET'])]
    #[OA\Get(
        summary: 'Lister les annonces disponibles d\'une entreprise',
        description: 'Retourne les unités publiées et libres, avec filtres optionnels. Une unité louée disparaît automatiquement de la liste sans action manuelle.'
    )]
    #[OA\Parameter(name: 'slug', description: 'Slug de la vitrine', in: 'path', required: true)]
    #[OA\Parameter(name: 'city', description: 'Filtrer par code ville (ex: KIN)', in: 'query')]
    #[OA\Parameter(name: 'type', description: 'Type d\'unité (apartment, office, commercial)', in: 'query', schema: new OA\Schema(type: 'string', enum: ['apartment', 'office', 'commercial']))]
    #[OA\Parameter(name: 'bedrooms', description: 'Nombre minimum de chambres', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 0))]
    #[OA\Parameter(name: 'minRent', description: 'Loyer minimum', in: 'query', schema: new OA\Schema(type: 'string', format: 'decimal'))]
    #[OA\Parameter(name: 'maxRent', description: 'Loyer maximum', in: 'query', schema: new OA\Schema(type: 'string', format: 'decimal'))]
    #[OA\Parameter(name: 'currency', description: 'Devise (USD, CDF)', in: 'query', schema: new OA\Schema(type: 'string', enum: ['USD', 'CDF']))]
    #[OA\Parameter(name: 'page', description: 'Numéro de page (1-indexé)', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1))]
    #[OA\Parameter(name: 'limit', description: 'Éléments par page (max 100)', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20))]
    #[OA\Response(
        response: 200,
        description: 'Liste paginée des annonces',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Entreprise introuvable ou vitrine masquée',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    public function listings(
        string $slug,
        #[MapQueryString] ?PaginationQuery $query = null,
        ?string $city = null,
        ?string $type = null,
        ?int $bedrooms = null,
        ?string $minRent = null,
        ?string $maxRent = null,
        ?string $currency = null
    ): JsonResponse {
        $organization = $this->publicShowcaseService->findListedOrganizationBySlug($slug);

        if ($organization === null) {
            return $this->json(
                (new Feedback())
                    ->setFlushDescription('Entreprise introuvable ou vitrine non publiée.')
                    ->setStatus(404)
                    ->autoInitFlush(),
                404
            );
        }

        $page = $query->page ?? 1;
        $limit = min($query->limit ?? 20, 100);

        $unitType = $type !== null ? \App\Enum\UnitType::tryFrom($type) : null;
        $currencyEnum = $currency !== null ? \App\Enum\Currency::tryFrom($currency) : null;

        // Valeur invalide = pas de filtre (même comportement que city inconnu)
        // Un 400/422 serait plus strict, mais l'API publique privilégie la robustesse :
        // un bot qui essaie des paramètres au hasard ne doit pas provoquer de 500.
        $result = $this->publicShowcaseService->listListings(
            $organization,
            $city !== null ? [$city] : null,
            $unitType,
            $bedrooms,
            $minRent,
            $maxRent,
            $currencyEnum,
            $page,
            $limit
        );

        return $this->json(
            (new Feedback())
                ->setData($result)
                ->setStatus(200)
                ->autoInitFlush(),
            200
        );
    }

    #[Route('/organizations/{slug}/listings/{uuid}', name: 'listing_detail', methods: ['GET'])]
    #[OA\Get(
        summary: 'Détail d\'une annonce publique',
        description: 'Retourne les informations complètes d\'une unité publiée et libre. 404 si l\'unité n\'est pas publiée, occupée, ou appartient à une autre entreprise.'
    )]
    #[OA\Parameter(name: 'slug', description: 'Slug de la vitrine', in: 'path', required: true)]
    #[OA\Parameter(name: 'uuid', description: 'UUID public de l\'unité', in: 'path', required: true)]
    #[OA\Response(
        response: 200,
        description: 'Annonce trouvée',
        content: new OA\JsonContent(ref: new Model(type: PublicListingResponse::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Annonce introuvable, non publiée, occupée, ou UUID invalide',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    public function listingDetail(string $slug, string $uuid): JsonResponse
    {
        $organization = $this->publicShowcaseService->findListedOrganizationBySlug($slug);

        if ($organization === null) {
            return $this->json(
                (new Feedback())
                    ->setFlushDescription('Entreprise introuvable ou vitrine non publiée.')
                    ->setStatus(404)
                    ->autoInitFlush(),
                404
            );
        }

        $listing = $this->publicShowcaseService->getListing($organization, $uuid);

        if ($listing === null) {
            return $this->json(
                (new Feedback())
                    ->setFlushDescription('Annonce introuvable ou non disponible.')
                    ->setStatus(404)
                    ->autoInitFlush(),
                404
            );
        }

        return $this->json(
            (new Feedback())
                ->setData($listing)
                ->setStatus(200)
                ->autoInitFlush(),
            200
        );
    }
}