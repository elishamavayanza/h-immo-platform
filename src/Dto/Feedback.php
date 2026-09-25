<?php

declare(strict_types=1);

namespace App\Dto;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Feedback
 *
 * Enveloppe standardisée pour les réponses de l'API.
 * Permet de transporter les données applicatives, les messages flash,
 * ainsi que les erreurs de validation et avertissements métier.
 */
#[OA\Schema(
    title: 'Feedback',
    description: 'Enveloppe globale de réponse API contenant le résultat, le statut et les erreurs de validation.'
)]
class Feedback implements \JsonSerializable
{
    public const FLUSH_DESCRIPTION = '_flush_description_';

    #[OA\Property(
        description: 'Titre ou résumé du message global de retour (ex: notification toast/flash)',
        example: "Succès d'exécution de l'opération",
        nullable: true
    )]
    private ?string $flush = null;

    #[OA\Property(
        description: 'Description détaillée de l\'opération ou de la cause de l\'échec',
        example: 'Le contrat de bail a été crée avec succès.',
        nullable: true
    )]
    private ?string $flushDescription = null;

    #[OA\Property(
        description: 'Code de statut HTTP associé au traitement métier',
        example: 200
    )]
    private int $status = 200;

    #[OA\Property(
        description: 'Dictionnaire des erreurs de validation ou de traitement associées aux champs (clé = nom du champ, valeur = message)',
        type: 'object',
        example: ['monthlyRent' => 'Le montant doit être supérieur à zéro.']
    )]
    private array $errors = [];

    #[OA\Property(
        description: 'Dictionnaire des avertissements non-bloquants (clé = nom du champ, valeur = message)',
        type: 'object',
        example: ['depositAmount' => 'La garantie locative n\'a pas encore été perçue.']
    )]
    private array $warnings = [];

    #[OA\Property(
        description: 'Charge utile de la réponse (DTO de réponse, tableau d\'objets, ou donnée scalaire)',
        nullable: true
    )]
    private mixed $data = null;

    public function getFlush(): ?string
    {
        return $this->flush;
    }

    public function setFlush(?string $flush): self
    {
        $this->flush =$flush;
        return $this;
    }

    public function getFlushDescription(): ?string
    {
        return $this->flushDescription;
    }

    public function setFlushDescription(?string $flushDescription): self
    {
        $this->flushDescription =$flushDescription;
        return $this;
    }

    /**
     * Définit la description du message global et l'ajoute simultanément
     * au dictionnaire des erreurs ou des avertissements.
     */
    public function setFlushDescriptionWithError(string $flushDescription, bool$error = true): self
    {
        $this->setFlushDescription($flushDescription);

        if ($error) {
            $this->addError(self::FLUSH_DESCRIPTION,$flushDescription);
        } else {
            $this->addWarning(self::FLUSH_DESCRIPTION,$flushDescription);
        }

        return $this;
    }

    public function setErrorFlushDescription(string $flushDescription): self
    {
        return $this->setFlushDescriptionWithError($flushDescription, true);
    }

    public function setWarningFlushDescription(string $flushDescription): self
    {
        return $this->setFlushDescriptionWithError($flushDescription, false);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status =$status;
        return $this;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function addError(string $field, string$message): self
    {
        $this->errors[$field] =$message;
        return $this;
    }

    public function addWarning(string $field, string$message): self
    {
        $this->warnings[$field] =$message;
        return $this;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function setData(mixed $data): self
    {
        $this->data =$data;
        return $this;
    }

    /**
     * Extrait les violations du Validator Symfony et les lie au dictionnaire d'erreurs.
     *
     * @param iterable|ConstraintViolationListInterface $violations
     */
    public function bind(iterable $violations): self
    {
        foreach ($violations as$violation) {
            if ($violation instanceof ConstraintViolationInterface) {
                $field =$violation->getPropertyPath();
                if (!isset($this->errors[$field])) {$this->errors[$field] =$violation->getMessage();
                }
            }
        }

        return $this;
    }

    /**
     * Génère automatiquement le titre du message (`flush`) et le code HTTP approprié
     * en fonction de la présence ou non d'erreurs.
     */
    public function autoInitFlush(): self
    {
        $hasErrors =$this->hasErrors();
        $this->setFlush($hasErrors ? "Échec d'exécution de l'opération" : "Succès d'exécution de l'opération");
        $this->setStatus($this->isOk() ? 200 : 422);

        return $this;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function isOk(): bool
    {
        return empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    public function jsonSerialize(): array
    {
        return [
            'flush' => $this->flush,
            'flushDescription' => $this->flushDescription,
            'status' => $this->status,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'data' => $this->data,
        ];
    }
}
