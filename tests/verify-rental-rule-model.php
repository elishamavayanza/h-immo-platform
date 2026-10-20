<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Entity\Identity\OrganizationRentalRuleVersion;
use App\Entity\Rental\Lease;
use App\Entity\Rental\Rent;
use App\Enum\RentalPaymentMode;
use App\Enum\RentalRuleOverrideMode;
use Symfony\Component\Validator\Validation;

$validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) {
        throw new RuntimeException('Échec : ' . $message);
    }
};
$hasViolation = static function (iterable $violations, string $path, string $fragment): bool {
    foreach ($violations as $violation) {
        if ($violation->getPropertyPath() === $path && str_contains((string) $violation->getMessage(), $fragment)) {
            return true;
        }
    }

    return false;
};
$hasPathViolation = static function (iterable $violations, string $path): bool {
    foreach ($violations as $violation) {
        if ($violation->getPropertyPath() === $path) {
            return true;
        }
    }

    return false;
};

$version = (new OrganizationRentalRuleVersion())
    ->setEffectiveFrom(new DateTimeImmutable('2026-10-20'))
    ->setDueDay(32);
$versionViolations = $validator->validate($version);
$assert($hasViolation($versionViolations, 'effectiveFrom', 'premier jour'), 'une version doit prendre effet le premier jour du mois');
$assert($hasPathViolation($versionViolations, 'dueDay'), 'le jour d’exigibilité est borné de 1 à 31');

$lease = new Lease();
$assert(count($validator->validate($lease)) === 0, 'un bail sans surcharge hérite explicitement de toutes les règles');
$lease->setDueModeOverrideMode(RentalRuleOverrideMode::OVERRIDE);
$overrideWithoutValue = $validator->validate($lease);
$assert($hasViolation($overrideWithoutValue, 'dueModeOverrideValue', 'valeur est obligatoire'), 'une surcharge exige une valeur explicite');

$lease
    ->setDueModeOverrideMode(RentalRuleOverrideMode::INHERIT)
    ->setDueModeOverrideValue(RentalPaymentMode::IN_ADVANCE);
$inheritWithValue = $validator->validate($lease);
$assert($hasViolation($inheritWithValue, 'dueModeOverrideValue', 'ne peut pas être fournie'), 'un héritage ne peut pas contenir une valeur masquée');

$organizationA = new App\Entity\Identity\Organization();
$organizationB = new App\Entity\Identity\Organization();
$leaseA = (new Lease())->setOrganization($organizationA);
$crossOrganizationRentRejected = false;
try {
    (new Rent())->setOrganization($organizationB)->setLease($leaseA);
} catch (InvalidArgumentException) {
    $crossOrganizationRentRejected = true;
}
$assert($crossOrganizationRentRejected, 'le modèle refuse une échéance dont l’organisation diffère de celle du bail');

$migrationPath = dirname(__DIR__) . '/migrations/Version20261020100000RentalRuleModel.php';
$migration = file_get_contents($migrationPath);
$assert(is_string($migration), 'la migration prévue existe et est lisible');
$preflight = strpos($migration, 'SELECT COUNT(*) FROM rent');
$firstDdl = strpos($migration, "ALTER TABLE lease");
$assert($preflight !== false && $firstDdl !== false && $preflight < $firstDdl, 'le contrôle des échéances orphelines précède le premier DDL');
$assert(str_contains($migration, 'UPDATE rent r INNER JOIN lease l') && str_contains($migration, 'r.organization_id = l.organization_id'), 'la portée des anciennes échéances est reprise de leur bail');
$assert(str_contains($migration, 'MODIFY organization_id BIGINT UNSIGNED NOT NULL'), 'la colonne ne devient obligatoire qu’après son remplissage');
$assert(str_contains($migration, 'uniq_rental_rule_org_version') && str_contains($migration, 'uniq_rental_rule_org_effective'), 'numéro de version et date d’effet sont uniques par organisation');
$assert(str_contains($migration, 'ADD rule_version_id BIGINT UNSIGNED DEFAULT NULL'), 'la référence de version reste nullable pour l’historique et le défaut applicatif');
$assert(str_contains($migration, 'FOREIGN KEY (lease_id, organization_id)') && str_contains($migration, 'FOREIGN KEY (rule_version_id, organization_id)'), 'la base lie échéances, baux, versions et organisation via des clés composites');
$assert(str_contains($migration, 'FOREIGN KEY (rent_id, organization_id, rule_version_id)'), 'le snapshot ne peut pas référencer une autre version que son échéance');
$assert(str_contains($migration, 'trg_rental_rule_immutable_update') && str_contains($migration, 'trg_rental_rule_immutable_delete'), 'une version publiée ne peut être modifiée ou supprimée');
$assert(!preg_match('/INSERT\s+INTO\s+organization_rental_rule_version/i', $migration), 'aucune version par défaut n’est attribuée aux organisations existantes');
$assert(!preg_match('/INSERT\s+INTO\s+rent_rule_snapshot/i', $migration), 'aucun snapshot historique n’est créé');
$assert(!preg_match('/UPDATE\s+rent\s+.*\b(amount|due_date|status)\b/is', $migration), 'la migration ne recalcule aucune donnée financière historique');
$down = substr($migration, strpos($migration, 'public function down'));
$assert(strpos($down, 'DROP TABLE rent_rule_snapshot') < strpos($down, 'ALTER TABLE rent DROP FOREIGN KEY'), 'le rollback supprime le snapshot avant ses tables référencées');
$assert(strpos($down, 'DROP FOREIGN KEY') < strpos($down, 'DROP rule_version_id, DROP organization_id'), 'le rollback retire les clés étrangères avant les colonnes');

echo sprintf("OK : %d contrôles du modèle des règles locatives, sans accès à la base.\n", $checks);
