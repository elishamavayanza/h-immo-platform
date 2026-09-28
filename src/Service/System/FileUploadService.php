<?php

declare(strict_types=1);

namespace App\Service\System;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * FileUploadService
 *
 * Package : System & Audit
 *
 * Gère l'upload sécurisé de fichiers (photos et logos).
 * Stocke les fichiers dans public/uploads/ organisé par type d'entité.
 *
 * Règles de sécurité :
 * - Validation MIME type et extension
 * - Limite de taille configurable
 * - Noms de fichiers sécurisés (slug + hash + timestamp)
 * - Suppression de l'ancien fichier lors du remplacement
 * - Accès via URL publique /uploads/{type}/{filename}
 */
final readonly class FileUploadService
{
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 Mo

    /**
     * Images uniquement en V1 : le PDF est volontairement retiré.
     *
     * `public/uploads` est servi par le serveur web sans authentification —
     * aucune règle `access_control` ne couvre ce chemin, qui n'est pas sous
     * `/api`. Un document déposé à cet endroit est donc downloadable par
     * quiconque connaît ou devine son URL, et le nom de fichier est
     * prévisible (slug + horodatage). Tant que les documents ne sont pas
     * une fonctionnalité V1, on refuse de les déposer ici ; les pièces
     * justificatives ne doivent pas attendre que ce trou soit refermé.
     */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function __construct(
        private string $uploadsDir,
        private SluggerInterface $slugger,
        private string $baseUrl = '/uploads',
    ) {
        // Créer les sous-dossiers s'ils n'existent pas
        $subdirs = ['users', 'organizations', 'parcels', 'buildings', 'units'];
        foreach ($subdirs as $dir) {
            if (!is_dir($this->uploadsDir . '/' . $dir)) {
                mkdir($this->uploadsDir . '/' . $dir, 0755, true);
            }
        }
    }

    /**
     * Upload un fichier pour une entité donnée.
     *
     * @param UploadedFile $file Fichier uploadé
     * @param string $entityType Type d'entité : 'users', 'organizations', 'parcels', 'buildings', 'units'
     * @param string|null $oldFilePath Ancien chemin à supprimer (optionnel)
     * @return string Chemin relatif du fichier stocké (ex: 'users/abc123.jpg')
     * @throws \InvalidArgumentException Si validation échoue
     */
    public function upload(UploadedFile $file, string $entityType, ?string $oldFilePath = null): string
    {
        $this->validateFile($file, $entityType);

        // Supprimer l'ancien fichier si fourni
        if ($oldFilePath !== null) {
            $this->delete($oldFilePath);
        }

        // Générer un nom de fichier sécurisé
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = $this->slugger->slug($originalName)->lower();
        $extension = strtolower($file->guessExtension() ?? 'bin');
        $timestamp = (new \DateTimeImmutable())->format('YmdHis');
        $random = bin2hex(random_bytes(8));
        $filename = sprintf('%s_%s_%s.%s', $safeName, $timestamp, $random, $extension);

        $targetDir = $this->uploadsDir . '/' . $entityType;
        $relativePath = $entityType . '/' . $filename;
        $absolutePath = $targetDir . '/' . $filename;

        // Le dossier d'un sous-dossier par entité (`parcels/{uuid}`) n'est
        // pas créé par le constructeur, qui ne connaît que les premiers
        // niveaux. Sans ce `mkdir`, `move()` échouerait sur un dossier
        // inexistant.
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new \RuntimeException(sprintf('Impossible de créer le dossier de dépôt : %s', $entityType));
        }

        try {
            $file->move($targetDir, $filename);
        } catch (FileException $e) {
            throw new \RuntimeException(sprintf('Échec de l\'upload : %s', $e->getMessage()), previous: $e);
        }

        return $relativePath;
    }

    /**
     * Supprime un fichier existant.
     *
     * Le chemin est résolu puis comparé au répertoire d'uploads : c'est
     * cette vérification qui empêche la traversée de répertoire. Un
     * chemin comme `parcels/../../.env` se résout en `public/.env`, hors du
     * répertoire d'uploads ; sans ce garde-fou, un appelant qui contrôle
     * le nom de fichier — le cas des routes de suppression — supprimait
     * n'importe quel fichier accessible à l'utilisateur du serveur.
     *
     * Les séquences de remontée sont refusées AVANT la résolution : le
     * `realpath()` d'un chemin qui n'existe pas renvoie `false`, ce qui
     * laisserait passer un chemin malveillant visant un fichier absent au
     * moment du contrôle.
     */
    public function delete(string $relativePath): bool
    {
        $absolutePath = $this->resolveInsideUploads($relativePath);

        if ($absolutePath === null || !is_file($absolutePath)) {
            return false;
        }

        return unlink($absolutePath);
    }

    /**
     * Résout un chemin relatif en chemin absolu, à condition qu'il reste
     * dans le répertoire d'uploads. Renvoie `null` sinon.
     */
    public function resolveInsideUploads(string $relativePath): ?string
    {
        // Antislashage : un chemin absolu n'a rien à faire ici, et sous
        // Windows la barre inverse est un séparateur comme la barre
        // oblique.
        if (str_contains($relativePath, '\\')) {
            return null;
        }

        if ($relativePath === '' || str_starts_with($relativePath, '/')) {
            return null;
        }

        $segments = explode('/', $relativePath);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        $uploadsRealPath = realpath($this->uploadsDir);
        $absolutePath = $this->uploadsDir . '/' . $relativePath;
        $absoluteRealPath = realpath($absolutePath);

        if ($uploadsRealPath === false || $absoluteRealPath === false) {
            return null;
        }

        // `DIRECTORY_SEPARATOR` termine le préfixe, sans quoi
        // `/uploads-evil` passerait le test pour un enfant de `/uploads`.
        if (!str_starts_with($absoluteRealPath, $uploadsRealPath.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $absolutePath;
    }

    /**
     * Vrai si le chemin donné appartient bien au sous-dossier indiqué.
     *
     * Sert à prouver l'appartenance d'un fichier à une entité lorsque
     * celle-ci n'est pas stockée en base : sans cela, la seule garantie
     * qu'un nom de fichier ne sort pas de son périmètre est celle de son
     * URL.
     */
    public function belongsToFolder(string $relativePath, string $folder): bool
    {
        $relativePath = ltrim($relativePath, '/');

        return str_starts_with($relativePath, trim($folder, '/').'/');
    }

    /**
     * Retourne l'URL publique d'accès au fichier.
     */
    public function getPublicUrl(string $relativePath): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($relativePath, '/');
    }

    /**
     * Valide le fichier (taille, MIME, extension).
     */
    private function validateFile(UploadedFile $file, string $entityType): void
    {
        // Taille
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException(sprintf(
                'Fichier trop volumineux (max %d Mo).',
                self::MAX_FILE_SIZE / 1024 / 1024
            ));
        }

        // MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Type de fichier non autorisé : %s. Types acceptés : %s.',
                $mimeType,
                implode(', ', self::ALLOWED_MIME_TYPES)
            ));
        }

        // Extension
        $extension = strtolower($file->guessExtension() ?? '');
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Extension non autorisée : %s. Extensions acceptées : %s.',
                $extension,
                implode(', ', self::ALLOWED_EXTENSIONS)
            ));
        }

        // Type d'entité valide. Un sous-dossier est autorisé
        // (`parcels/{uuid}`) : c'est ce qui rattache un fichier à son
        // entité dans le chemin, faute de lien en base. Chaque segment
        // est contrôlé pour qu'aucun ne puisse s'évader du premier
        // niveau.
        $segments = explode('/', $entityType);
        $rootType = array_shift($segments);

        $validTypes = ['users', 'organizations', 'parcels', 'buildings', 'units'];

        if (!in_array($rootType, $validTypes, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Type d\'entité invalide : %s. Types valides : %s.',
                $entityType,
                implode(', ', $validTypes)
            ));
        }

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException(sprintf(
                    'Chemin de dépôt invalide : %s.',
                    $entityType
                ));
            }
        }
    }

    /**
     * Retourne la configuration pour la documentation OpenAPI.
     */
    public static function getOpenApiConfig(): array
    {
        return [
            'maxFileSize' => self::MAX_FILE_SIZE,
            'allowedMimeTypes' => self::ALLOWED_MIME_TYPES,
            'allowedExtensions' => self::ALLOWED_EXTENSIONS,
        ];
    }
}