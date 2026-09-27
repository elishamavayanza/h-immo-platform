<?php

declare(strict_types=1);

namespace App\Service\Property;

use App\DataMapper\Property\UnitDataMapper;
use App\Dto\Feedback;
use App\Dto\Request\PaginationQuery;
use App\Dto\Request\Property\UnitRequest;
use App\Dto\Response\PaginatedResponse;
use App\Entity\Property\Building;
use App\Entity\Property\Unit;
use App\Repository\Property\BuildingRepository;
use App\Repository\Property\UnitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class UnitService
{
    public function __construct(
        private UnitRepository $unitRepository,
        private BuildingRepository $buildingRepository,
        private UnitDataMapper $unitDataMapper,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
    ) {
    }

    public function create(UnitRequest $request): Feedback
    {
        $feedback = new Feedback();

        $violations = $this->validator->validate($request, groups: ['create']);
        if (count($violations) > 0) {
            return $feedback->bind($violations)
                ->setErrorFlushDescription('Les données soumises sont invalides.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $building = $this->buildingRepository->findOneBy(['uuid' => $request->buildingUuid]);
        if (!$building instanceof Building) {
            return $feedback->addError('buildingUuid', 'Le bâtiment spécifié n\'existe pas.')
                ->setErrorFlushDescription('Impossible de créer l\'unité locative.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        if ($this->unitRepository->findOneBy(['building' => $building, 'reference' => $request->reference])) {
            return $feedback->addError('reference', 'Cette référence existe déjà pour ce bâtiment.')
                ->setErrorFlushDescription('Conflit d\'unicité détecté.')
                ->setStatus(409)
                ->autoInitFlush();
        }

        $unit = $this->unitDataMapper->toEntity($request, $building);
        $this->entityManager->persist($unit);
        $this->entityManager->flush();

        return $feedback
            ->setData($this->unitDataMapper->toResponse($unit))
            ->setFlushDescription('L\'unité locative a été créée avec succès.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    public function update(string $uuid, UnitRequest $request): Feedback
    {
        $feedback = new Feedback();

        $unit = $this->unitRepository->findOneBy(['uuid' => $uuid]);
        if (!$unit instanceof Unit) {
            return $feedback->setErrorFlushDescription('L\'unité locative demandée n\'existe pas.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $violations = $this->validator->validate($request, groups: ['update']);
        if (count($violations) > 0) {
            return $feedback->bind($violations)
                ->setErrorFlushDescription('Les données de mise à jour sont invalides.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        if ($request->reference !== null && $request->reference !== $unit->getReference()) {
            $existing = $this->unitRepository->findOneBy([
                'building' => $unit->getBuilding(),
                'reference' => $request->reference,
            ]);
            if ($existing !== null) {
                return $feedback->addError('reference', 'Cette référence existe déjà pour ce bâtiment.')
                    ->setErrorFlushDescription('Conflit d\'unicité détecté.')
                    ->setStatus(409)
                    ->autoInitFlush();
            }
        }

        $this->unitDataMapper->updateEntity($unit, $request);
        $this->entityManager->flush();

        return $feedback
            ->setData($this->unitDataMapper->toResponse($unit))
            ->setFlushDescription('L\'unité locative a été mise à jour avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function getByUuid(string $uuid): Feedback
    {
        $feedback = new Feedback();

        $unit = $this->unitRepository->findOneBy(['uuid' => $uuid]);
        if (!$unit instanceof Unit) {
            return $feedback->setErrorFlushDescription('L\'unité locative demandée n\'existe pas.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        return $feedback
            ->setData($this->unitDataMapper->toResponse($unit))
            ->setFlushDescription('Détails de l\'unité locative récupérés.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function list(PaginationQuery $query): Feedback
    {
        $feedback = new Feedback();

        $paginator = $this->unitRepository->findPaginated($query);
        $totalItems = count($paginator);
        $items = [];

        foreach ($paginator as $unit) {
            $items[] = $this->unitDataMapper->toResponse($unit);
        }

        $paginatedResponse = new PaginatedResponse(
            items: $items,
            totalItems: $totalItems,
            currentPage: $query->page,
            limit: $query->limit,
            totalPages: (int) ceil($totalItems / $query->limit)
        );

        return $feedback
            ->setData($paginatedResponse)
            ->setFlushDescription('Liste des unités locatives récupérée avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function delete(string $uuid): Feedback
    {
        $feedback = new Feedback();

        $unit = $this->unitRepository->findOneBy(['uuid' => $uuid]);
        if (!$unit instanceof Unit) {
            return $feedback->setErrorFlushDescription('L\'unité locative demandée n\'existe pas.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $unit->setDeletedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $feedback
            ->setFlushDescription('L\'unité locative a été supprimée avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }
}
