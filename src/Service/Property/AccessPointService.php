<?php

declare(strict_types=1);

namespace App\Service\Property;

use App\Dto\Feedback;
use App\Dto\Request\Property\AccessPointRequest;
use App\Dto\Response\Property\AccessPointResponse;
use App\Entity\Identity\User;
use App\Entity\Property\AccessPoint;
use App\Entity\Property\Building;
use App\Entity\Property\Unit;
use App\Repository\Property\AccessPointRepository;
use App\Repository\Property\BuildingRepository;
use App\Repository\Property\ParcelRepository;
use App\Repository\Property\UnitRepository;
use App\Repository\Rental\LeaseRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\System\AuditLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Gestion des accès physiques, sans effet sur la disponibilité locative. */
final readonly class AccessPointService
{
    public function __construct(
        private AccessPointRepository $accessPoints,
        private BuildingRepository $buildings,
        private ParcelRepository $parcels,
        private UnitRepository $units,
        private LeaseRepository $leases,
        private SecurityServiceInterface $security,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private AuditLogService $audit,
    ) {}

    public function list(?string $buildingUuid, ?string $parcelUuid): Feedback
    {
        $feedback = new Feedback();
        $building = $buildingUuid !== null ? $this->resolveBuilding($buildingUuid) : null;
        if ($buildingUuid !== null && $building === null) return $feedback->setData([])->setStatus(200)->autoInitFlush();
        $parcel = $parcelUuid !== null ? $this->resolveParcel($parcelUuid) : $building?->getParcel();
        if ($parcel === null || ($building !== null && $building->getParcel()->getId() !== $parcel->getId())) {
            return $feedback->setData([])->setStatus(200)->autoInitFlush();
        }
        $this->security->checkParcelAccess($parcel, SecurityAction::VIEW_PARCEL);
        if ($building !== null) $this->security->checkBuildingAccess($building, SecurityAction::VIEW_UNIT);
        return $feedback->setData(array_map([AccessPointResponse::class, 'fromEntity'], $this->accessPoints->findByParcel($parcel, $building)))
            ->setFlushDescription('Accès du bâtiment récupérés.')->setStatus(200)->autoInitFlush();
    }

    public function create(AccessPointRequest $request, User $actor): Feedback
    {
        $feedback = new Feedback();
        $violations = $this->validator->validate($request);
        if (count($violations) > 0) return $feedback->bind($violations)->setFlushDescriptionWithError('Les données de l’accès sont invalides.')->autoInitFlush();
        [$parcel, $building, $invalidScope] = $this->resolveScope($request);
        if ($invalidScope || $parcel === null) return $feedback->addError('parcelUuid', 'Indiquez une parcelle ou un bâtiment accessible.')->autoInitFlush()->setStatus(404);
        $this->security->checkParcelAccess($parcel, SecurityAction::CREATE_PARCEL);
        if ($building !== null) $this->security->checkBuildingAccess($building, SecurityAction::CREATE_UNIT);
        if ($this->accessPoints->referenceExists($parcel, $request->reference)) return $feedback->addError('reference', 'Cette référence existe déjà sur la parcelle.')->autoInitFlush()->setStatus(409);
        $selectedUnits = $this->resolveUnits($request->unitUuids, $parcel, $building, $feedback);
        if ($selectedUnits === null) return $feedback->setFlushDescriptionWithError('Les espaces sélectionnés doivent appartenir au périmètre de cet accès.')->autoInitFlush();

        $access = (new AccessPoint())->setParcel($parcel)->setBuilding($building)->setReference($request->reference)->setLabel($request->label)
            ->setType($request->type)->setAccessMode($request->accessMode)->setLocationDescription($request->locationDescription)->setUnits($selectedUnits);
        $this->entityManager->persist($access);
        $this->entityManager->flush();
        $this->audit->log('CREATE_ACCESS_POINT', AccessPoint::class, (int) $access->getId(), $parcel->getCity()->getOrganization(), $actor, null, ['reference' => $access->getReference(), 'unitUuids' => array_map(static fn (Unit $unit): string => (string) $unit->getUuid(), $selectedUnits)]);
        return $feedback->setData(AccessPointResponse::fromEntity($access))->setFlushDescription('L’accès a été enregistré.')
            ->setStatus(201)->autoInitFlush()->setStatus(201);
    }

    public function update(string $uuid, AccessPointRequest $request, User $actor): Feedback
    {
        $feedback = new Feedback();
        $access = $this->find($uuid);
        if ($access === null) return $feedback->addError('id', 'Accès introuvable.')->autoInitFlush()->setStatus(404);
        $this->security->checkParcelAccess($access->getParcel(), SecurityAction::UPDATE_PARCEL);
        if ($access->getBuilding() !== null) $this->security->checkBuildingAccess($access->getBuilding(), SecurityAction::UPDATE_UNIT);
        $violations = $this->validator->validate($request);
        if (count($violations) > 0) return $feedback->bind($violations)->setFlushDescriptionWithError('Les données de l’accès sont invalides.')->autoInitFlush();
        [$parcel, $building, $invalidScope] = $this->resolveScope($request);
        if ($invalidScope || $parcel === null || $parcel->getId() !== $access->getParcel()->getId() || $building?->getId() !== $access->getBuilding()?->getId()) return $feedback->addError('parcelUuid', 'Un accès ne peut pas être déplacé vers une autre parcelle ou un autre bâtiment.')->autoInitFlush()->setStatus(422);
        if ($this->accessPoints->referenceExists($parcel, $request->reference, $access)) return $feedback->addError('reference', 'Cette référence existe déjà sur la parcelle.')->autoInitFlush()->setStatus(409);
        $selectedUnits = $this->resolveUnits($request->unitUuids, $parcel, $building, $feedback);
        if ($selectedUnits === null) return $feedback->setFlushDescriptionWithError('Les espaces sélectionnés doivent appartenir au périmètre de cet accès.')->autoInitFlush();
        $oldUnits = $access->getUnits()->toArray();
        $oldIds = array_map(static fn (Unit $unit): int => (int) $unit->getId(), $oldUnits);
        $newIds = array_map(static fn (Unit $unit): int => (int) $unit->getId(), $selectedUnits);
        sort($oldIds); sort($newIds);
        if ($oldIds !== $newIds) {
            $relatedSpaces = [];
            foreach ([...$oldUnits, ...$selectedUnits] as $unit) {
                foreach ($this->units->findRelatedSpaces($unit) as $related) $relatedSpaces[$related->getId()] = $related;
            }
            if ($this->leases->findActiveLeaseForRelatedSpaces(array_values($relatedSpaces)) !== null) {
                return $feedback->addError('unitUuids', 'Les unités desservies ne peuvent pas être modifiées pendant un bail actif.')
                    ->setFlushDescriptionWithError('Clôturez ou transférez les baux concernés avant de restructurer un accès partagé.')
                    ->autoInitFlush()->setStatus(409);
            }
        }
        $old = ['label' => $access->getLabel(), 'unitUuids' => array_map(static fn (Unit $unit): string => (string) $unit->getUuid(), $access->getUnits()->toArray())];
        $access->setReference($request->reference)->setLabel($request->label)->setType($request->type)->setAccessMode($request->accessMode)->setLocationDescription($request->locationDescription)->setUnits($selectedUnits);
        $this->entityManager->flush();
        $this->audit->log('UPDATE_ACCESS_POINT', AccessPoint::class, (int) $access->getId(), $parcel->getCity()->getOrganization(), $actor, $old, ['label' => $access->getLabel(), 'unitUuids' => array_map(static fn (Unit $unit): string => (string) $unit->getUuid(), $selectedUnits)]);
        return $feedback->setData(AccessPointResponse::fromEntity($access))->setFlushDescription('L’accès a été mis à jour.')->setStatus(200)->autoInitFlush();
    }

    public function setEnabled(string $uuid, bool $enabled, User $actor): Feedback
    {
        $feedback = new Feedback();
        $access = $this->find($uuid);
        if ($access === null) return $feedback->addError('id', 'Accès introuvable.')->autoInitFlush()->setStatus(404);
        $this->security->checkParcelAccess($access->getParcel(), SecurityAction::UPDATE_PARCEL);
        if ($access->getBuilding() !== null) $this->security->checkBuildingAccess($access->getBuilding(), SecurityAction::UPDATE_UNIT);
        $access->setEnabled($enabled);
        $this->entityManager->flush();
        $this->audit->log($enabled ? 'ENABLE_ACCESS_POINT' : 'DISABLE_ACCESS_POINT', AccessPoint::class, (int) $access->getId(), $access->getParcel()->getCity()->getOrganization(), $actor, null, ['enabled' => $enabled]);
        return $feedback->setData(AccessPointResponse::fromEntity($access))->setFlushDescription('Le statut de l’accès a été modifié.')->setStatus(200)->autoInitFlush();
    }

    private function resolveUnits(array $uuids, \App\Entity\Property\Parcel $parcel, ?Building $building, Feedback $feedback): ?array
    {
        $selected = [];
        foreach (array_unique($uuids) as $uuid) {
            try { $unit = $this->units->findOneByUuid(Uuid::fromString((string) $uuid)); } catch (\InvalidArgumentException) { $unit = null; }
            if ($unit === null || $unit->getBuilding()->getParcel()->getId() !== $parcel->getId() || ($building !== null && $unit->getBuilding()->getId() !== $building->getId())) {
                $feedback->addError('unitUuids', 'Un espace sélectionné ne dépend pas de ce bâtiment.');
                return null;
            }
            $this->security->checkUnitAccess($unit, SecurityAction::VIEW_UNIT);
            $selected[] = $unit;
        }
        return $selected;
    }

    private function resolveBuilding(string $uuid): ?Building
    {
        try { return $this->buildings->findOneByUuid(Uuid::fromString($uuid)); } catch (\InvalidArgumentException) { return null; }
    }

    private function resolveParcel(string $uuid): ?\App\Entity\Property\Parcel
    {
        try { return $this->parcels->findOneByUuid(Uuid::fromString($uuid)); } catch (\InvalidArgumentException) { return null; }
    }

    /** @return array{0: ?\App\Entity\Property\Parcel, 1: ?Building, 2: bool} */
    private function resolveScope(AccessPointRequest $request): array
    {
        if ($request->buildingUuid === null && $request->parcelUuid === null) return [null, null, true];
        $building = $request->buildingUuid !== null ? $this->resolveBuilding($request->buildingUuid) : null;
        if ($request->buildingUuid !== null && $building === null) return [null, null, true];
        $parcel = $request->parcelUuid !== null ? $this->resolveParcel($request->parcelUuid) : $building?->getParcel();
        if ($parcel === null || ($building !== null && $building->getParcel()->getId() !== $parcel->getId())) return [null, null, true];
        return [$parcel, $building, false];
    }

    private function find(string $uuid): ?AccessPoint
    {
        try { return $this->accessPoints->findOneByUuid(Uuid::fromString($uuid)); } catch (\InvalidArgumentException) { return null; }
    }
}
