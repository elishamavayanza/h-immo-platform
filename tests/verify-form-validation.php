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
use App\Controller\Api\Property\ListingController;
use App\Controller\Api\Staff\WorkerAssignmentController;
use App\Dto\Request\Expense\ExpenseRequest;
use App\Dto\Request\Identity\UserCityRequest;
use App\Dto\Request\Identity\UserSettingsRequest;
use App\Dto\Request\Property\UnitRequest;
use App\Dto\Request\Property\ListingDraftRequest;
use App\Dto\Request\Property\ListingCandidateFilterRequest;
use App\Dto\Request\Property\ListingFilterRequest;
use App\Dto\Request\Staff\WorkerAssignmentRequest;
use App\Dto\Request\System\PublicationDraftDto;
use App\Enum\Currency;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Validator\Validation;

require dirname(__DIR__).'/vendor/autoload.php';

$validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
$failures = 0;
$checks = 0;

$check = static function (bool $condition, string $label) use (&$failures, &$checks): void {
    echo ($condition ? '  OK  ' : 'FAIL  ').$label.PHP_EOL;
    $checks++;
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

$invalidListing = new ListingDraftRequest(title: '', description: 'Annonce', offerType: 'gift', price: '0.00', currency: 'EUR');
$check($hasViolation($invalidListing, null, 'offerType') && $hasViolation($invalidListing, null, 'currency'), 'les types d’offre et devises d’annonce sont bornés');
$check($hasViolation($invalidListing, null, 'price'), 'un prix d’annonce nul ou mal formé est rejeté');
$invalidListingFilter = new ListingFilterRequest(page: 0, limit: 101, status: 'unknown');
$check($hasViolation($invalidListingFilter, null, 'page') && $hasViolation($invalidListingFilter, null, 'limit'), 'la pagination des annonces respecte ses bornes');
$check($hasViolation($invalidListingFilter, null, 'status'), 'un statut de filtre d’annonce inconnu est rejeté');
$invalidCandidateFilter = new ListingCandidateFilterRequest(page: 0, limit: 101, cityUuid: 'pas-un-uuid');
$check($hasViolation($invalidCandidateFilter, null, 'page') && $hasViolation($invalidCandidateFilter, null, 'limit'), 'la pagination des biens candidats respecte ses bornes');
$check($hasViolation($invalidCandidateFilter, null, 'cityUuid'), 'le filtre de ville active exige un UUID valide');

$oversizedLease = new \App\Dto\Request\Rental\LeaseRequest(additionalUnitUuids: array_fill(0, 21, '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'));
$check($hasViolation($oversizedLease, ['create'], 'additionalUnitUuids'), 'une liste démesurée d’unités est rejetée');

$mappedGroups = [
    [ExpenseController::class, 'create', ExpenseRequest::class, ['create']],
    [UserController::class, 'updateMySettings', UserSettingsRequest::class, ['update']],
    [UserCityController::class, 'assign', UserCityRequest::class, ['create']],
    [UnitController::class, 'update', UnitRequest::class, ['update']],
    [ListingController::class, 'create', ListingDraftRequest::class, null],
    [ListingController::class, 'update', ListingDraftRequest::class, null],
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
    $check($found, $controller.'::'.$method.' active les groupes '.implode(',', $expectedGroups ?? []));
}
$listingList = new ReflectionMethod(ListingController::class, 'list');
$listingFilterMapped = false;
foreach ($listingList->getParameters() as $parameter) {
    if ($parameter->getType() instanceof ReflectionNamedType && $parameter->getType()->getName() === ListingFilterRequest::class) {
        $listingFilterMapped = $parameter->getAttributes(\Symfony\Component\HttpKernel\Attribute\MapQueryString::class) !== [];
    }
}
$check($listingFilterMapped, 'ListingController::list mappe le filtre via la query string');
$listingCandidates = new ReflectionMethod(ListingController::class, 'candidates');
$listingCandidatesMapped = false;
foreach ($listingCandidates->getParameters() as $parameter) {
    if ($parameter->getType() instanceof ReflectionNamedType && $parameter->getType()->getName() === ListingCandidateFilterRequest::class) {
        $listingCandidatesMapped = $parameter->getAttributes(\Symfony\Component\HttpKernel\Attribute\MapQueryString::class) !== [];
    }
}
$check($listingCandidatesMapped, 'ListingController::candidates mappe le filtre via la query string');
$listingFilterValidation = new ReflectionMethod(\App\Service\Property\ListingWorkflowService::class, 'validateFilter');
$check($listingFilterValidation->getParameters()[0]->getType() instanceof ReflectionNamedType
    && $listingFilterValidation->getParameters()[0]->getType()->getName() === ListingFilterRequest::class,
    'ListingWorkflowService valide le DTO de filtre de liste avec son type propre');
$listingDraftValidation = new ReflectionMethod(\App\Service\Property\ListingWorkflowService::class, 'validate');
$check($listingDraftValidation->getParameters()[0]->getType() instanceof ReflectionNamedType
    && $listingDraftValidation->getParameters()[0]->getType()->getName() === ListingDraftRequest::class,
    'ListingWorkflowService conserve la validation dédiée au brouillon');

echo PHP_EOL.'Contrôles : '.$checks.'; échecs : '.$failures.PHP_EOL;
exit($failures === 0 ? 0 : 1);
