<?php

declare(strict_types=1);

namespace App\Service\Property;

use App\Dto\Response\Property\PublicListingResponse;
use App\Dto\Response\System\PublicShowcaseResponse;
use App\Entity\Identity\Organization;
use App\Enum\Currency;
use App\Enum\UnitType;
use App\Mapper\Property\UnitMapper;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Property\CityRepository;
use App\Repository\Property\UnitRepository;
use App\Service\System\FileUploadService;

/**
 * Lecture de la vitrine publique, sans authentification.
 *
 * ⚠ Cette service est atteinte par des routes `PUBLIC_ACCESS`, donc par des
 * visiteurs non authentifiés. Elle ne fait donc AUCUN contrôle d'accès, et sa
 * seule garantie de non-divulgation est structurelle : tout ce qu'elle expose
 * vient de `UnitRepository::findPublishedAndVacant()` ou
 * `findPublishedAndVacantByUuid()`, qui bornent l'agrégat **en SQL** sur
 * l'Organization demandée, `is_published = true` et l'absence de bail `ACTIVE`.
 *
 * Conséquence à respecter : ne jamais accepter dans cette service une liste
 * d'identifiants venue du client sans avoir vérifié leur appartenance. Un
 * filtrage appliqué après coup laisserait fuiter l'existence des ressources,
 * alors qu'un `WHERE` ne les charge pas.
 */
final readonly class PublicShowcaseService
{
    public function __construct(
        private UnitRepository $unitRepository,
        private CityRepository $cityRepository,
        private OrganizationRepository $organizationRepository,
        private UnitPhotoService $unitPhotoService,
        private UnitMapper $unitMapper,
        private FileUploadService $fileUploadService,
    ) {
    }

    /**
     * Retrouve une entreprise par son slug de vitrine.
     *
     * Une Organization masquée (`is_publicly_listed = false`) ou supprimée est
     * traitée comme inexistante : le contrôle est fait ici, au seul endroit qui
     * connaît la règle de visibilité, plutôt que dans chaque appelant.
     */
    public function findListedOrganizationBySlug(string $slug): ?Organization
    {
        $organization = $this->organizationRepository->findOneBySlug($slug);

        if ($organization === null || $organization->isPubliclyHidden()) {
            return null;
        }

        return $organization;
    }

    /**
     * Présentation publique d'une entreprise.
     *
     * Le nombre d'unités disponibles vient de la requête bornée, pas d'un
     * décompte global : afficher « 12 disponibles » alors que six sont louées
     * ferait mentir le visiteur, et compter le parc entier révélerait son
     * volume à un tiers.
     */
    public function getShowcase(Organization $organization): PublicShowcaseResponse
    {
        $available = $this->unitRepository->findPublishedAndVacant($organization, [], 1, 1);

        return new PublicShowcaseResponse(
            name: $organization->getName(),
            logo: $organization->getLogo() !== null
                ? $this->fileUploadService->getPublicUrl($organization->getLogo())
                : null,
            description: $organization->getPublicDescription(),
            address: $organization->getAddress(),
            city: $organization->getCity(),
            country: $organization->getCountry(),
            email: $organization->getEmail(),
            phone: $organization->getPhone(),
            availableUnitsCount: $available['total'],
        );
    }

    /**
     * Annonces disponibles d'une entreprise, filtrées et paginées.
     *
     * @param list<string>|null $cityCodes villes retenues, résolues par code
     *
     * @return array{items: list<PublicListingResponse>, total: int, page: int, pages: int}
     */
    public function listListings(
        Organization $organization,
        ?array $cityCodes = null,
        ?UnitType $type = null,
        ?int $minBedrooms = null,
        ?string $minRent = null,
        ?string $maxRent = null,
        ?Currency $currency = null,
        int $page = 1,
        int $limit = 20
    ): array {
        // Une borne de loyer sans devise comparerait deux monnaies (§ Argent) :
        // elle est donc ignorée, et le résultat reste cohérent.
        if ($currency === null) {
            $minRent = null;
            $maxRent = null;
        }

        $cities = $this->resolveCities($organization, $cityCodes);

        $result = $this->unitRepository->findPublishedAndVacant(
            $organization,
            $cities,
            $page,
            $limit,
            $type,
            $minBedrooms,
            $minRent,
            $maxRent,
            $currency
        );

        $items = [];

        foreach ($result['items'] as $unit) {
            $items[] = $this->unitMapper->toPublicListing(
                $unit,
                $this->unitPhotoService->listForUnit($unit)
            );
        }

        return [
            'items' => $items,
            'total' => $result['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($result['total'] / max(1, $limit))),
        ];
    }

    /**
     * Détail d'une annonce, à deux conditions simultanées.
     *
     * Une 404 — et non une 403 — si l'unité n'est pas publiée, occupée, ou
     * appartient à une autre entreprise : répondre « interdit » confirmerait
     * au visiteur que l'UUID existe et se trouve ailleurs, ce qui suffit à
     * énumérer le parc des concurrents à partir d'une liste de détails fuite.
     */
    public function getListing(Organization $organization, string $unitUuid): ?PublicListingResponse
    {
        try {
            $uuid = \Symfony\Component\Uid\Uuid::fromString($unitUuid);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $unit = $this->unitRepository->findPublishedAndVacantByUuid($uuid, $organization);

        if ($unit === null) {
            return null;
        }

        return $this->unitMapper->toPublicListing(
            $unit,
            $this->unitPhotoService->listForUnit($unit)
        );
    }

    /**
     * Résout des codes de ville en entités bornées à l'Organization.
     *
     * Les codes sont résolus, jamais utilisés tels quels dans le `WHERE` : une
     * liste de codes invalide doit produire une liste vide, pas une requête
     * qui ignorerait silencieusement le filtre.
     *
     * @param list<string>|null $cityCodes
     *
     * @return list<\App\Entity\Property\City>
     */
    private function resolveCities(Organization $organization, ?array $cityCodes): array
    {
        if ($cityCodes === null || $cityCodes === []) {
            return [];
        }

        $cities = [];

        foreach ($cityCodes as $code) {
            $city = $this->cityRepository->findOneByOrganizationAndCode($organization, $code);

            if ($city !== null) {
                $cities[] = $city;
            }
        }

        return $cities;
    }
}