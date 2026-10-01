<?php

declare(strict_types=1);

namespace App\Service\Property;

use App\Dto\Feedback;
use App\Dto\Request\Property\PublishListingRequest;
use App\Entity\Property\Unit;
use App\Entity\Property\UnitPhoto;
use App\Repository\Property\UnitPhotoRepository;
use App\Repository\Property\UnitRepository;
use App\Repository\Rental\LeaseRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\System\AuditLogService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityException;
use Symfony\Component\Uid\Uuid;

/**
 * Pilotage de la vitrine : publication d'une unité et galerie photo.
 *
 * Chaque entrée applique le même contrat, et l'ordre des étapes est
 * volontaire :
 *
 *   1. résoudre l'unité par UUID (404 si inconnue) ;
 *   2. vérifier `PUBLISH_LISTING` sur SA ville (403 sinon) ;
 *   3. appliquer ensuite la règle métier propre à l'action.
 *
 * L'inversion de 2 et 3 est ce qui produit le bug classique : on refuse parce
 * que « l'unité est occupée » avant d'avoir vérifié qui demande, ce qui
 * transforme une fonctionnalité d'administration en oracle sur l'occupation des
 * biens d'un autre tenant.
 */
final readonly class PublicShowcaseManagementService
{
    public function __construct(
        private UnitRepository $unitRepository,
        private UnitPhotoRepository $unitPhotoRepository,
        private LeaseRepository $leaseRepository,
        private SecurityServiceInterface $security,
        private AuditLogService $auditLogService,
        private UnitPhotoService $unitPhotoService,
    ) {
    }

    /**
     * Publie ou retire l'annonce d'une unité.
     *
     * Publier une unité occupée est refusé : `is_published` est la décision
     * d'exposition, et une décision qui promet au public une chose fausse
     * n'est pas une décision. La disponibilité réelle reste de toute façon
     * recalculée à la lecture, donc ce refus protège le gestionnaire d'une
     * erreur, pas le visiteur d'un mensonge.
     *
     * Dépublier n'est jamais refusé : c'est au contraire le seul recours si
     * un bail a été activé par erreur.
     *
     * @throws UnprocessableEntityException si publication demandée sur un bien occupé
     */
    public function publish(Unit $unit, PublishListingRequest $request): Feedback
    {
        $this->security->checkUnitPublishAccess($unit, SecurityAction::PUBLISH_LISTING);

        $published = $request->isPublished ?? false;

        if ($published && $this->leaseRepository->hasActiveLeaseForUnit($unit)) {
            throw new UnprocessableEntityException(
                'Impossible de publier une unité déjà occupée : un bail est actif sur ce bien.'
            );
        }

        $unit->setIsPublished($published);
        $this->unitRepository->save($unit, flush: true);

        $this->auditLogService->log(
            $published ? 'LISTING_PUBLISHED' : 'LISTING_UNPUBLISHED',
            Unit::class,
            (int) $unit->getId(),
            $unit->getBuilding()->getParcel()->getCity()->getOrganization(),
            $this->security->getCurrentUser(),
            null,
            [
                'unitUuid' => (string) $unit->getUuid(),
                'unitReference' => $unit->getReference(),
                'isPublished' => $published,
            ]
        );

        return (new Feedback())
            ->setFlushDescription($published
                ? 'Annonce publiée sur la vitrine publique.'
                : 'Annonce retirée de la vitrine publique.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Ajoute une photo à la galerie de l'unité.
     *
     * La publication n'est pas un préalable : une galerie se prépare avant la
     * mise en ligne, et lier les deux obligerait à publier une annonce vide
     * pour pouvoir la contenir.
     */
    public function addPhoto(Unit $unit, UploadedFile $file): Feedback
    {
        $this->security->checkUnitPublishAccess($unit, SecurityAction::PUBLISH_LISTING);

        $photo = $this->unitPhotoService->addPhoto($unit, $file);

        $this->auditLogService->log(
            'LISTING_PHOTO_ADDED',
            UnitPhoto::class,
            (int) $photo->getId(),
            $unit->getBuilding()->getParcel()->getCity()->getOrganization(),
            $this->security->getCurrentUser(),
            null,
            [
                'unitUuid' => (string) $unit->getUuid(),
                'photoUuid' => (string) $photo->getUuid(),
                'position' => $photo->getPosition(),
            ]
        );

        return (new Feedback())
            ->setData($this->unitPhotoService->listForUnit($unit))
            ->setFlushDescription('Photo ajoutée à la galerie.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    /**
     * Retire une photo de la galerie d'une unité.
     *
     * La photo est d'abord rattachée à l'unité par l'UUID fourni, et l'unité
     * obtenue sert à autoriser : chercher la photo seule autoriserait sur la
     * seule foi de son identifiant, et le retrait porterait alors sur un bien
     * d'une autre entreprise.
     */
    public function removePhoto(string $unitUuid, string $photoUuid): Feedback
    {
        $unit = $this->findUnitOrFail($unitUuid);

        $this->security->checkUnitPublishAccess($unit, SecurityAction::PUBLISH_LISTING);

        try {
            $photo = $this->unitPhotoRepository->findOneByUnitAndUuid(
                $unit,
                Uuid::fromString($photoUuid)
            );
        } catch (\InvalidArgumentException) {
            $photo = null;
        }

        if ($photo === null) {
            throw new UnprocessableEntityException('Photo introuvable pour cette unité.');
        }

        $this->unitPhotoService->removePhoto($photo);

        $this->auditLogService->log(
            'LISTING_PHOTO_REMOVED',
            UnitPhoto::class,
            (int) $photo->getId(),
            $unit->getBuilding()->getParcel()->getCity()->getOrganization(),
            $this->security->getCurrentUser(),
            [
                'unitUuid' => (string) $unit->getUuid(),
                'photoUuid' => (string) $photo->getUuid(),
            ],
            null
        );

        return (new Feedback())
            ->setData($this->unitPhotoService->listForUnit($unit))
            ->setFlushDescription('Photo retirée de la galerie.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /**
     * Résout l'unité par UUID, ou interrompt la requête en 404.
     *
     * Une exception plutôt qu'un retour `null` : toutes les actions de cette
     * service ont la même forme, et faire porter au contrôleur le distinguement
     * « unité absente » le dispenserait de le faire à chaque fois.
     */
    private function findUnitOrFail(string $uuid): Unit
    {
        try {
            $parsed = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            throw new UnprocessableEntityException('Identifiant d\'unité invalide.');
        }

        $unit = $this->unitRepository->findOneByUuid($parsed);

        if ($unit === null) {
            // 404 et non 403 : l'unité est réellement absente du périmètre,
            // et un identifiant d'une autre entreprise ne doit pas se
            // distinguer d'un identifiant inventé.
            throw new NotFoundHttpException('Unité introuvable.');
        }

        return $unit;
    }
}