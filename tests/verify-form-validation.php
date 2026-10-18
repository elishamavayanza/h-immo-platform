<?php

declare(strict_types=1);

/**
 * Vérifie des règles de validation critiques des formulaires sans base de données.
 * Usage : php tests/verify-form-validation.php
 */

use App\Controller\Api\Expense\ExpenseController;
use App\Controller\Api\Identity\UserCityController;
use App\Controller\Api\Identity\UserController;
use App\Controller\Api\Property\UnitController;
use App\Controller\Api\Staff\WorkerAssignmentController;
use App\Dto\Request\Expense\ExpenseRequest;
use App\Dto\Request\Identity\UserCityRequest;
use App\Dto\Request\Identity\UserSettingsRequest;
use App\Dto\Request\Property\UnitRequest;
use App\Dto\Request\Staff\WorkerAssignmentRequest;
use App\Dto\Request\System\PublicationDraftDto;
use App\Enum\Currency;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Validator\Validation;

require dirname(__DIR__).'/vendor/autoload.php';

$validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
$failures = 0;

$check = static function (bool $condition, string $label) use (&$failures): void {
    echo ($condition ? '  OK  ' : 'FAIL  ').$label.PHP_EOL;
    $failures += $condition ? 0 : 1;
};

$hasViolation = static function (object $dto, ?array $groups = null, string $field = '') use ($validator): bool {
    foreach ($validator->validate($dto, null, $groups) as $violation) {
        if ($field === '' || $violation->getPropertyPath() === $field || str_starts_with($violation->getPropertyPath(), $field.'[')) {
            return true;
        }
    }

    return false;
};

$expense = new ExpenseRequest(
    category: 'invented',
    amount: '-1.00',
    currency: Currency::USD,
    expenseDate: new DateTimeImmutable('2026-01-01'),
);
$check($hasViolation($expense, ['create'], 'category'), 'une catégorie inconnue de dépense est rejetée');
$check($hasViolation($expense, ['create'], 'amount'), 'un montant négatif est rejeté');

$workerAssignment = new WorkerAssignmentRequest(role: 'invented', monthlySalary: '-3.00');
$check($hasViolation($workerAssignment, ['create'], 'role'), 'un rôle de personnel inconnu est rejeté');
$check($hasViolation($workerAssignment, ['create'], 'monthlySalary'), 'un salaire nul ou négatif est rejeté');

$settings = new UserSettingsRequest(auditLogRetentionDays: 10, defaultPageSize: 500);
$check($hasViolation($settings, ['update'], 'auditLogRetentionDays'), 'la rétention des journaux respecte ses bornes');
$check($hasViolation($settings, ['update'], 'defaultPageSize'), 'la taille de page respecte ses bornes');

$unit = new UnitRequest(surface: '1234567.00');
$check($hasViolation($unit, ['create'], 'surface'), 'la précision de surface respecte le schéma décimal');

$publication = new PublicationDraftDto(audienceRoles: ['super_admin', 'unknown'], scheduledAt: 'not-a-date');
$check($hasViolation($publication, null, 'audienceRoles'), 'les rôles cibles de publication sont bornés aux rôles métier');
$check($hasViolation($publication, null, 'scheduledAt'), 'une date de publication mal formée est rejetée');

$oversizedLease = new \App\Dto\Request\Rental\LeaseRequest(additionalUnitUuids: array_fill(0, 21, '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'));
$check($hasViolation($oversizedLease, ['create'], 'additionalUnitUuids'), 'une liste démesurée d’unités est rejetée');

$mappedGroups = [
    [ExpenseController::class, 'create', ExpenseRequest::class, ['create']],
    [UserController::class, 'updateMySettings', UserSettingsRequest::class, ['update']],
    [UserCityController::class, 'assign', UserCityRequest::class, ['create']],
    [UnitController::class, 'update', UnitRequest::class, ['update']],
    [WorkerAssignmentController::class, 'create', WorkerAssignmentRequest::class, ['create']],
];
foreach ($mappedGroups as [$controller, $method, $dtoClass, $expectedGroups]) {
    $reflection = new ReflectionMethod($controller, $method);
    $found = false;
    foreach ($reflection->getParameters() as $parameter) {
        if ($parameter->getType() instanceof ReflectionNamedType && $parameter->getType()->getName() === $dtoClass) {
            $attributes = $parameter->getAttributes(MapRequestPayload::class);
            $found = $attributes !== [] && $attributes[0]->newInstance()->validationGroups === $expectedGroups;
            break;
        }
    }
    $check($found, $controller.'::'.$method.' active les groupes '.implode(',', $expectedGroups));
}

echo PHP_EOL.'Contrôles : '.(count($mappedGroups) + 10).'; échecs : '.$failures.PHP_EOL;
exit($failures === 0 ? 0 : 1);
