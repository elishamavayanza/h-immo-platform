<?php

declare(strict_types=1);

namespace App\Service\System;

use App\Dto\Request\System\PublicationDraftDto;
use App\Entity\Identity\Organization;
use App\Entity\System\Publication;
use App\Entity\System\PublicationRecipient;
use App\Enum\PublicationAudience;
use App\Enum\PublicationCategory;
use App\Enum\PublicationStatus;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\System\PublicationRepository;
use App\Security\SecurityServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final class PublicationService
{
    public function __construct(
        private readonly PublicationRepository $publications,
        private readonly OrganizationRepository $organizations,
        private readonly SecurityServiceInterface $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly DateTimeService $dateTime,
        private readonly FileUploadService $uploads,
        private readonly AuditLogService $audit,
    ) {}

    /** @return list<array<string, mixed>> */
    public function managementList(): array
    {
        $this->security->requirePlatformRole();
        $rows = [];
        $stats = $this->publications->getRecipientStats($this->dateTime->now());
        foreach ($this->publications->findManagementList() as $publication) {
            $publicationStats = $stats[(int) $publication->getId()] ?? ['recipients' => 0, 'views' => 0, 'expired' => 0];
            $rows[] = $this->serialize($publication) + ['recipientCount' => $publicationStats['recipients'], 'viewCount' => $publicationStats['views'], 'expiredCount' => $publicationStats['expired']];
        }
        return $rows;
    }

    public function saveDraft(PublicationDraftDto $dto, ?string $uuid = null): array
    {
        $actor = $this->security->requirePlatformRole();
        $publication = $uuid === null ? new Publication() : $this->find($uuid);
        if ($publication->getStatus() !== PublicationStatus::DRAFT) {
            throw new BadRequestHttpException('Seuls les brouillons peuvent être modifiés.');
        }
        $category = PublicationCategory::tryFrom($dto->category);
        $audience = PublicationAudience::tryFrom($dto->audience);
        if ($category === null || $audience === null) throw new BadRequestHttpException('Catégorie ou audience invalide.');
        $roles = [];
        if ($audience === PublicationAudience::ROLES) {
            $allowed = ['super_admin', 'patron', 'admin_immobilier', 'admin_ville'];
            if ($dto->audienceRoles === [] || array_filter($dto->audienceRoles, static fn (mixed $role): bool => !is_string($role) || !in_array($role, $allowed, true)) !== []) throw new BadRequestHttpException('Sélectionnez au moins un rôle valide.');
            $roles = array_values(array_unique($dto->audienceRoles));
        } else {
            $roles = [];
        }
        $organization = null;
        if ($audience === PublicationAudience::ORGANIZATION) {
            if (!$dto->organizationUuid) throw new BadRequestHttpException('Une organisation doit être sélectionnée.');
            $organization = $this->organizations->findOneByUuid(Uuid::fromString($dto->organizationUuid));
            if (!$organization) throw new NotFoundHttpException('Organisation introuvable.');
        }
        $scheduledAt = $this->parseOptionalDate($dto->scheduledAt, 'date de publication');
        $endsAt = $this->parseOptionalDate($dto->endsAt, 'date de fin');
        if ($endsAt !== null && $endsAt <= ($scheduledAt ?? $this->dateTime->now())) throw new BadRequestHttpException('La date de fin doit suivre la date de publication.');

        $publication->setTitle(trim($dto->title))->setContentBlocks($this->toBlocks($dto->content))
            ->setCategory($category)->setAudience($audience)->setAudienceRoles($roles)
            ->setOrganization($organization)->setScheduledAt($scheduledAt)->setEndsAt($endsAt)
            ->setCreatedBy($actor)->setUpdatedAt($this->dateTime->now());
        $this->entityManager->persist($publication);
        $this->entityManager->flush();
        $this->audit->log($uuid === null ? 'CREATE' : 'UPDATE', 'Publication', (int) $publication->getId(), null, $actor, null, ['uuid' => $publication->getUuid()->toRfc4122(), 'status' => 'draft']);
        return $this->serialize($publication);
    }

    public function publish(string $uuid): array
    {
        $actor = $this->security->requirePlatformRole();
        $publication = $this->find($uuid);
        if ($publication->getStatus() !== PublicationStatus::DRAFT) throw new BadRequestHttpException('Cette publication n’est plus un brouillon.');
        $now = $this->dateTime->now();
        $availableAt = $publication->getScheduledAt() ?? $now;
        if ($availableAt < $now) $availableAt = $now;
        if ($publication->getEndsAt() !== null && $publication->getEndsAt() <= $availableAt) throw new BadRequestHttpException('La date de fin doit suivre la publication.');
        $publication->setPublishedAt($availableAt)->setStatus(PublicationStatus::PUBLISHED)->setUpdatedAt($now);
        foreach ($this->publications->findRecipients($publication) as $user) {
            $recipient = (new PublicationRecipient())->setPublication($publication)->setUser($user)->setAvailableAt($availableAt);
            $this->entityManager->persist($recipient);
        }
        $this->entityManager->flush();
        $this->audit->log('PUBLISH', 'Publication', (int) $publication->getId(), null, $actor, null, ['uuid' => $uuid, 'recipientCount' => count($this->publications->findRecipients($publication))]);
        return $this->serialize($publication);
    }

    public function changeStatus(string $uuid, PublicationStatus $status): array
    {
        $actor = $this->security->requirePlatformRole();
        $publication = $this->find($uuid);
        if (!in_array($status, [PublicationStatus::ARCHIVED, PublicationStatus::WITHDRAWN], true) || $publication->getStatus() !== PublicationStatus::PUBLISHED) {
            throw new BadRequestHttpException('Transition de publication invalide.');
        }
        $now = $this->dateTime->now();
        $publication->setStatus($status)->setUpdatedAt($now);
        if ($status === PublicationStatus::WITHDRAWN) {
            foreach ($this->entityManager->getRepository(PublicationRecipient::class)->findBy(['publication' => $publication, 'withdrawnAt' => null]) as $recipient) $recipient->setWithdrawnAt($now);
        }
        $this->entityManager->flush();
        $this->audit->log($status === PublicationStatus::ARCHIVED ? 'ARCHIVE' : 'WITHDRAW', 'Publication', (int) $publication->getId(), null, $actor, null, ['uuid' => $uuid]);
        return $this->serialize($publication);
    }

    /** @param list<UploadedFile> $files @param list<string> $captions */
    public function addImages(string $uuid, array $files, array $captions): array
    {
        $this->security->requirePlatformRole();
        $publication = $this->find($uuid);
        if ($publication->getStatus() !== PublicationStatus::DRAFT) throw new BadRequestHttpException('Les images ne peuvent être ajoutées qu’à un brouillon.');
        if (count($files) < 1 || count($files) + count($publication->getImages()) > 8) throw new BadRequestHttpException('Une publication accepte au maximum 8 images.');
        $images = $publication->getImages();
        foreach ($files as $index => $file) {
            try {
                $path = $this->uploads->upload($file, 'publications');
            } catch (\InvalidArgumentException $exception) {
                throw new BadRequestHttpException($exception->getMessage(), $exception);
            }
            $images[] = ['path' => $path, 'caption' => mb_substr(trim($captions[$index] ?? ''), 0, 240)];
        }
        $publication->setImages($images)->setUpdatedAt($this->dateTime->now());
        $this->entityManager->flush();
        return $this->serialize($publication);
    }

    /** @return list<array<string, mixed>> */
    public function activeForCurrentUser(): array
    {
        $now = $this->dateTime->now();
        return array_map(fn (PublicationRecipient $recipient): array => $this->serialize($recipient->getPublication()) + [
            'firstViewedAt' => $recipient->getFirstViewedAt()?->format(DATE_ATOM),
            'availableAt' => $recipient->getAvailableAt()->format(DATE_ATOM),
        ], $this->publications->findActiveForUser($this->security->getCurrentUser(), $now));
    }

    public function openForCurrentUser(string $uuid): array
    {
        $user = $this->security->getCurrentUser();
        $recipient = $this->publications->findRecipient($user, $uuid);
        if (!$recipient || $recipient->getWithdrawnAt() !== null || $recipient->getPublication()->getStatus() !== PublicationStatus::PUBLISHED) throw new NotFoundHttpException('Publication introuvable.');
        if ($recipient->getFirstViewedAt() === null) {
            $recipient->setFirstViewedAt($this->dateTime->now());
            $this->entityManager->flush();
        }
        return $this->serialize($recipient->getPublication()) + ['firstViewedAt' => $recipient->getFirstViewedAt()?->format(DATE_ATOM)];
    }

    private function find(string $uuid): Publication
    {
        $publication = $this->publications->findOneBy(['uuid' => Uuid::fromString($uuid)]);
        if (!$publication) throw new NotFoundHttpException('Publication introuvable.');
        return $publication;
    }

    private function parseOptionalDate(?string $value, string $field): ?\DateTimeImmutable
    {
        if ($value === null || trim($value) === '') return null;
        $date = $this->dateTime->parseDateTime($value);
        if (!$date) throw new BadRequestHttpException('La '.$field.' est invalide.');
        return $date;
    }

    /** Convertit le texte brut en blocs sûrs sans stocker de HTML contrôlé par le client. */
    private function toBlocks(string $content): array
    {
        $blocks = [];
        foreach (preg_split('/\R/u', trim($content)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (preg_match('/^[-*]\s+(.+)$/u', $line, $match)) $blocks[] = ['type' => 'bullet', 'text' => $match[1]];
            elseif (preg_match('/^\d+[.)]\s+(.+)$/u', $line, $match)) $blocks[] = ['type' => 'ordered', 'text' => $match[1]];
            else $blocks[] = ['type' => 'paragraph', 'text' => $line];
        }
        if ($blocks === []) throw new BadRequestHttpException('Le contenu de la publication est obligatoire.');
        return $blocks;
    }

    private function serialize(Publication $publication): array
    {
        return [
            'uuid' => $publication->getUuid()->toRfc4122(), 'title' => $publication->getTitle(),
            'contentBlocks' => $publication->getContentBlocks(), 'category' => $publication->getCategory()->value,
            'audience' => $publication->getAudience()->value, 'audienceRoles' => $publication->getAudienceRoles(),
            'organization' => $publication->getOrganization() ? ['uuid' => $publication->getOrganization()->getUuid()->toRfc4122(), 'name' => $publication->getOrganization()->getName()] : null,
            'scheduledAt' => $publication->getScheduledAt()?->format(DATE_ATOM), 'publishedAt' => $publication->getPublishedAt()?->format(DATE_ATOM),
            'endsAt' => $publication->getEndsAt()?->format(DATE_ATOM), 'status' => $publication->getStatus()->value,
            'images' => array_map(fn (array $image): array => $image + ['url' => $this->uploads->getPublicUrl($image['path'])], $publication->getImages()),
            'createdAt' => $publication->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
