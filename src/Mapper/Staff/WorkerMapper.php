<?php

declare(strict_types=1);

namespace App\Mapper\Staff;

use App\Dto\Request\Staff\WorkerRequest;
use App\Dto\Response\Staff\WorkerResponse;
use App\Entity\Staff\Worker;

/**
 * WorkerMapper
 *
 * Package : Staff Management — Service de mapping d'entité
 *
 * Assure la conversion bidirectionnelle entre l'entité Worker
 * et les objets DTO de requête et de réponse associés.
 * L'Organization est positionnée par le service appelant.
 */
final class WorkerMapper
{
    public function copyToEntity(WorkerRequest $request, Worker $worker): Worker
    {
        if ($request->fullName !== null) {
            $worker->setFullName($request->fullName);
        }
        if ($request->phone !== null) {
            $worker->setPhone($request->phone);
        }
        if ($request->email !== null) {
            $worker->setEmail($request->email);
        }
        if ($request->nationalId !== null) {
            $worker->setNationalId($request->nationalId);
        }
        if ($request->address !== null) {
            $worker->setAddress($request->address);
        }
        if ($request->notes !== null) {
            $worker->setNotes($request->notes);
        }

        // L'Organization est positionnée par le service

        return $worker;
    }

    public function toResponse(Worker $worker): WorkerResponse
    {
        return WorkerResponse::fromEntity($worker);
    }
}