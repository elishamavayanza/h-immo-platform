<?php

declare(strict_types=1);

namespace App\Service\Property;

use App\Dto\Feedback;
use App\Dto\Request\Property\PublishListingRequest;
use App\Entity\Property\Unit;
use App\Entity\Property\UnitPhoto;
use App\Enum\ListingStatus;
use App\Repository\Property\UnitPhotoRepository;
use App\Repository\Property\UnitRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\System\AuditLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityException;
use Symfony\Component\Uid\Uuid;

/**
 * Pilotage de la vitrine : publication d'une unité et galerie photo.
 *
 * Chaque entrée applique le même contrat, et l'ordre des étapes est
 * volontaire :
 *
 *   1. résoudre l'unité par UUID (404 si inconnue) ;
 *   2. vérifier l'action précise sur SA ville (403 sinon) ;
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
        private SecurityServiceInterface $security,
        private AuditLogService $auditLogService,
        private UnitPhotoService $unitPhotoService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * L'ancien basculement d'unité est conservé uniquement pour répondre
     * clairement aux clients historiques : la publication exige désormais
     * une annonce approuvée et une transition explicite du workflow.
     */
    public function publish(Unit $unit, PublishListingRequest $request): Feedback
    {
        $this->security->checkUnitPublishAccess($unit, SecurityAction::PUBLISH_LISTING);
        throw new ConflictHttpException('La publication directe est désactivée. Utilisez le workflow de validation des annonces.');
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
        $this->assertCanEditMedia($unit);

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
            ->autoInitFlush()
            ->setStatus(201);
    }

    /** Lit la galerie après avoir vérifié le même périmètre que l'ajout. */
    public function listPhotos(Unit $unit): Feedback
    {
        $this->security->checkUnitPublishAccess($unit, SecurityAction::VIEW_LISTING);

        return (new Feedback())
            ->setData($this->unitPhotoService->listForManagement($unit))
            ->setFlushDescription('Photos de l’unité récupérées.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    /** Liste les photos retirées que l'utilisateur autorisé peut restaurer. */
    public function listDeletedPhotos(Unit $unit): Feedback
    {
        $this->security->checkUnitPublishAccess($unit, SecurityAction::VIEW_LISTING);

        return (new Feedback())
            ->setData($this->unitPhotoService->listDeletedForManagement($unit))
            ->setFlushDescription('Photos retirées récupérables de l’unité récupérées.')
            ->setStatus(200)
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

        $this->assertCanEditMedia($unit);

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
            null,
            false,
        );
        $this->entityManager->flush();

        return (new Feedback())
            ->setData($this->unitPhotoService->listForUnit($unit))
            ->setFlushDescription('Photo retirée de la galerie.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function restorePhoto(string $unitUuid, string $photoUuid): Feedback
    {
        $unit = $this->findUnitOrFail($unitUuid);
        $this->assertCanEditMedia($unit);

        try {
            $photo = $this->unitPhotoRepository->findDeletedOneByUnitAndUuid(
                $unit,
                Uuid::fromString($photoUuid)
            );
        } catch (\InvalidArgumentException) {
            $photo = null;
        }

        if ($photo === null) {
            throw new UnprocessableEntityException('Photo supprimée introuvable pour cette unité.');
        }

        if ($this->unitPhotoService->countForUnit($unit) >= 12) {
            return (new Feedback())
                ->addError('photos', 'La galerie contient déjà le nombre maximal de photos.')
                ->setFlushDescription('Restauration impossible : la galerie est pleine.')
                ->setStatus(409)
                ->autoInitFlush();
        }

        $deletedAt = $photo->getDeletedAt()?->format(DATE_ATOM);
        $this->unitPhotoService->restorePhoto($photo);
        $this->auditLogService->log(
            'RESTORE_UNIT_PHOTO',
            UnitPhoto::class,
            (int) $photo->getId(),
            $unit->getBuilding()->getParcel()->getCity()->getOrganization(),
            $this->security->getCurrentUser(),
            ['unitUuid' => (string) $unit->getUuid(), 'photoUuid' => (string) $photo->getUuid(), 'deletedAt' => $deletedAt],
            ['deletedAt' => null, 'position' => $photo->getPosition()],
            false,
        );
        $this->entityManager->flush();

        return (new Feedback())
            ->setData($this->unitPhotoService->listForUnit($unit))
            ->setFlushDescription('Photo restaurée dans la galerie.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    private function assertCanEditMedia(Unit $unit): void
    {
        $this->security->checkUnitPublishAccess($unit, SecurityAction::MANAGE_LISTING_MEDIA);
        if ($unit->getListing()?->getStatus() === ListingStatus::PUBLISHED) {
            throw new ConflictHttpException('Retirez l’annonce de la vitrine avant de modifier ses photos, puis soumettez-la à une nouvelle vérification.');
        }
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
