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
 * Gère l'upload sécurisé de fichiers (photos, logos, documents).
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
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'application/pdf',
    ];
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];

    public function __construct(
        private string $uploadsDir,
        private SluggerInterface $slugger,
        private string $baseUrl = '/uploads',
    ) {
        // Créer les sous-dossiers s'ils n'existent pas
        $subdirs = ['users', 'organizations', 'parcels', 'buildings', 'units', 'documents'];
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
     * @param string $entityType Type d'entité : 'users', 'organizations', 'parcels', 'buildings', 'units', 'documents'
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

        try {
            $file->move($targetDir, $filename);
        } catch (FileException $e) {
            throw new \RuntimeException(sprintf('Échec de l\'upload : %s', $e->getMessage()), previous: $e);
        }

        return $relativePath;
    }

    /**
     * Supprime un fichier existant.
     */
    public function delete(string $relativePath): bool
    {
        $absolutePath = $this->uploadsDir . '/' . $relativePath;

        if (file_exists($absolutePath)) {
            return unlink($absolutePath);
        }

        return false;
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

        // Type d'entité valide
        $validTypes = ['users', 'organizations', 'parcels', 'buildings', 'units', 'documents'];
        if (!in_array($entityType, $validTypes, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Type d\'entité invalide : %s. Types valides : %s.',
                $entityType,
                implode(', ', $validTypes)
            ));
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