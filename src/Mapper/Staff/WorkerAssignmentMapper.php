<?php

declare(strict_types=1);

namespace App\Mapper\Staff;

use App\Dto\Request\Staff\WorkerAssignmentRequest;
use App\Dto\Response\Staff\WorkerAssignmentResponse;
use App\Entity\Staff\WorkerAssignment;

/**
 * WorkerAssignmentMapper
 *
 * Package : Staff Management — Service de mapping d'entité
 *
 * Assure la conversion bidirectionnelle entre l'entité WorkerAssignment
 * et les objets DTO de requête et de réponse associés.
 * Les relations (worker, city, parcel/building/unit) sont positionnées
 * par le service appelant après résolution.
 */
final class WorkerAssignmentMapper
{
    public function copyToEntity(WorkerAssignmentRequest $request, WorkerAssignment $assignment): WorkerAssignment
    {
        if ($request->role !== null) {
            $assignment->setRole(\App\Enum\WorkerRole::from($request->role));
        }
        if ($request->monthlySalary !== null) {
            $assignment->setMonthlySalary($request->monthlySalary);
        }
        if ($request->currency !== null) {
            $assignment->setCurrency($request->currency);
        }
        if ($request->startDate !== null) {
            $assignment->setStartDate($request->startDate);
        }
        if ($request->endDate !== null) {
            $assignment->setEndDate($request->endDate);
        }
        if ($request->notes !== null) {
            $assignment->setNotes($request->notes);
        }

        // worker, city, parcel/building/unit sont positionnés par le service

        return $assignment;
    }

    public function toResponse(WorkerAssignment $assignment): WorkerAssignmentResponse
    {
        return WorkerAssignmentResponse::fromEntity($assignment);
    }
}