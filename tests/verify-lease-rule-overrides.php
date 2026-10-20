<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Dto\Request\Rental\LeaseRequest;
use App\Entity\Rental\Lease;
use App\Enum\RentalPaymentMode;
use App\Enum\RentalRuleOverrideMode;
use App\Mapper\Rental\LeaseMapper;
use Symfony\Component\Validator\Validation;

$validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "ÉCHEC : {$message}\n");
        exit(1);
    }
};
$checks = 0;

$lease = new Lease();
$assert(count($validator->validate($lease)) === 0, 'les règles par défaut héritées sont valides');
$checks++;

$lease->setDueModeOverrideMode(RentalRuleOverrideMode::OVERRIDE);
$assert(count($validator->validate($lease)) === 1, 'un remplacement sans valeur est refusé');
$checks++;

$lease->setDueModeOverrideValue(RentalPaymentMode::IN_ARREARS);
$assert(count($validator->validate($lease)) === 0, 'un remplacement avec valeur est valide');
$checks++;

$lease->setDueModeOverrideMode(RentalRuleOverrideMode::INHERIT);
$assert(count($validator->validate($lease)) === 1, 'une valeur fournie avec le mode hérité est refusée');
$checks++;

$mapper = new LeaseMapper();
$lease->setDueModeOverrideMode(RentalRuleOverrideMode::OVERRIDE)->setDueModeOverrideValue(RentalPaymentMode::IN_ARREARS);
$mapper->copyToEntity(new LeaseRequest(dueModeOverrideMode: null), $lease);
$assert($lease->getDueModeOverrideMode() === RentalRuleOverrideMode::OVERRIDE, 'un champ absent en mise à jour conserve le remplacement existant');
$checks++;

$mapper->copyToEntity(new LeaseRequest(dueModeOverrideMode: RentalRuleOverrideMode::INHERIT), $lease);
$assert($lease->getDueModeOverrideMode() === RentalRuleOverrideMode::INHERIT && $lease->getDueModeOverrideValue() === null, 'un choix explicite d’héritage efface le remplacement');
$checks++;

$invalidDay = new LeaseRequest(dueDayOverrideMode: RentalRuleOverrideMode::OVERRIDE, dueDayOverrideValue: 32);
$assert(count($validator->validate($invalidDay, null, ['create'])) > 0, 'le jour d’exigibilité hors plage est refusé');
$checks++;

$serviceSource = file_get_contents(dirname(__DIR__) . '/src/Service/Rental/LeaseService.php');
$simulationStart = strpos($serviceSource, 'public function simulateLeaseSchedule(');
$simulationEnd = strpos($serviceSource, 'public function createLease(', $simulationStart ?: 0);
$simulationSource = $simulationStart !== false && $simulationEnd !== false
    ? substr($serviceSource, $simulationStart, $simulationEnd - $simulationStart)
    : '';
$assert(str_contains($simulationSource, '$this->scheduleCalculator->calculate('), 'l’aperçu délègue le calcul au moteur partagé');
$checks++;
$assert(!preg_match('/->(?:persist|flush|createRent|createSnapshot)\s*\(/', $simulationSource), 'l’aperçu ne persiste ni bail, ni échéance, ni snapshot');
$checks++;
$assert(str_contains($simulationSource, 'resolveTenant(') && str_contains($simulationSource, 'resolveUnit(') && str_contains($simulationSource, 'CREATE_LEASE'), 'l’aperçu contrôle les ressources et l’autorisation sur l’organisation');
$checks++;
$controllerSource = file_get_contents(dirname(__DIR__) . '/src/Controller/Api/Rental/LeaseController.php');
$assert(str_contains($controllerSource, "Route('/simulate'") && str_contains($controllerSource, 'MapRequestPayload] LeaseSimulationRequest'), 'la route HTTP protégée mappe un DTO validé');
$checks++;
$governanceSource = file_get_contents(dirname(__DIR__) . '/src/Service/Rental/RentalRuleGovernanceService.php');
$assert(str_contains($governanceSource, 'function currentCapabilities') && str_contains($governanceSource, 'PUBLISH_RENTAL_RULE'), 'les capacités de publication exposées sont décidées côté serveur');
$checks++;

fwrite(STDOUT, "Réussi : {$checks} contrôles des surcharges et garanties statiques de simulation, sans base de données.\n");
