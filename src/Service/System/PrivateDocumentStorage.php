<?php

declare(strict_types=1);

namespace App\Service\System;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

/** Stockage de pièces justificatives hors de la racine servie publiquement. */
final readonly class PrivateDocumentStorage
{
    private const MAX_FILE_SIZE = 15 * 1024 * 1024;
    private const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/private-documents')]
        private string $storageDir,
    ) {
    }

    /** @return array{storageName: string, mimeType: string, size: int} */
    public function store(UploadedFile $file, Uuid $organizationUuid): array
    {
        $size = $file->getSize();
        $mimeType = $file->getMimeType();
        $extension = self::MIME_EXTENSIONS[$mimeType ?? ''] ?? null;
        if ($size === false || $size < 1 || $size > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException('Le fichier doit faire entre 1 octet et 15 Mo.');
        }
        if ($extension === null) {
            throw new \InvalidArgumentException('Type de fichier refusé. Formats acceptés : PDF, JPEG, PNG et WebP.');
        }

        $folder = $organizationUuid->toRfc4122();
        $targetDir = $this->storageDir . '/' . $folder;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0700, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Impossible de préparer le stockage privé des documents.');
        }
        @chmod($targetDir, 0700);

        $storageName = $folder . '/' . Uuid::v4()->toRfc4122() . '.' . $extension;
        $targetPath = $this->storageDir . '/' . $storageName;
        $file->move($targetDir, basename($targetPath));
        @chmod($targetPath, 0600);

        return ['storageName' => $storageName, 'mimeType' => $mimeType, 'size' => $size];
    }

    public function resolve(string $storageName): ?string
    {
        if (!preg_match('/^[0-9a-f-]{36}\/[0-9a-f-]{36}\.(pdf|jpg|png|webp)$/', $storageName)) {
            return null;
        }
        $root = realpath($this->storageDir);
        $path = realpath($this->storageDir . '/' . $storageName);
        if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
            return null;
        }

        return $path;
    }

    public function delete(string $storageName): bool
    {
        $path = $this->resolve($storageName);

        return $path !== null && unlink($path);
    }
}
