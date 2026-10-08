<?php

declare(strict_types=1);

/**
 * Vérifie la suspension d'un compte utilisateur avec notification email.
 *
 * Couvre `POST /api/v1/identity/users/{uuid}/suspend` (Service + autorisation
 * + audit + gardes) et l'enrichissement de la liste par les rattachements
 * d'organisation (`UserResponse.memberships`) :
 *   1. la liste SUPER_ADMIN renvoie les rattachements (organisation + rôle) ;
 *   2. une suspension valide passe `isActive` → false, archive `SUSPEND_USER`
 *      (motif inclus) et ne casse pas sur l'envoi d'email (transport de dev
 *      `null://null`, échec → warning, statut 200) ;
 *   3. on ne peut pas suspendre un compte déjà désactivé (422) ;
 *   4. on ne peut pas suspendre son propre compte (422) ;
 *   5. un UUID inconnu renvoie 404 ;
 *   6. un appelant non autorisé reçoit une `AccessDeniedException`.
 *
 *   php tests/verify-user-suspend.php
 */

use App\Dto\Request\Identity\UserSuspendRequest;
use App\Dto\Request\PaginationQuery;
use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationUser;
use App\Entity\Identity\User;
use App\Enum\OrganizationRole;
use App\Enum\OrganizationStatus;
use App\Enum\PlatformRole;
use App\Exception\AccessDeniedException;
use App\Service\Identity\UserService;
use App\Service\System\AuditLogService;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

require dirname(__DIR__) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

final class UserSuspendKernel extends App\Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach ([
                    'security.token_storage',
                    App\Security\SecurityService::class,
                    UserService::class,
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

$kernel = new UserSuspendKernel('dev', true);
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

section('Jeu de données (SUPER_ADMIN + compte membre actif + compte inactif + intrus)');

$superAdmin = new User();
$superAdmin->setEmail('root.usersuspend@plateforme.test');
$superAdmin->setFullName('Root Plateforme');
$superAdmin->setPassword(password_hash('Password123!', PASSWORD_BCRYPT));
$superAdmin->setPlatformRole(PlatformRole::SUPER_ADMIN);
$superAdmin->setIsActive(true);
$em->persist($superAdmin);

$target = new User();
$target->setEmail('cible.usersuspend@orga.test');
$target->setFullName('Cible Orga');
$target->setPassword(password_hash('Password123!', PASSWORD_BCRYPT));
$target->setPlatformRole(null);
$target->setIsActive(true);
$em->persist($target);

$inactive = new User();
$inactive->setEmail('inactif.usersuspend@orga.test');
$inactive->setFullName('Déjà Inactif');
$inactive->setPassword(password_hash('Password123!', PASSWORD_BCRYPT));
$inactive->setPlatformRole(null);
$inactive->setIsActive(false);
$em->persist($inactive);

$org = new Organization();
$org->setName('Organisation UsersSuspend');
$org->setCode('USERSUSP');
$org->setEmail('contact.usersuspend@orga.test');
$org->setPhone('+000');
$org->setStatus(OrganizationStatus::ACTIVE);
$em->persist($org);

$membership = new OrganizationUser();
$membership->setUser($target);
$membership->setOrganization($org);
$membership->setRole(OrganizationRole::PATRON);
$em->persist($membership);

$outsider = new User();
$outsider->setEmail('intrus.usersuspend@autre.test');
$outsider->setFullName('Intrus');
$outsider->setPassword(password_hash('Password123!', PASSWORD_BCRYPT));
$outsider->setPlatformRole(null);
$outsider->setIsActive(true);
$em->persist($outsider);

$em->flush();

$tokenStorage = $container->get('security.token_storage');
$tokenStorage->setToken(new UsernamePasswordToken($superAdmin, 'main', $superAdmin->getRoles()));

/** @var UserService $service */
$service = $container->get(UserService::class);

section('Liste enrichie par les rattachements d\'organisation');

$feedbackList = $service->list(new PaginationQuery(page: 1, limit: 100));
$items = $feedbackList->getData()['items'];
check('La liste SUPER_ADMIN renvoie 200', $feedbackList->getStatus() === 200, "obtenu {$feedbackList->getStatus()}");

$targetItem = null;
foreach ($items as $item) {
    if ($item->email === 'cible.usersuspend@orga.test') {
        $targetItem = $item;
    }
}
check('Le compte cible est présent dans la liste', $targetItem !== null);
check('La liste expose les rattachements du compte', $targetItem !== null && $targetItem->memberships !== []);

if ($targetItem !== null && $targetItem->memberships !== []) {
    $first = $targetItem->memberships[0];
    check('Le rattachement porte l\'UUID de l\'organisation', $first['organizationId'] === (string) $org->getUuid(), $first['organizationId'] ?? 'vide');
    check('Le rattachement porte le nom de l\'organisation', $first['organizationName'] === 'Organisation UsersSuspend', $first['organizationName'] ?? 'vide');
    check('Le rattachement porte le rôle métier', ($first['role'] ?? '') === OrganizationRole::PATRON->value, $first['role'] ?? 'vide');
}

section('Suspension valide + audit + email non bloquant');

$feedbackSuspend = $service->suspend((string) $target->getUuid(), new UserSuspendRequest('Compte dormant depuis plusieurs mois.'));
check('La suspension valide renvoie 200', $feedbackSuspend->getStatus() === 200, "obtenu {$feedbackSuspend->getStatus()}");
$data = $feedbackSuspend->getData();
check('Le compte exposé est inactif', $data !== null && $data->isActive === false);
check('La réponse de suspension conserve les rattachements', $data !== null && $data->memberships !== []);

$isActiveInDb = $connection->fetchOne('SELECT is_active FROM user WHERE id = :id', ['id' => $target->getId()]);
check('Le compte est désactivé en base', (int) $isActiveInDb === 0, (string) $isActiveInDb);

$auditSuspend = $em->getConnection()->fetchAssociative(
    'SELECT * FROM audit_log WHERE action = :action ORDER BY id DESC LIMIT 1',
    ['action' => 'SUSPEND_USER']
);
check('Une ligne d\'audit SUSPEND_USER est écrite', $auditSuspend !== false);
check('Le motif est archivé dans l\'audit', ($auditSuspend['new_values'] ?? null) !== null && str_contains((string) $auditSuspend['new_values'], 'dormant'), (string) ($auditSuspend['new_values'] ?? 'vide'));

section('Double suspension refusée');

$feedbackTwice = $service->suspend((string) $target->getUuid(), new UserSuspendRequest('Nouveau motif.'));
check('Suspendre un compte déjà désactivé renvoie 422', $feedbackTwice->getStatus() === 422, "obtenu {$feedbackTwice->getStatus()}");

section('Auto-suspension refusée');

$feedbackSelf = $service->suspend((string) $superAdmin->getUuid(), new UserSuspendRequest('Test.'));
check('Suspendre son propre compte renvoie 422', $feedbackSelf->getStatus() === 422, "obtenu {$feedbackSelf->getStatus()}");
check('L\'erreur cible le champ `user`', isset($feedbackSelf->getErrors()['user']), (string) json_encode($feedbackSelf->getErrors()));

section('Erreurs et autorisation');

$feedback404 = $service->suspend('00000000-0000-0000-0000-000000000000', new UserSuspendRequest('Motif.'));
check('Un UUID inconnu renvoie 404', $feedback404->getStatus() === 404, "obtenu {$feedback404->getStatus()} : " . (string) json_encode($feedback404->getErrors()));

// Appelant sans appartenance : simple utilisateur authentifié, aucune
// organisation ni rôle de plateforme. `checkUserAccess` doit lever 403.
$tokenStorage->setToken(new UsernamePasswordToken($outsider, 'main', $outsider->getRoles()));

try {
    $service->suspend((string) $inactive->getUuid(), new UserSuspendRequest('Motif non autorisé.'));
    check('Un appelant non autorisé est refusé (403 via AccessDeniedException)', false, 'aucune exception levée');
} catch (AccessDeniedException) {
    check('Un appelant non autorisé est refusé (403 via AccessDeniedException)', true);
}

// Filet de sécurité : rollback explicite (le shutdown function fait le reste).
$connection->rollBack();

echo "\n------------------------------------------------------------\n";
echo $failures === [] ? "SUCCES : {$checks} contrôles passés\n" : "ECHEC : {$failures[0]} (+" . (count($failures) - 1) . " autres)\n";

exit($failures === [] ? 0 : 1);