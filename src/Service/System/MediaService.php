<?php

declare(strict_types=1);

namespace App\Service\System;

use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Entity\Property\Parcel;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Identity\UserRepository;
use App\Repository\Property\ParcelRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

/**
 * MediaService
 *
 * Package : System & Audit — Service Métier
 *
 * Rattache un fichier téléversé à l'entité qu'il concerne, après avoir
 * vérifié que l'appelant a le droit d'y toucher.
 *
 * Chaque point de ce service applique deux règles :
 *
 *  1. l'entité est résolue par son UUID, jamais déduite du nom de fichier ;
 *  2. le contrôle d'accès passe par `SecurityService`, qui résout le rôle
 *     dans l'Organization de l'entité.
 *
 * Le schéma de la base ne conservait aucun lien entre une parcelle et ses
 * fichiers. Le chemin stocké porte donc l'UUID de la parcelle
 * (`parcels/{uuid}/…`) : l'appartenance devient vérifiable à partir du
 * chemin, ce qui permet à `deleteParcelPhoto()` de refuser de supprimer
 * un fichier qui n'est pas sous le dossier de la parcelle demandée. Sans
 * cela, l'UUID de la parcelle dans l'URL n'était qu'un décor, et la
 * suppression visait `parcels/{filename}` — un fichier choisi par
 * l'appelant, dans le dossier partagé de tous les tenants.
 */
final readonly class MediaService
{
    public function __construct(
        private FileUploadService $fileUploadService,
        private SecurityServiceInterface $securityService,
        private UserRepository $userRepository,
        private OrganizationRepository $organizationRepository,
        private ParcelRepository $parcelRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | PHOTO DE PROFIL
    |--------------------------------------------------------------------------
    */

    /**
     * Remplace la photo de profil d'un utilisateur et renvoie le chemin
     * relatif stocké.
     *
     * Le contrôle est `UPDATE_USER` : `checkUserAccess()` autorise
     * l'appelant sur son propre profil, le SUPER_ADMIN, et un compte
     * partageant au moins une Organization avec lui. Un compte d'une autre
     * société ne peut donc pas remplacer la photo d'un utilisateur qui
     * n'a rien à voir avec lui.
     */
    public function replaceUserPhoto(User $target, UploadedFile $file): string
    {
        $this->securityService->checkUserAccess($target, SecurityAction::UPDATE_USER);

        $oldPath = $target->getProfilePhoto();

        $relativePath = $this->fileUploadService->upload($file, 'users');

        $target->setProfilePhoto($relativePath);
        $this->entityManager->flush();

        // Suppression après flush : si l'écriture échoue, l'ancien fichier
        // reste en place et la photo affichée ne devient pas un lien mort.
        if ($oldPath !== null && $oldPath !== $relativePath) {
            $this->fileUploadService->delete($oldPath);
        }

        return $relativePath;
    }

    /**
     * Détache la photo de profil. Renvoie `false` s'il n'y en avait pas.
     */
    public function removeUserPhoto(User $target): bool
    {
        $this->securityService->checkUserAccess($target, SecurityAction::UPDATE_USER);

        $oldPath = $target->getProfilePhoto();

        if ($oldPath === null) {
            return false;
        }

        $target->setProfilePhoto(null);
        $this->entityManager->flush();

        return $this->fileUploadService->delete($oldPath);
    }

    /*
    |--------------------------------------------------------------------------
    | LOGO D'ORGANIZATION
    |--------------------------------------------------------------------------
    */

    /**
     * Remplace le logo d'une organisation.
     *
     * L'action `UPDATE_ORGANIZATION` n'est accordée qu'au PATRON dans la
     * matrice rôle x action : ni l'ADMIN_IMMOBILIER ni l'ADMIN_VILLE ne
     * l'obtiennent, ce qui est le comportement attendu pour une identité
     * commerciale.
     */
    public function replaceOrganizationLogo(Organization $organization, UploadedFile $file): string
    {
        $this->securityService->checkOrganizationAccess($organization, SecurityAction::MANAGE_ORGANIZATION);

        $oldPath = $organization->getLogo();

        $relativePath = $this->fileUploadService->upload($file, 'organizations');

        $organization->setLogo($relativePath);
        $this->entityManager->flush();

        if ($oldPath !== null && $oldPath !== $relativePath) {
            $this->fileUploadService->delete($oldPath);
        }

        return $relativePath;
    }

    public function removeOrganizationLogo(Organization $organization): bool
    {
        $this->securityService->checkOrganizationAccess($organization, SecurityAction::MANAGE_ORGANIZATION);

        $oldPath = $organization->getLogo();

        if ($oldPath === null) {
            return false;
        }

        $organization->setLogo(null);
        $this->entityManager->flush();

        return $this->fileUploadService->delete($oldPath);
    }

    /*
    |--------------------------------------------------------------------------
    | PHOTOS DE PARCELLE
    |--------------------------------------------------------------------------
    */

    /**
     * Dépose une photo dans le dossier de la parcelle.
     *
     * `UPDATE_PARCEL` est ouverte au PATRON, à l'ADMIN_IMMOBILIER et à
     * l'ADMIN_VILLE, ce dernier étant déjà borné à SES villes par
     * `checkParcelAccess()`.
     *
     * @return list<array{path: string, url: string}>
     */
    public function addParcelPhotos(Parcel $parcel, array $files): array
    {
        $this->securityService->checkParcelAccess($parcel, SecurityAction::UPDATE_PARCEL);

        $folder = $this->parcelFolder($parcel);
        $uploaded = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $relativePath = $this->fileUploadService->upload($file, $folder);

            $uploaded[] = [
                'path' => $relativePath,
                'url' => $this->fileUploadService->getPublicUrl($relativePath),
            ];
        }

        return $uploaded;
    }

    /**
     * Supprime une photo de parcelle.
     *
     * Le nom de fichier venu de l'URL est réduit à son nom de base puis
     * reconstruit dans le dossier de la parcelle. Un `filename` contenant
     * un séparateur — `..%2f..%2fphoto.jpg` se décodant en `../../photo.jpg`
     * une fois la route résolue — ne peut donc pas viser un autre dossier,
     * et `FileUploadService::delete()` refuse par ailleurs tout chemin
     * sortant de `uploads/`.
     */
    public function deleteParcelPhoto(Parcel $parcel, string $filename): bool
    {
        $this->securityService->checkParcelAccess($parcel, SecurityAction::DELETE_PARCEL);

        $filename = basename($filename);

        if ($filename === '' || $filename === '.' || $filename === '..') {
            return false;
        }

        return $this->fileUploadService->delete($this->parcelFolder($parcel).'/'.$filename);
    }

    /**
     * Sous-dossier de dépôt d'une parcelle : `parcels/{uuid}`.
     */
    private function parcelFolder(Parcel $parcel): string
    {
        return 'parcels/'.$parcel->getUuid()->toRfc4122();
    }

    /*
    |--------------------------------------------------------------------------
    | RÉSOLUTION DES ENTITÉS
    |--------------------------------------------------------------------------
    */

    /**
     * Résout une entité par son UUID, ou `null` si l'identifiant est
     * malformé ou inconnu.
     *
     * Résoudre avant d'appliquer le contrôle d'accès est délibéré : sans
     * cible, il n'y a rien à autoriser. Le 404 est renvoyé par le
     * contrôleur, qui le garde après le contrôle d'accès afin de ne pas
     * révéler l'existence d'une entité d'un autre tenant.
     */
    public function resolveUser(string $uuid): ?User
    {
        $parsed = $this->parseUuid($uuid);

        return $parsed === null ? null : $this->userRepository->findOneByUuid($parsed);
    }

    public function resolveOrganization(string $uuid): ?Organization
    {
        $parsed = $this->parseUuid($uuid);

        return $parsed === null ? null : $this->organizationRepository->findOneByUuid($parsed);
    }

    public function resolveParcel(string $uuid): ?Parcel
    {
        $parsed = $this->parseUuid($uuid);

        return $parsed === null ? null : $this->parcelRepository->findOneByUuid($parsed);
    }

    private function parseUuid(string $uuid): ?Uuid
    {
        try {
            return Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
