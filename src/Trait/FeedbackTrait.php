<?php

declare(strict_types=1);

namespace App\Trait;

use App\Dto\Feedback;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Trait FeedbackTrait
 *
 * Fournit des méthodes utilitaires aux contrôleurs Symfony
 * pour générer rapidement des réponses sous forme de Feedback JSON.
 */
trait FeedbackTrait
{
    /**
     * Retourne une réponse de succès (HTTP 200 par défaut ou 201).
     */
    protected function respondSuccess(
        mixed $data = null,
        ?string $message = 'Opération effectuée avec succès.',
        int $statusCode = 200
    ): JsonResponse {
        $feedback = (new Feedback())
            ->setData($data)
            ->setFlushDescription($message)
            ->setStatus($statusCode)
            ->autoInitFlush();

        return $this->json($feedback, $statusCode);
    }

    /**
     * Retourne une réponse d'erreur de validation suite à un contrôle Symfony Validator (HTTP 422).
     */
    protected function respondWithViolations(
        ConstraintViolationListInterface $violations,
        string $message = 'Les données soumises sont invalides.'
    ): JsonResponse {
        $feedback = (new Feedback())
            ->bind($violations)
            ->setFlushDescriptionWithError($message)
            ->setStatus(422)
            ->autoInitFlush();

        return $this->json($feedback, 422);
    }

    /**
     * Retourne une réponse d'erreur métier personnalisée (HTTP 400 ou autre).
     */
    protected function respondError(
        string $message,
        string $field = Feedback::FLUSH_DESCRIPTION,
        int $statusCode = 400
    ): JsonResponse {
        $feedback = (new Feedback())
            ->addError($field, $message)
            ->setFlushDescriptionWithError($message)
            ->setStatus($statusCode)
            ->autoInitFlush();

        return $this->json($feedback, $statusCode);
    }
}
