<?php

declare(strict_types=1);

/**
 * Vérifie la suspension/réactivation d'une organisation avec notion.
 *
 * Couvre `POST /api/v1/identity/organizations/{uuid}/suspend` et
 * `POST /api/v1/identity/organizations/{uuid}/reactivate` (Service +
 * autorisation + audit + validation du motif) :
 *   1. un motif vide est refusé (422) et le statut reste `active` ;
 *   2. une suspension valide passe `active` → `suspended`, archive une ligne
 *      d'audit `SUSPEND_ORGANIZATION` et ne casse pas sur l'envoi d'emails
 *      (le transport de dev est `null://null`, échecs → warning) ;
 *   3. on ne peut pas suspendre une organisation déjà suspendue (422) ;
 *   4. la réactivation passe `suspended` → `active` et archive
 *      `ACTIVATE_ORGANIZATION` ;
 *   5. on ne peut pas réactiver une organisation active (422) ;
 *   6. un appelant non autorisé reçoit une `AccessDeniedException`.
 *
 *   php tests/verify-organization-suspend.php
 */

use App\Dto\Request\Identity\OrganizationSuspendRequest;
use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationUser;
use App\Entity\Identity\User;
use App\Enum\OrganizationRole;
use App\Enum\OrganizationStatus;
use App\Enum\PlatformRole;
use App\Exception\AccessDeniedException;
use App\Service\Identity\OrganizationService;
use App\Service\System\AuditLogService;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

require dirname(__DIR__) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

final class SuspendKernel extends App\Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach ([
                    'security.token_storage',
                    App\Security\SecurityService::class,
                    OrganizationService::class,
                    AuditLogService::class,
                ] as $id) {
                    if ($container->hasDefinition($id)) {
                        $container->getDefinition($id)->setPublic(true);
                    }
                }
            }
        });
    }
}

$checks = 0;
$failures = [];

function check(string $label, bool $ok, string $detail = ''): void
{
    global $checks, $failures;

    $checks++;

    if ($ok) {
        echo "  [OK]   {$label}\n";

        return;
    }

    $failures[] = $label . ($detail !== '' ? " ({$detail})" : '');
    echo "  [FAIL] {$label}" . ($detail !== '' ? " -> {$detail}" : '') . "\n";
}

function section(string $title): void
{
    echo "\n=== {$title} ===\n";
}

$kernel = new SuspendKernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$connection = $em->getConnection();
$container = $kernel->getContainer();

// Transaction globale : tout est annulé, les lignes d'audit et les jetons
// compris, y compris en cas d'erreur fatale.
$connection->beginTransaction();
$rollback = static function () use ($connection): void {
    if ($connection->isTransactionActive()) {
        $connection->rollBack();
    }
};

register_shutdown_function(static function () use ($rollback): void {
    $rollback();
});

$connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
foreach ($connection->fetchFirstColumn("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()") as $table) {
    if (in_array($table, ['doctrine_migration_versions', 'migration_versions'], true)) {
        continue;
    }

    $connection->executeStatement('DELETE FROM `' . $table . '`');
}
$connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

section('Jeu de données (SUPER_ADMIN + organisation active avec un membre)');

$superAdmin = new User();
$superAdmin->setEmail('root.suspend@plateforme.test');
$superAdmin->setFullName('Root Plateforme');
$superAdmin->setPassword(password_hash('Password123!', PASSWORD_BCRYPT));
$superAdmin->setPlatformRole(PlatformRole::SUPER_ADMIN);
$superAdmin->setIsActive(true);
$em->persist($superAdmin);

$member = new User();
$member->setEmail('membre.suspend@orga.test');
$member->setFullName('Membre Orga');
$member->setPassword(password_hash('Password123!', PASSWORD_BCRYPT));
$member->setPlatformRole(null);
$member->setIsActive(true);
$em->persist($member);

$org = new Organization();
$org->setName('Organisation Suspend Test');
$org->setCode('SUSPEND');
$org->setEmail('contact.suspend@orga.test');
$org->setPhone('+000');
$org->setStatus(OrganizationStatus::ACTIVE);
$em->persist($org);

$membership = new OrganizationUser();
$membership->setUser($member);
$membership->setOrganization($org);
$membership->setRole(OrganizationRole::PATRON);
$em->persist($membership);

$em->flush();

$memberUuid = (string) $org->getUuid();

$tokenStorage = $container->get('security.token_storage');
$tokenStorage->setToken(new UsernamePasswordToken($superAdmin, 'main', $superAdmin->getRoles()));

/** @var OrganizationService $service */
$service = $container->get(OrganizationService::class);

section('Motif obligatoire');

$feedbackEmpty = $service->suspend($memberUuid, new OrganizationSuspendRequest(''));
check('Un motif vide renvoie un statut 422', $feedbackEmpty->getStatus() === 422, "obtenu {$feedbackEmpty->getStatus()}");
check('Le motif vide est signalé sur le champ `reason`', isset($feedbackEmpty->getErrors()['reason']), (string) json_encode($feedbackEmpty->getErrors()));

$statusInDb = $connection->fetchOne('SELECT status FROM organization WHERE code = :code', ['code' => 'SUSPEND']);
check('L\'organisation reste active après refus', $statusInDb === OrganizationStatus::ACTIVE->value, (string) $statusInDb);

section('Suspension valide + audit');

$feedbackSuspend = $service->suspend($memberUuid, new OrganizationSuspendRequest('Non-paiement des frais d\'abonnement.'));
check('La suspension valide renvoie 200', $feedbackSuspend->getStatus() === 200, "obtenu {$feedbackSuspend->getStatus()}");
$data = $feedbackSuspend->getData();
check('Le statut exposé est `suspended`', $data->status === OrganizationStatus::SUSPENDED, $data->status->value);

$statusInDb = $connection->fetchOne('SELECT status FROM organization WHERE code = :code', ['code' => 'SUSPEND']);
check('Le statut en base est `suspended`', $statusInDb === OrganizationStatus::SUSPENDED->value, (string) $statusInDb);

$auditSuspend = $em->getConnection()->fetchAssociative(
    'SELECT * FROM audit_log WHERE action = :action ORDER BY id DESC LIMIT 1',
    ['action' => 'SUSPEND_ORGANIZATION']
);
check('Une ligne d\'audit SUSPEND_ORGANIZATION est écrite', $auditSuspend !== false);
check('Le motif est archivé dans l\'audit', ($auditSuspend['new_values'] ?? null) !== null && str_contains((string) $auditSuspend['new_values'], 'Non-paiement'), (string) ($auditSuspend['new_values'] ?? 'vide'));

section('Double suspension refusée');

$feedbackTwice = $service->suspend($memberUuid, new OrganizationSuspendRequest('Nouveau motif.'));
check('Suspendre un tenant déjà suspendu renvoie 422', $feedbackTwice->getStatus() === 422, "obtenu {$feedbackTwice->getStatus()}");

section('Réactivation + audit');

$feedbackReactivate = $service->reactivate($memberUuid);
check('La réactivation valide renvoie 200', $feedbackReactivate->getStatus() === 200, "obtenu {$feedbackReactivate->getStatus()}");
$data = $feedbackReactivate->getData();
check('Le statut exposé redevient `active`', $data->status === OrganizationStatus::ACTIVE, $data->status->value);

$auditReactivate = $em->getConnection()->fetchAssociative(
    'SELECT * FROM audit_log WHERE action = :action ORDER BY id DESC LIMIT 1',
    ['action' => 'ACTIVATE_ORGANIZATION']
);
check('Une ligne d\'audit ACTIVATE_ORGANIZATION est écrite', $auditReactivate !== false);

check('Réactiver un tenant actif renvoie 422', $service->reactivate($memberUuid)->getStatus() === 422);

section('Erreurs et autorisation');

$feedback404 = $service->suspend('00000000-0000-0000-0000-000000000000', new OrganizationSuspendRequest('Motif.'));
check('Un UUID inconnu renvoie 404', $feedback404->getStatus() === 404, "obtenu {$feedback404->getStatus()} : " . (string) json_encode($feedback404->getErrors()));

// Appelant sans appartenance : simple utilisateur authentifié, aucune
// organisation. Le `checkOrganizationAccess` doit lever App\AccessDeniedException.
$outsider = new User();
$outsider->setEmail('outsider.suspend@autre.test');
$outsider->setFullName('Intrus');
$outsider->setPassword(password_hash('Password123!', PASSWORD_BCRYPT));
$outsider->setPlatformRole(null);
$outsider->setIsActive(true);
$em->persist($outsider);
$em->flush();

$tokenStorage->setToken(new UsernamePasswordToken($outsider, 'main', $outsider->getRoles()));

try {
    $service->suspend($memberUuid, new OrganizationSuspendRequest('Motif non autorisé.'));
    check('Un non-membre est refusé (403 via AccessDeniedException)', false, 'aucune exception levée');
} catch (AccessDeniedException) {
    check('Un non-membre est refusé (403 via AccessDeniedException)', true);
}

// Filet de sécurité : rollback explicite (le shutdown function fait le reste).
$connection->rollBack();

echo "\n------------------------------------------------------------\n";
echo $failures === [] ? "SUCCES : {$checks} contrôles passés\n" : "ECHEC : {$failures[0]} (+" . (count($failures) - 1) . " autres)\n";

exit($failures === [] ? 0 : 1);