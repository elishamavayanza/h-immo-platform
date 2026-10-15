<?php

declare(strict_types=1);

namespace App\Service\System;

use App\Entity\Identity\Organization;
use App\Enum\OrganizationRole;
use App\Enum\PlatformRole;
use App\Exception\AccessDeniedException;
use App\Repository\Identity\OrganizationRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/** Détermine le manuel accessible depuis l’espace réellement actif. */
final readonly class UserManualService
{
    public function __construct(
        private SecurityServiceInterface $security,
        private OrganizationRepository $organizations,
        private RequestStack $requestStack,
        private string $catalogPath,
        private string $manualsDirectory,
        private string $pagesDirectory,
    ) {
    }

    /** @return array{metadata: array<string, mixed>, path: string} */
    public function getCurrentManual(): array
    {
        $user = $this->security->getCurrentUser();
        if ($user->getPlatformRole() === PlatformRole::SUPER_ADMIN) {
            $roleKey = PlatformRole::SUPER_ADMIN->value;
        } else {
            $request = $this->requestStack->getCurrentRequest();
            $organizationUuid = $request?->headers->get('X-Organization-Context');
            if (!is_string($organizationUuid) || !Uuid::isValid($organizationUuid)) {
                throw new AccessDeniedException('Sélectionnez une organisation active pour consulter son manuel.');
            }

            $organization = $this->organizations->findOneByUuid(Uuid::fromString($organizationUuid));
            if (!$organization instanceof Organization) {
                throw new AccessDeniedException('Organisation active introuvable.');
            }

            $this->security->checkOrganizationAccess($organization, SecurityAction::VIEW_ORGANIZATION);
            $role = $this->security->getOrganizationRole($user, $organization);
            if (!$role instanceof OrganizationRole) {
                throw new AccessDeniedException('Aucun espace utilisateur actif ne permet de consulter ce manuel.');
            }
            $roleKey = $role->value;
        }

        $catalog = $this->readCatalog();
        $manual = $catalog['roles'][$roleKey] ?? null;
        if (!is_array($manual) || !isset($manual['filename'])) {
            throw new \RuntimeException('Le manuel du rôle actif est indisponible.');
        }

        $path = rtrim($this->manualsDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.basename((string) $manual['filename']);
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('Le fichier du manuel du rôle actif est indisponible.');
        }

        return ['metadata' => [
            'role' => $roleKey,
            'label' => $manual['label'] ?? $roleKey,
            'title' => $manual['title'] ?? '',
            'description' => $manual['description'] ?? '',
            'version' => $catalog['version'] ?? '',
            'updatedAt' => $catalog['updatedAt'] ?? '',
            'filename' => basename((string) $manual['filename']),
            'pageCount' => (int) ($manual['pageCount'] ?? 0),
        ], 'path' => $path];
    }

    /** @return array{path: string, pageCount: int} */
    public function getCurrentManualPage(int $page): array
    {
        $manual = $this->getCurrentManual();
        $pageCount = (int) $manual['metadata']['pageCount'];
        if ($page < 1 || $page > $pageCount) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Page de manuel introuvable.');
        }

        $role = (string) $manual['metadata']['role'];
        $path = rtrim($this->pagesDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$role.DIRECTORY_SEPARATOR.sprintf('page-%03d.jpg', $page);
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('La page du manuel actif est indisponible.');
        }

        return ['path' => $path, 'pageCount' => $pageCount];
    }

    /** @return array<string, mixed> */
    private function readCatalog(): array
    {
        $contents = @file_get_contents($this->catalogPath);
        $catalog = is_string($contents) ? json_decode($contents, true) : null;
        if (!is_array($catalog) || !isset($catalog['roles']) || !is_array($catalog['roles'])) {
            throw new \RuntimeException('Le catalogue des manuels est invalide.');
        }

        return $catalog;
    }
}
