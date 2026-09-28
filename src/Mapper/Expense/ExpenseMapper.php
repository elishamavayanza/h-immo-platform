<?php

declare(strict_types=1);

namespace App\Mapper\Expense;

use App\Dto\Request\Expense\ExpenseRequest;
use App\Dto\Response\Expense\ExpenseResponse;
use App\Entity\Expense\Expense;

/**
 * ExpenseMapper
 *
 * Package : Expense Management — Service de mapping d'entité
 *
 * Assure la conversion bidirectionnelle entre l'entité Expense
 * et les objets DTO de requête et de réponse associés.
 * L'Organization et le createdBy sont positionnés par le service appelant.
 */
final class ExpenseMapper
{
    public function copyToEntity(ExpenseRequest $request, Expense $expense): Expense
    {
        if ($request->cityUuid !== null) {
            // La ville est positionnée par le service après résolution
        }
        if ($request->category !== null) {
            $expense->setCategory(\App\Enum\ExpenseCategory::from($request->category));
        }
        if ($request->amount !== null) {
            $expense->setAmount($request->amount);
        }
        if ($request->currency !== null) {
            $expense->setCurrency($request->currency);
        }
        if ($request->expenseDate !== null) {
            $expense->setExpenseDate($request->expenseDate);
        }
        if ($request->periodStart !== null) {
            $expense->setPeriodStart($request->periodStart);
        }
        if ($request->periodEnd !== null) {
            $expense->setPeriodEnd($request->periodEnd);
        }
        if ($request->method !== null) {
            $expense->setMethod(\App\Enum\PaymentMethod::from($request->method));
        }
        if ($request->supplier !== null) {
            $expense->setSupplier($request->supplier);
        }
        if ($request->reference !== null) {
            $expense->setReference($request->reference);
        }
        if ($request->receiptNumber !== null) {
            $expense->setReceiptNumber($request->receiptNumber);
        }
        if ($request->notes !== null) {
            $expense->setNotes($request->notes);
        }

        // parcel/building/unit/worker sont positionnés par le service après résolution
        // city/organization/createdBy sont positionnés par le service

        return $expense;
    }

    public function toResponse(Expense $expense): ExpenseResponse
    {
        return ExpenseResponse::fromEntity($expense);
    }
}