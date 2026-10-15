<?php

declare(strict_types=1);

namespace App\Service\System;

use App\Entity\Expense\Expense;
use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Entity\Property\Building;
use App\Entity\Property\City;
use App\Entity\Property\Parcel;
use App\Entity\Property\Unit;
use App\Entity\Rental\Lease;
use App\Entity\Rental\Payment;
use App\Entity\System\PrivateDocument;
use App\Enum\DocumentCategory;
use App\Enum\DocumentEntityType;
use App\Enum\OrganizationRole;
use App\Exception\AccessDeniedException;
use App\Repository\Expense\ExpenseRepository;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Property\BuildingRepository;
use App\Repository\Property\CityRepository;
use App\Repository\Property\ParcelRepository;
use App\Repository\Property\UnitRepository;
use App\Repository\Rental\LeaseRepository;
use App\Repository\Rental\PaymentRepository;
use App\Repository\System\PrivateDocumentRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\System\DateTimeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final readonly class PrivateDocumentService
{
    public function __construct(
        private PrivateDocumentRepository $documents,
        private OrganizationRepository $organizations,
        private CityRepository $cities,
        private LeaseRepository $leases,
        private PaymentRepository $payments,
        private ExpenseRepository $expenses,
        private ParcelRepository $parcels,
        private BuildingRepository $buildings,
        private UnitRepository $units,
        private SecurityServiceInterface $security,
        private PrivateDocumentStorage $storage,
        private EntityManagerInterface $entityManager,
        private DateTimeService $dateTime,
        private AuditLogService $auditLog,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(User $user, string $organizationUuid, ?string $cityUuid, ?string $type, ?string $relatedUuid): array
    {
        $organization = $this->organization($organizationUuid);
        $this->requireMembership($user, $organization);
        $this->security->checkOrganizationAccess($organization, SecurityAction::VIEW_DOCUMENT);
        if ($type !== null && DocumentEntityType::tryFrom($type) === null) {
            throw new \InvalidArgumentException('Type de ressource invalide.');
        }
        if ($relatedUuid !== null && $type === null) {
            throw new \InvalidArgumentException('Le type de ressource est requis avec son identifiant.');
        }

        $city = $cityUuid !== null ? $this->city($cityUuid, $organization) : null;
        if ($city !== null) {
            $this->security->checkCityAccess($city, SecurityAction::VIEW_DOCUMENT);
        } elseif ($this->security->getOrganizationRole($user, $organization) === OrganizationRole::ADMIN_VILLE) {
            throw new \InvalidArgumentException('Sélectionnez une ville qui vous est attribuée.');
        }

        if ($relatedUuid !== null && $type !== null) {
            $source = $this->resolveSource(DocumentEntityType::from($type), $relatedUuid);
            if ($source !== null) {
                if ($source['organization']->getId() !== $organization->getId()) {
                    throw new AccessDeniedException('La ressource liée n’appartient pas à cette organisation.');
                }

                $this->security->checkCityAccess($source['city'], SecurityAction::VIEW_DOCUMENT);
                $this->checkSourceAccess($source['resource'], $source['viewAction']);
            }
        }

        return array_map($this->toArray(...), $this->documents->findVisible(
            $organization,
            $city,
            $type !== null ? DocumentEntityType::from($type) : null,
            $relatedUuid,
        ));
    }

    /** @return array<string, mixed> */
    public function upload(
        User $user,
        string $organizationUuid,
        string $relatedType,
        string $relatedUuid,
        string $category,
        ?string $description,
        UploadedFile $file,
    ): array {
        $type = DocumentEntityType::tryFrom($relatedType);
        $categoryEnum = DocumentCategory::tryFrom($category);
        if ($type === null || $categoryEnum === null) {
            throw new \InvalidArgumentException('La catégorie ou la ressource liée est invalide.');
        }
        $expectedCategory = match ($type) {
            DocumentEntityType::LEASE => DocumentCategory::LEASE_CONTRACT,
            DocumentEntityType::PAYMENT => DocumentCategory::PAYMENT_RECEIPT,
            DocumentEntityType::EXPENSE => DocumentCategory::INVOICE,
            default => DocumentCategory::OTHER,
        };
        if ($categoryEnum !== $expectedCategory && $categoryEnum !== DocumentCategory::OTHER) {
            throw new \InvalidArgumentException('La catégorie choisie ne correspond pas à la ressource liée.');
        }
        $source = $this->resolveSource($type, $relatedUuid);
        if ($source === null || !$source['city'] instanceof City) {
            throw new \InvalidArgumentException('La ressource liée est introuvable.');
        }
        $organization = $this->organization($organizationUuid);
        $this->requireMembership($user, $organization);
        if ($source['organization']->getId() !== $organization->getId()) {
            throw new \InvalidArgumentException('La ressource ne fait pas partie de cette organisation.');
        }

        $this->security->checkOrganizationAccess($organization, SecurityAction::CREATE_DOCUMENT);
        $this->security->checkCityAccess($source['city'], SecurityAction::CREATE_DOCUMENT);
        $this->checkSourceAccess($source['resource'], $source['viewAction']);

        $stored = $this->storage->store($file, $organization->getUuid());
        $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?: 'document';
        $originalName = mb_substr($originalName, 0, 180);
        $description = $description !== null ? trim($description) : null;
        if ($description !== null && $description === '') {
            $description = null;
        }
        if ($description !== null && mb_strlen($description) > 500) {
            $this->storage->delete($stored['storageName']);
            throw new \InvalidArgumentException('La description ne peut pas dépasser 500 caractères.');
        }

        $document = (new PrivateDocument())
            ->setOrganization($organization)
            ->setCity($source['city'])
            ->setUploadedBy($user)
            ->setCategory($categoryEnum)
            ->setRelatedType($type)
            ->setRelatedUuid($relatedUuid)
            ->setOriginalName($originalName)
            ->setStorageName($stored['storageName'])
            ->setMimeType($stored['mimeType'])
            ->setFileSize($stored['size'])
            ->setDescription($description);
        try {
            $this->entityManager->persist($document);
            $this->entityManager->flush();
        } catch (\Throwable $error) {
            $this->storage->delete($stored['storageName']);
            throw $error;
        }

        $documentId = $document->getId() ?? throw new \LogicException('Le document n’a pas reçu son identifiant interne.');
        $this->auditLog->log('UPLOAD_PRIVATE_DOCUMENT', 'private_document', $documentId, $organization, $user, null, [
            'category' => $categoryEnum->value,
            'relatedType' => $type->value,
            'relatedUuid' => $relatedUuid,
            'fileSize' => $stored['size'],
        ]);

        return $this->toArray($document);
    }

    public function download(User $user, string $uuid): array
    {
        $document = $this->document($uuid);
        $this->assertDocumentAccess($user, $document, SecurityAction::VIEW_DOCUMENT);
        $path = $this->storage->resolve($document->getStorageName());
        if ($path === null) {
            throw new \RuntimeException('Le fichier privé est indisponible.');
        }

        return ['path' => $path, 'name' => $document->getOriginalName(), 'mimeType' => $document->getMimeType()];
    }

    public function delete(User $user, string $uuid): void
    {
        $document = $this->document($uuid);
        $this->assertDocumentAccess($user, $document, SecurityAction::DELETE_DOCUMENT);
        $oldValues = [
            'category' => $document->getCategory()->value,
            'relatedType' => $document->getRelatedType()->value,
            'relatedUuid' => $document->getRelatedUuid(),
            'storageName' => $document->getStorageName(),
        ];
        $document->setDeletedAt($this->dateTime->now());
        $documentId = $document->getId() ?? throw new \LogicException('Le document n’a pas reçu son identifiant interne.');
        $this->auditLog->log(
            'SOFT_DELETE_PRIVATE_DOCUMENT',
            PrivateDocument::class,
            $documentId,
            $document->getOrganization(),
            $user,
            $oldValues,
            ['deletedAt' => $document->getDeletedAt()?->format(DATE_ATOM)],
            false,
        );
        $this->entityManager->flush();
    }

    /** @return array{organization: Organization, city: City, viewAction: SecurityAction, resource: object}|null */
    private function resolveSource(DocumentEntityType $type, string $uuid): ?array
    {
        try {
            $id = Uuid::fromString($uuid);
        } catch (\InvalidArgumentException) {
            return null;
        }
        $source = match ($type) {
            DocumentEntityType::LEASE => $this->leases->findOneByUuid($id),
            DocumentEntityType::PAYMENT => $this->payments->findOneByUuid($id),
            DocumentEntityType::EXPENSE => $this->expenses->findOneByUuid($id),
            DocumentEntityType::PARCEL => $this->parcels->findOneByUuid($id),
            DocumentEntityType::BUILDING => $this->buildings->findOneByUuid($id),
            DocumentEntityType::UNIT => $this->units->findOneByUuid($id),
        };
        if ($source === null) {
            return null;
        }

        [$organization, $city, $action] = match (true) {
            $source instanceof Lease => [$source->getOrganization(), $source->getUnit()->getBuilding()->getParcel()->getCity(), SecurityAction::VIEW_LEASE],
            $source instanceof Payment => [$source->getRent()->getLease()->getOrganization(), $source->getRent()->getLease()->getUnit()->getBuilding()->getParcel()->getCity(), SecurityAction::VIEW_PAYMENT],
            $source instanceof Expense => [$source->getOrganization(), $source->getCity(), SecurityAction::VIEW_EXPENSE],
            $source instanceof Parcel => [$source->getCity()->getOrganization(), $source->getCity(), SecurityAction::VIEW_PARCEL],
            $source instanceof Building => [$source->getParcel()->getCity()->getOrganization(), $source->getParcel()->getCity(), SecurityAction::VIEW_BUILDING],
            $source instanceof Unit => [$source->getBuilding()->getParcel()->getCity()->getOrganization(), $source->getBuilding()->getParcel()->getCity(), SecurityAction::VIEW_UNIT],
            default => throw new \LogicException('Type de ressource documentaire non géré.'),
        };

        return ['organization' => $organization, 'city' => $city, 'viewAction' => $action, 'resource' => $source];
    }

    private function checkSourceAccess(object $source, SecurityAction $action): void
    {
        if ($source instanceof Lease) {
            $this->security->checkLeaseAccess($source, $action);
        } elseif ($source instanceof Payment) {
            $this->security->checkPaymentAccess($source, $action);
        } elseif ($source instanceof Expense) {
            $this->security->checkExpenseAccess($source, $action);
        } elseif ($source instanceof Parcel) {
            $this->security->checkParcelAccess($source, $action);
        } elseif ($source instanceof Building) {
            $this->security->checkBuildingAccess($source, $action);
        } elseif ($source instanceof Unit) {
            $this->security->checkUnitAccess($source, $action);
        } else {
            throw new \LogicException('Type de ressource documentaire non géré.');
        }
    }

    private function assertDocumentAccess(User $user, PrivateDocument $document, SecurityAction $action): void
    {
        $this->requireMembership($user, $document->getOrganization());
        $this->security->checkOrganizationAccess($document->getOrganization(), $action);
        if ($document->getCity() !== null) {
            $this->security->checkCityAccess($document->getCity(), $action);
        }
    }

    private function requireMembership(User $user, Organization $organization): void
    {
        if ($this->security->getOrganizationRole($user, $organization) === null) {
            throw new AccessDeniedException('Cet espace métier est réservé aux membres de l’organisation.');
        }
    }

    private function organization(string $uuid): Organization
    {
        try {
            $organization = $this->organizations->findOneByUuid(Uuid::fromString($uuid));
        } catch (\InvalidArgumentException) {
            $organization = null;
        }
        return $organization ?? throw new \InvalidArgumentException('Organisation introuvable.');
    }

    private function city(string $uuid, Organization $organization): City
    {
        try {
            $city = $this->cities->findOneByUuid(Uuid::fromString($uuid));
        } catch (\InvalidArgumentException) {
            $city = null;
        }
        if ($city === null || $city->getOrganization()->getId() !== $organization->getId()) {
            throw new \InvalidArgumentException('Ville introuvable dans cette organisation.');
        }
        return $city;
    }

    private function document(string $uuid): PrivateDocument
    {
        try {
            $document = $this->documents->findOneByPublicUuid(Uuid::fromString($uuid));
        } catch (\InvalidArgumentException) {
            $document = null;
        }
        return $document ?? throw new \InvalidArgumentException('Document introuvable.');
    }

    /** @return array<string, mixed> */
    private function toArray(PrivateDocument $document): array
    {
        return [
            'uuid' => $document->getUuid()->toRfc4122(),
            'organizationUuid' => $document->getOrganization()->getUuid()->toRfc4122(),
            'cityUuid' => $document->getCity()?->getUuid()->toRfc4122(),
            'category' => $document->getCategory()->value,
            'relatedType' => $document->getRelatedType()->value,
            'relatedUuid' => $document->getRelatedUuid(),
            'originalName' => $document->getOriginalName(),
            'mimeType' => $document->getMimeType(),
            'fileSize' => $document->getFileSize(),
            'description' => $document->getDescription(),
            'createdAt' => $document->getCreatedAt()->format(DATE_ATOM),
            'uploadedBy' => $document->getUploadedBy()->getFullName(),
        ];
    }
}
