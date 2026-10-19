<?php

declare(strict_types=1);

namespace App\Service\Property;

use App\Dto\Feedback;
use App\Dto\Request\Property\BuildingLevelRequest;
use App\Dto\Response\Property\BuildingLevelResponse;
use App\Entity\Identity\User;
use App\Entity\Property\Building;
use App\Entity\Property\BuildingLevel;
use App\Repository\Property\BuildingLevelRepository;
use App\Repository\Property\BuildingRepository;
use App\Repository\Property\UnitRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use App\Service\System\AuditLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class BuildingLevelService
{
    public function __construct(
        private BuildingRepository $buildings,
        private BuildingLevelRepository $levels,
        private UnitRepository $units,
        private SecurityServiceInterface $security,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private AuditLogService $audit,
    ) {}

    public function list(string $buildingUuid): Feedback
    {
        $feedback = new Feedback();
        $building = $this->resolveBuilding($buildingUuid);
        if ($building === null) return $feedback->setData([])->setStatus(200)->autoInitFlush();
        $this->security->checkBuildingAccess($building, SecurityAction::VIEW_UNIT);
        return $feedback->setData(array_map([BuildingLevelResponse::class, 'fromEntity'], $this->levels->findByBuilding($building)))
            ->setFlushDescription('Niveaux du bâtiment récupérés.')->setStatus(200)->autoInitFlush();
    }

    public function create(string $buildingUuid, BuildingLevelRequest $request, User $actor): Feedback
    {
        $feedback = new Feedback();
        $violations = $this->validator->validate($request);
        if (count($violations) > 0) return $feedback->bind($violations)->setFlushDescriptionWithError('Les données du niveau sont invalides.')->autoInitFlush();
        $building = $this->resolveBuilding($buildingUuid);
        if ($building === null) return $feedback->addError('buildingUuid', 'Bâtiment introuvable.')->autoInitFlush()->setStatus(404);
        $this->security->checkBuildingAccess($building, SecurityAction::CREATE_UNIT);
        if ($request->displayOrder > $this->maximumFloor($building)) {
            return $feedback->addError('displayOrder', 'Ce niveau dépasse le nombre d’étages déclaré pour le bâtiment.')
                ->setErrorFlushDescription('Augmentez d’abord le nombre d’étages du bâtiment ou choisissez un numéro inférieur.')
                ->autoInitFlush()->setStatus(422);
        }
        if ($this->levels->referenceExists($building, $request->reference)) return $feedback->addError('reference', 'Cette référence de niveau existe déjà.')->autoInitFlush()->setStatus(409);
        $level = (new BuildingLevel())->setBuilding($building)->setReference($request->reference)->setLabel($request->label)
            ->setDisplayOrder($request->displayOrder)->setDescription($request->description);
        $this->entityManager->persist($level);
        $this->entityManager->flush();
        $this->audit->log('CREATE_BUILDING_LEVEL', BuildingLevel::class, (int) $level->getId(), $building->getParcel()->getCity()->getOrganization(), $actor, null, ['reference' => $level->getReference(), 'label' => $level->getLabel(), 'displayOrder' => $level->getDisplayOrder()]);
        return $feedback->setData(BuildingLevelResponse::fromEntity($level))->setFlushDescription('Le niveau a été créé.')->setStatus(201)->autoInitFlush()->setStatus(201);
    }

    public function update(string $uuid, BuildingLevelRequest $request, User $actor): Feedback
    {
        $feedback = new Feedback();
        $level = $this->findLevel($uuid);
        if ($level === null) return $feedback->addError('id', 'Niveau introuvable.')->autoInitFlush()->setStatus(404);
        $this->security->checkBuildingAccess($level->getBuilding(), SecurityAction::UPDATE_UNIT);
        $violations = $this->validator->validate($request);
        if (count($violations) > 0) return $feedback->bind($violations)->setFlushDescriptionWithError('Les données du niveau sont invalides.')->autoInitFlush();
        if ($request->displayOrder > $this->maximumFloor($level->getBuilding())) {
            return $feedback->addError('displayOrder', 'Ce niveau dépasse le nombre d’étages déclaré pour le bâtiment.')
                ->setErrorFlushDescription('Augmentez d’abord le nombre d’étages du bâtiment ou choisissez un numéro inférieur.')
                ->autoInitFlush()->setStatus(422);
        }
        if ($this->levels->referenceExists($level->getBuilding(), $request->reference, $level)) return $feedback->addError('reference', 'Cette référence de niveau existe déjà.')->autoInitFlush()->setStatus(409);
        $old = ['reference' => $level->getReference(), 'label' => $level->getLabel(), 'displayOrder' => $level->getDisplayOrder(), 'description' => $level->getDescription()];
        $level->setReference($request->reference)->setLabel($request->label)->setDisplayOrder($request->displayOrder)->setDescription($request->description);
        foreach ($this->units->findByLevel($level) as $unit) {
            $unit->setFloor($level->getDisplayOrder())->setFloorLabel($level->getLabel());
        }
        $this->entityManager->flush();
        $this->audit->log('UPDATE_BUILDING_LEVEL', BuildingLevel::class, (int) $level->getId(), $level->getBuilding()->getParcel()->getCity()->getOrganization(), $actor, $old, ['reference' => $level->getReference(), 'label' => $level->getLabel(), 'displayOrder' => $level->getDisplayOrder(), 'description' => $level->getDescription()]);
        return $feedback->setData(BuildingLevelResponse::fromEntity($level))->setFlushDescription('Le niveau a été mis à jour.')->setStatus(200)->autoInitFlush();
    }

    private function resolveBuilding(string $uuid): ?Building
    {
        try { return $this->buildings->findOneByUuid(Uuid::fromString($uuid)); } catch (\InvalidArgumentException) { return null; }
    }

    private function findLevel(string $uuid): ?BuildingLevel
    {
        try { return $this->levels->findOneByUuid(Uuid::fromString($uuid)); } catch (\InvalidArgumentException) { return null; }
    }

    private function maximumFloor(Building $building): int
    {
        return $building->getNumberOfFloors()
            ?? max(0, $this->units->highestFloor($building), $this->levels->highestFloor($building));
    }
}
