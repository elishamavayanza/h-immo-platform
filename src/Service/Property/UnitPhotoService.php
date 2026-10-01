<?php

declare(strict_types=1);

namespace App\Service\Property;

use App\Entity\Property\Unit;
use App\Entity\Property\UnitPhoto;
use App\Repository\Property\UnitPhotoRepository;
use App\Service\System\FileUploadService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Gestion de la galerie de photos d'une unité publiée.
 *
 * Le stockage passe par `FileUploadService`, qui valide type et taille, génère
 * le nom de fichier côté serveur et refuse la traversée de chemin. Cette
 * service ne fait que le lien entre l'unité et le disque : aucune vérification
 * de sécurité ici, elle appartient à l'appelant qui a déjà contrôlé
 * `PUBLISH_LISTING`.
 */
final readonly class UnitPhotoService
{
    /**
     * Sous-dossier de dépôt, relatif à `public/uploads`.
     *
     * Un dossier par unité : les photos d'un bien n'ont aucun rapport avec
     * celles d'un autre, et le nettoyage d'une unité ne doit pas obliger à
     * parcourir un dossier commun.
     */
    private const UPLOAD_FOLDER = 'units';

    /**
     * Le nom de fichier est généré par `FileUploadService` à partir du nom
     * client, dans un dossier lui appartenant. Cette limite borne donc ce que
     * ce service écrit dans son dossier, pas ce que `FileUploadService`
     * accepte ailleurs.
     */
    private const MAX_PHOTOS_PER_UNIT = 12;

    public function __construct(
        private FileUploadService $fileUploadService,
        private UnitPhotoRepository $unitPhotoRepository,
    ) {
    }

    /**
     * Ajoute une photo à la galerie de l'unité.
     *
     * La position est `max + 1` : l'ordre d'arrivée est celui de l'upload, ce
     * qui évite d'exposer un `position` fourni par le client et donc
     * manipulable.
     */
    public function addPhoto(Unit $unit, UploadedFile $file): UnitPhoto
    {
        if ($this->countForUnit($unit) >= self::MAX_PHOTOS_PER_UNIT) {
            throw new \RuntimeException(sprintf(
                'Une unité ne peut pas dépasser %d photos.',
                self::MAX_PHOTOS_PER_UNIT
            ));
        }

        $folder = self::UPLOAD_FOLDER.'/'.$unit->getUuid()->toRfc4122();

        // Un dossier par unité, comme les photos de parcelle : le nom de
        // fichier est produit par `FileUploadService`, jamais celui du client.
        $path = $this->fileUploadService->upload($file, $folder);

        $photo = new UnitPhoto($unit, $path, $this->nextPosition($unit));
        $this->unitPhotoRepository->save($photo, flush: true);

        return $photo;
    }

    /**
     * Retire une photo de la galerie : ligne et fichier.
     *
     * Le fichier est supprimé après le flush de la ligne, et non avant : si
     * l'écriture échoue, la photo reste référencée et visible plutôt que
     * pointer vers un lien mort.
     *
     * Le chemin vient de la base et non du client, mais `FileUploadService`
     * refuse de toute façon toute sortie du répertoire d'uploads.
     */
    public function removePhoto(UnitPhoto $photo): void
    {
        $relativePath = $photo->getPath();

        $this->unitPhotoRepository->remove($photo, flush: true);

        $this->fileUploadService->delete($relativePath);
    }

    /**
     * Galerie d'une unité, ordonnée, avec les URLs publiques.
     */
    public function listForUnit(Unit $unit): array
    {
        $out = [];

        foreach ($this->unitPhotoRepository->findForUnit($unit) as $photo) {
            $out[] = [
                'url' => $this->fileUploadService->getPublicUrl($photo->getPath()),
                'position' => $photo->getPosition(),
            ];
        }

        return $out;
    }

    public function countForUnit(Unit $unit): int
    {
        return $this->unitPhotoRepository->countForUnit($unit);
    }

    /**
     * Prochain rang de galerie.
     *
     * `MAX(position)` et non `COUNT(*)` : si une photo est supprimée au milieu,
     * le compte retombe et produirait un rang déjà utilisé.
     */
    private function nextPosition(Unit $unit): int
    {
        return $this->unitPhotoRepository->maxPositionForUnit($unit) + 1;
    }
}