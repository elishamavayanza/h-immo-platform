<?php

declare(strict_types=1);

namespace App\Service\Property;

use App\DataMapper\Property\ParcelDataMapper;
use App\Dto\Feedback;
use App\Dto\Request\PaginationQuery;
use App\Dto\Request\Property\ParcelRequest;
use App\Dto\Response\PaginatedResponse;
use App\Entity\Property\City;
use App\Entity\Property\Parcel;
use App\Repository\Property\CityRepository;
use App\Repository\Property\ParcelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class ParcelService
{
    public function __construct(
        private ParcelRepository $parcelRepository,
        private CityRepository $cityRepository,
        private ParcelDataMapper $parcelDataMapper,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
    ) {
    }

    public function create(ParcelRequest $request): Feedback
    {
        $feedback = new Feedback();

        $violations = $this->validator->validate($request, groups: ['create']);
        if (count($violations) > 0) {
            return $feedback->bind($violations)
                ->setErrorFlushDescription('Les données soumises sont invalides.')
                ->setStatus(422)
                ->autoInitFlush();
        }

        $city = $this->cityRepository->findOneBy(['uuid' => $request->cityUuid]);
        if (!$city instanceof City) {
            return $feedback->addError('cityUuid', 'La ville spécifiée n\'existe pas.')
                ->setErrorFlushDescription('Impossible de créer la parcelle.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        if ($this->parcelRepository->findOneBy(['city' => $city, 'reference' => $request->reference])) {
            return $feedback->addError('reference', 'Cette référence existe déjà pour cette ville.')
                ->setErrorFlushDescription('Conflit d\'unicité détecté.')
                ->setStatus(409)
                ->autoInitFlush();
        }

        $parcel = $this->parcelDataMapper->toEntity($request, $city);
        $this->entityManager->persist($parcel);
        $this->entityManager->flush();

        return $feedback
            ->setData($this->parcelDataMapper->toResponse($parcel))
            ->setFlushDescription('La parcelle a été créée avec succès.')
            ->setStatus(201)
            ->autoInitFlush();
    }

    public function update(string $uuid, ParcelRequest $request): Feedback
    {
        $feedback = new Feedback();

        $parcel = $this->parcelRepository->findOneBy(['uuid' => $uuid]);
        if (!$parcel instanceof Parcel) {
            return $feedback->setErrorFlushDescription('La parcelle demandée n\'existe pas.')
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

        if ($request->reference !== null && $request->reference !== $parcel->getReference()) {
            $existing = $this->parcelRepository->findOneBy([
                'city' => $parcel->getCity(),
                'reference' => $request->reference,
            ]);
            if ($existing !== null) {
                return $feedback->addError('reference', 'Cette référence existe déjà pour cette ville.')
                    ->setErrorFlushDescription('Conflit d\'unicité détecté.')
                    ->setStatus(409)
                    ->autoInitFlush();
            }
        }

        $this->parcelDataMapper->updateEntity($parcel, $request);
        $this->entityManager->flush();

        return $feedback
            ->setData($this->parcelDataMapper->toResponse($parcel))
            ->setFlushDescription('La parcelle a été mise à jour avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function getByUuid(string $uuid): Feedback
    {
        $feedback = new Feedback();

        $parcel = $this->parcelRepository->findOneBy(['uuid' => $uuid]);
        if (!$parcel instanceof Parcel) {
            return $feedback->setErrorFlushDescription('La parcelle demandée n\'existe pas.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        return $feedback
            ->setData($this->parcelDataMapper->toResponse($parcel))
            ->setFlushDescription('Détails de la parcelle récupérés.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function list(PaginationQuery $query): Feedback
    {
        $feedback = new Feedback();

        $paginator = $this->parcelRepository->findPaginated($query);
        $totalItems = count($paginator);
        $items = [];

        foreach ($paginator as $parcel) {
            $items[] = $this->parcelDataMapper->toResponse($parcel);
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
            ->setFlushDescription('Liste des parcelles récupérée avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }

    public function delete(string $uuid): Feedback
    {
        $feedback = new Feedback();

        $parcel = $this->parcelRepository->findOneBy(['uuid' => $uuid]);
        if (!$parcel instanceof Parcel) {
            return $feedback->setErrorFlushDescription('La parcelle demandée n\'existe pas.')
                ->setStatus(404)
                ->autoInitFlush();
        }

        $parcel->setDeletedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $feedback
            ->setFlushDescription('La parcelle a été supprimée avec succès.')
            ->setStatus(200)
            ->autoInitFlush();
    }
}
