<?php

declare(strict_types=1);

namespace App\Service\Property;

use App\Dto\Request\Property\ListingDraftRequest;
use App\Dto\Request\Property\ListingCandidateFilterRequest;
use App\Dto\Request\Property\ListingFilterRequest;
use App\Entity\Identity\Organization;
use App\Entity\Property\City;
use App\Entity\Property\Listing;
use App\Entity\Property\ListingHistory;
use App\Entity\Property\Unit;
use App\Enum\CityStatus;
use App\Enum\Currency;
use App\Enum\ListingOfferType;
use App\Enum\ListingStatus;
use App\Enum\OrganizationStatus;
use App\Enum\OrganizationRole;
use App\Enum\UnitOperationalStatus;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Property\ListingRepository;
use App\Repository\Property\UnitPhotoRepository;
use App\Repository\Property\UnitRepository;
use App\Repository\Rental\LeaseRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\Property\UnitPhotoService;
use App\Service\System\DateTimeService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Règles serveur du cycle de vie éditorial de la vitrine immobilière. */
final readonly class ListingWorkflowService
{
    public function __construct(
        private ListingRepository $listings,
        private UnitRepository $units,
        private UnitPhotoRepository $unitPhotos,
        private UnitPhotoService $unitPhotoService,
        private OrganizationRepository $organizations,
        private LeaseRepository $leases,
        private SecurityServiceInterface $security,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private DateTimeService $dateTime,
    ) {
    }

    public function createDraft(string $unitUuid, ListingDraftRequest $request): array
    {
        $unit = $this->findUnit($unitUuid);
        $this->security->checkUnitAccess($unit, SecurityAction::CREATE_LISTING);
        $organization = $unit->getBuilding()->getParcel()->getCity()->getOrganization();
        $this->assertShowcaseEnabled($organization);
        $this->validate($request);

        return $this->entityManager->wrapInTransaction(function () use ($unit, $request): array {
            $this->units->lockForUpdate($unit);
            $this->security->checkUnitAccess($unit, SecurityAction::CREATE_LISTING);
            $this->assertUnitEligible($unit, requirePhoto: false);
            $listing = $this->listings->findOneByUnit($unit);
            if ($listing === null) {
                $listing = (new Listing())->setUnit($unit)->setCreatedBy($this->security->getCurrentUser());
                $this->entityManager->persist($listing);
                $unit->setListing($listing);
                $oldStatus = null;
            } elseif (!in_array($listing->getStatus(), [ListingStatus::WITHDRAWN, ListingStatus::REJECTED], true)) {
                throw new ConflictHttpException('Une annonce active existe déjà pour cette unité.');
            } else {
                $oldStatus = $listing->getStatus()->value;
                $listing->setCreatedBy($this->security->getCurrentUser())->setPublishedAt(null);
            }

            $unit->setIsPublished(false);
            $this->copyDraft($request, $listing)->setStatus(ListingStatus::DRAFT)->setUpdatedAt($this->dateTime->now());
            $this->record($listing, 'DRAFT_CREATED', $oldStatus, ListingStatus::DRAFT, null, null, $this->content($listing));
            $this->entityManager->flush();

            return $this->serialize($listing, true);
        });
    }

    public function updateDraft(string $uuid, ListingDraftRequest $request): array
    {
        $this->validate($request);

        return $this->locked($uuid, function (Listing $listing) use ($request): array {
            $this->security->checkUnitAccess($listing->getUnit(), SecurityAction::UPDATE_LISTING);
            if (!in_array($listing->getStatus(), [ListingStatus::DRAFT, ListingStatus::CORRECTIONS_REQUESTED], true)) {
                throw new ConflictHttpException('Seuls un brouillon ou une annonce renvoyée en correction peuvent être modifiés.');
            }
            $old = $this->content($listing);
            $status = $listing->getStatus();
            $this->copyDraft($request, $listing)->setUpdatedAt($this->dateTime->now());
            $this->record($listing, 'CONTENT_UPDATED', $status->value, $status, null, $old, $this->content($listing));
            $this->entityManager->flush();

            return $this->serialize($listing, true);
        });
    }

    public function submit(string $uuid): array
    {
        return $this->locked($uuid, function (Listing $listing): array {
            $this->security->checkUnitAccess($listing->getUnit(), SecurityAction::SUBMIT_LISTING);
            if (!in_array($listing->getStatus(), [ListingStatus::DRAFT, ListingStatus::CORRECTIONS_REQUESTED], true)) {
                throw new ConflictHttpException('Seul un brouillon ou une annonce corrigée peut être soumis.');
            }
            $this->assertUnitEligible($listing->getUnit(), requirePhoto: true);
            $this->transition($listing, 'SUBMITTED', ListingStatus::PENDING_REVIEW);

            return $this->serialize($listing, true);
        });
    }

    public function requestCorrections(string $uuid, string $reason): array
    {
        return $this->reviewTransition($uuid, 'CORRECTIONS_REQUESTED', ListingStatus::CORRECTIONS_REQUESTED, $reason, [ListingStatus::PENDING_REVIEW]);
    }

    public function queuePublication(string $uuid): array
    {
        return $this->reviewTransition($uuid, 'APPROVED_FOR_PUBLICATION', ListingStatus::PENDING_PUBLICATION, null, [ListingStatus::PENDING_REVIEW]);
    }

    public function reject(string $uuid, string $reason): array
    {
        return $this->reviewTransition($uuid, 'REJECTED', ListingStatus::REJECTED, $reason, [ListingStatus::PENDING_REVIEW, ListingStatus::PENDING_PUBLICATION]);
    }

    public function publish(string $uuid): array
    {
        return $this->locked($uuid, function (Listing $listing): array {
            $this->security->checkUnitAccess($listing->getUnit(), SecurityAction::PUBLISH_LISTING);
            if ($listing->getStatus() !== ListingStatus::PENDING_PUBLICATION) {
                throw new ConflictHttpException('L’annonce doit être vérifiée et en attente de publication.');
            }
            $this->assertUnitEligible($listing->getUnit(), requirePhoto: true);
            $now = $this->dateTime->now();
            $listing->setPublishedAt($now);
            $listing->setPublicSnapshot($this->publicSnapshot($listing));
            $listing->getUnit()->setIsPublished(true);
            $this->transition($listing, 'PUBLISHED', ListingStatus::PUBLISHED, null, $listing->getPublicSnapshot());

            return $this->serialize($listing, true);
        });
    }

    public function withdraw(string $uuid, string $reason): array
    {
        return $this->reviewTransition($uuid, 'WITHDRAWN', ListingStatus::WITHDRAWN, $reason, [ListingStatus::PUBLISHED]);
    }

    /** @return array{items: list<array<string, mixed>>, total: int, page: int, pages: int} */
    public function listForOrganization(string $organizationUuid, ListingFilterRequest $request): array
    {
        $organization = $this->findOrganization($organizationUuid);
        $this->security->checkOrganizationAccess($organization, SecurityAction::VIEW_LISTING);
        $this->validateFilter($request);
        $filter = $request->status === null ? null : ListingStatus::tryFrom($request->status);
        $cities = $this->resolveListingCities($organization, $request->cityUuid);
        $result = $this->listings->findForManagement($organization, $cities, $filter, $request->page, $request->limit);

        return [
            'items' => array_map(fn (Listing $listing): array => $this->serialize($listing, false), $result['items']),
            'total' => $result['total'],
            'page' => $request->page,
            'pages' => max(1, (int) ceil($result['total'] / max(1, $request->limit))),
        ];
    }

    /** Biens disponibles dans le périmètre réel du rôle actif. */
    public function listEligibleUnits(string $organizationUuid, ListingCandidateFilterRequest $request): array
    {
        $organization = $this->findOrganization($organizationUuid);
        $this->security->checkOrganizationAccess($organization, SecurityAction::CREATE_LISTING);
        $this->assertShowcaseEnabled($organization);
        $this->assertValid($request);
        $cities = $this->resolveListingCities($organization, $request->cityUuid);
        $result = $this->units->findEligibleForListing($organization, $cities, $request->page, $request->limit);

        return [
            'items' => array_map(function (Unit $unit): array {
                $building = $unit->getBuilding();
                $parcel = $building->getParcel();
                $city = $parcel->getCity();
                $photos = $this->unitPhotoService->listForUnit($unit);

                return [
                    'uuid' => (string) $unit->getUuid(),
                    'reference' => $unit->getReference(),
                    'type' => $unit->getType()->value,
                    'cityUuid' => (string) $city->getUuid(),
                    'city' => $city->getName(),
                    'building' => $building->getName(),
                    'parcel' => $parcel->getName(),
                    'floor' => $unit->getFloor(),
                    'floorLabel' => $unit->getFloorLabel(),
                    'surface' => $unit->getSurface(),
                    'bedrooms' => $unit->getBedrooms(),
                    'rooms' => $unit->getRooms(),
                    'bathrooms' => $unit->getBathrooms(),
                    'monthlyRent' => $unit->getMonthlyRent(),
                    'currency' => $unit->getCurrency()->value,
                    'photoCount' => count($photos),
                    'coverPhotoUrl' => $photos[0]['url'] ?? null,
                ];
            }, $result['items']),
            'total' => $result['total'],
            'page' => $request->page,
            'pages' => max(1, (int) ceil($result['total'] / max(1, $request->limit))),
        ];
    }

    public function get(string $uuid): array
    {
        $listing = $this->findListing($uuid);
        $this->security->checkUnitAccess($listing->getUnit(), SecurityAction::VIEW_LISTING);

        return $this->serialize($listing, true);
    }

    private function reviewTransition(string $uuid, string $action, ListingStatus $next, ?string $reason, array $allowedFrom): array
    {
        if (in_array($next, [ListingStatus::CORRECTIONS_REQUESTED, ListingStatus::REJECTED, ListingStatus::WITHDRAWN], true)) {
            $reason = trim((string) $reason);
            if ($reason === '' || mb_strlen($reason) > 1000) throw new BadRequestHttpException('Un motif obligatoire de 1 à 1000 caractères est requis.');
        }

        return $this->locked($uuid, function (Listing $listing) use ($action, $next, $reason, $allowedFrom): array {
            $this->security->checkUnitAccess($listing->getUnit(), SecurityAction::REVIEW_LISTING);
            if (!in_array($listing->getStatus(), $allowedFrom, true)) throw new ConflictHttpException('Cette transition n’est pas autorisée depuis le statut actuel.');
            if ($next === ListingStatus::PENDING_PUBLICATION) $this->assertUnitEligible($listing->getUnit(), requirePhoto: true);
            if ($next === ListingStatus::WITHDRAWN) {
                $listing->getUnit()->setIsPublished(false);
            }
            $this->transition($listing, $action, $next, $reason);

            return $this->serialize($listing, true);
        });
    }

    private function locked(string $uuid, callable $callback): array
    {
        return $this->entityManager->wrapInTransaction(function () use ($uuid, $callback): array {
            $listing = $this->findListing($uuid);
            $this->entityManager->lock($listing, LockMode::PESSIMISTIC_WRITE);

            return $callback($listing);
        });
    }

    private function transition(Listing $listing, string $action, ListingStatus $next, ?string $reason = null, ?array $extraNewValues = null): void
    {
        $previous = $listing->getStatus();
        $content = $this->content($listing);
        $newValues = $extraNewValues === null ? $content : [...$content, 'publicSnapshot' => $extraNewValues];
        $listing->setStatus($next)->setUpdatedAt($this->dateTime->now());
        $this->record($listing, $action, $previous->value, $next, $reason, $content, $newValues);
        $this->entityManager->flush();
    }

    private function record(Listing $listing, string $action, ?string $oldStatus, ListingStatus $newStatus, ?string $reason, ?array $oldValues = null, ?array $newValues = null): void
    {
        $this->entityManager->persist((new ListingHistory())
            ->setListing($listing)->setActor($this->security->getCurrentUser())
            ->setAction($action)->setOldStatus($oldStatus)->setNewStatus($newStatus->value)
            ->setReason($reason)->setOldValues($oldValues)->setNewValues($newValues));
    }

    private function assertUnitEligible(Unit $unit, bool $requirePhoto): void
    {
        $city = $unit->getBuilding()->getParcel()->getCity();
        $organization = $city->getOrganization();
        $this->assertShowcaseEnabled($organization);
        if ($organization->getStatus() !== OrganizationStatus::ACTIVE || $city->getStatus() !== CityStatus::ACTIVE
            || $organization->getDeletedAt() !== null
            || $unit->getDeletedAt() !== null || $unit->getBuilding()->getDeletedAt() !== null
            || $unit->getBuilding()->getParcel()->getDeletedAt() !== null || $city->getDeletedAt() !== null
            || $unit->getOperationalStatus() !== UnitOperationalStatus::AVAILABLE
            || $this->leases->hasActiveLeaseForUnit($unit)) {
            throw new ConflictHttpException('Le bien n’est pas publiable : vérifiez l’activité, la disponibilité et l’absence de bail actif.');
        }

        if ($requirePhoto && $this->unitPhotos->countForUnit($unit) < 1) {
            throw new ConflictHttpException('Ajoutez au moins une photo à la galerie du bien avant de soumettre ou publier l’annonce.');
        }
    }

    private function assertShowcaseEnabled(Organization $organization): void
    {
        if (!$organization->isShowcaseEnabled()) throw new BadRequestHttpException('La vitrine publique n’est pas activée pour cette organisation.');
    }

    private function validate(ListingDraftRequest $request): void
    {
        $this->assertValid($request);
    }

    private function validateFilter(ListingFilterRequest $request): void
    {
        $this->assertValid($request);
    }

    /**
     * La ville active est un filtre d'écran, mais doit toujours être recoupée
     * avec les villes autorisées par SecurityService avant la requête SQL.
     * ADMIN_VILLE doit fournir une ville active pour éviter une vue consolidée.
     *
     * @return list<City>
     */
    private function resolveListingCities(Organization $organization, ?string $cityUuid): array
    {
        $user = $this->security->getCurrentUser();
        $role = $this->security->getOrganizationRole($user, $organization);
        $accessibleCities = $this->security->getAccessibleCitiesForOrganization($organization);

        if ($cityUuid === null) {
            if ($role === OrganizationRole::ADMIN_VILLE) {
                throw new BadRequestHttpException('Sélectionnez une ville active pour consulter sa vitrine.');
            }

            return $accessibleCities;
        }

        try {
            $requestedUuid = Uuid::fromString($cityUuid);
        } catch (\InvalidArgumentException) {
            throw new BadRequestHttpException('La ville sélectionnée est invalide.');
        }

        foreach ($accessibleCities as $city) {
            if ($city->getUuid()->equals($requestedUuid)) {
                return [$city];
            }
        }

        throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Cette ville ne fait pas partie de votre périmètre autorisé.');
    }

    private function assertValid(object $request): void
    {
        $violations = $this->validator->validate($request);
        if (count($violations) > 0) throw new BadRequestHttpException((string) $violations);
    }

    private function copyDraft(ListingDraftRequest $request, Listing $listing): Listing
    {
        return $listing->setTitle($request->title)->setDescription($request->description)
            ->setOfferType(ListingOfferType::from($request->offerType))->setPrice($request->price)
            ->setCurrency(Currency::from($request->currency));
    }

    private function content(Listing $listing): array
    {
        return ['title' => $listing->getTitle(), 'description' => $listing->getDescription(),
            'offerType' => $listing->getOfferType()->value, 'price' => $listing->getPrice(), 'currency' => $listing->getCurrency()->value];
    }

    private function publicSnapshot(Listing $listing): array
    {
        $unit = $listing->getUnit();
        $building = $unit->getBuilding();
        $city = $building->getParcel()->getCity();

        return ['reference' => $unit->getReference(), 'type' => $unit->getType()->value,
            'floor' => $unit->getFloor(), 'surface' => $unit->getSurface(),
            'bedrooms' => $unit->getBedrooms(), 'rooms' => $unit->getRooms(),
            'bathrooms' => $unit->getBathrooms(), 'city' => $city->getName(),
            'buildingName' => $building->getName()];
    }

    private function serialize(Listing $listing, bool $withHistory): array
    {
        $unit = $listing->getUnit();
        $city = $unit->getBuilding()->getParcel()->getCity();
        $photos = $this->unitPhotoService->listForUnit($unit);
        $data = ['uuid' => (string) $listing->getUuid(), 'unitUuid' => (string) $unit->getUuid(),
            'organizationUuid' => (string) $city->getOrganization()->getUuid(), 'cityUuid' => (string) $city->getUuid(),
            'city' => $city->getName(), 'unitReference' => $unit->getReference(),
            'buildingName' => $unit->getBuilding()->getName(), 'parcelName' => $unit->getBuilding()->getParcel()->getName(),
            'unitType' => $unit->getType()->value, 'floor' => $unit->getFloor(), 'floorLabel' => $unit->getFloorLabel(),
            'surface' => $unit->getSurface(), 'bedrooms' => $unit->getBedrooms(), 'rooms' => $unit->getRooms(),
            'bathrooms' => $unit->getBathrooms(), 'photoCount' => count($photos),
            'coverPhotoUrl' => $photos[0]['url'] ?? null,
            'createdByUuid' => (string) $listing->getCreatedBy()->getUuid(), 'status' => $listing->getStatus()->value,
            'title' => $listing->getTitle(), 'description' => $listing->getDescription(),
            'offerType' => $listing->getOfferType()->value, 'price' => $listing->getPrice(),
            'currency' => $listing->getCurrency()->value, 'publishedAt' => $listing->getPublishedAt()?->format(DATE_ATOM),
            'updatedAt' => $listing->getUpdatedAt()->format(DATE_ATOM)];
        if ($withHistory) $data['history'] = $this->listings->findHistory($listing);

        return $data;
    }

    private function findUnit(string $uuid): Unit
    {
        try { $unit = $this->units->findForListingByUuid(Uuid::fromString($uuid)); }
        catch (\InvalidArgumentException) { $unit = null; }
        if ($unit === null) throw new NotFoundHttpException('Bien immobilier introuvable.');

        return $unit;
    }

    private function findListing(string $uuid): Listing
    {
        try { $listing = $this->listings->findOneByUuid(Uuid::fromString($uuid)); }
        catch (\InvalidArgumentException) { $listing = null; }
        if ($listing === null) throw new NotFoundHttpException('Annonce introuvable.');

        return $listing;
    }

    private function findOrganization(string $uuid): Organization
    {
        try { $organization = $this->organizations->findOneByUuid(Uuid::fromString($uuid)); }
        catch (\InvalidArgumentException) { $organization = null; }
        if ($organization === null) throw new NotFoundHttpException('Organisation introuvable.');

        return $organization;
    }
}
