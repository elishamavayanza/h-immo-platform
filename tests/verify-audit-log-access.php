<?php

declare(strict_types=1);

/**
 * Le journal d'audit doit être accessible, et borné à l'Organization du lecteur.
 *
 * `AuditLogController` portait `#[IsGranted('ROLE_ADMIN')]`, or aucun utilisateur
 * ne possède jamais ce rôle : `User::getRoles()` ne dérive que `ROLE_USER` et
 * `ROLE_SUPER_ADMIN`, et `security.yaml` ne définit aucun `role_hierarchy`. Le
 * pare-feu renvoyait donc 403 à tout le monde, SUPER_ADMIN compris, et la
 * logique d'isolation de `SecurityService::checkOrganizationAuditLogAccess()`
 * — correcte, mais inatteignable — ne servait à rien.
 *
 * Ce script couvre l'accessibilité (rouvrant le contrôleur sans l'ouvrir à
 * tout le monde) et l'isolation (deux Organizations concurrentes, aucun
 * journal d'un autre tenant ne doit apparaître).
 *
 *   php tests/verify-audit-log-access.php
 */

use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationUser;
use App\Entity\Identity\User;
use App\Entity\System\AuditLog;
use App\Enum\OrganizationRole;
use App\Enum\OrganizationStatus;
use App\Enum\PlatformRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;

ob_start();

require dirname(__DIR__) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

$_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = 'null://null';
putenv('MAILER_DSN=null://null');

final class AuditLogKernel extends App\Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach (['security.token_storage', 'cache.rate_limiter'] as $id) {
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

$kernel = new AuditLogKernel('dev', false);
$kernel->boot();
$container = $kernel->getContainer();

/** @var EntityManagerInterface $em */
$em = $container->get('doctrine')->getManager();
$container->get('cache.rate_limiter')->clear();

$request = static function (string $method, string $uri, ?array $json = null, ?string $bearer = null) use ($kernel): array {
    $server = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'HTTP_HOST' => 'localhost'];

    if ($bearer !== null) {
        $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $bearer;
    }

    $content = $json !== null ? json_encode($json, JSON_THROW_ON_ERROR) : null;
    $req = Request::create($uri, $method, [], [], [], $server, $content);

    if ($content !== null) {
        $req->headers->set('CONTENT_TYPE', 'application/json');
    }

    $response = $kernel->handle($req);
    $status = $response->getStatusCode();
    $raw = (string) $response->getContent();
    $kernel->terminate($req, $response);

    return [$status, json_decode($raw, true), $raw];
};

$connection = $em->getConnection();
$tables = $connection->fetchFirstColumn(
    'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()'
);
$connection->beginTransaction();
$connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

foreach (array_reverse($tables) as $table) {
    if (!in_array($table, ['doctrine_migration_versions', 'migration_versions'], true)) {
        $connection->executeStatement('DELETE FROM `' . $table . '`');
    }
}

$connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

$suffix = 'auditlog';
$plainPassword = 'MotDePasse!123';

$rollback = static function () use ($connection): void {
    if ($connection->isTransactionActive()) {
        $connection->rollBack();
    }

    echo "\n[info] Annulation de la transaction\n";
};

$login = static function (string $email, string $password) use ($request): ?string {
    [$status, $payload] = $request('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);

    return $status === 200 && is_string($payload['accessToken'] ?? null) ? $payload['accessToken'] : null;
};

// ---------------------------------------------------------------------
// Deux Organizations concurrentes : A (lecteurs) et B (le tenant d'Aurélien)
// ---------------------------------------------------------------------
section('Construction : deux Organizations concurrentes');

$makeUser = static function (string $key) use ($em, $suffix, $plainPassword): User {
    $user = (new User())
        ->setEmail($key . '.' . $suffix . '@test.local')
        ->setFullName($key)
        ->setPassword(password_hash($plainPassword, PASSWORD_BCRYPT))
        ->setIsActive(true);

    $em->persist($user);

    return $user;
};

$superAdmin = $makeUser('root');
$superAdmin->setPlatformRole(PlatformRole::SUPER_ADMIN);

$patronA = $makeUser('patronA');
$adminImmoA = $makeUser('immoA');
$adminVilleA = $makeUser('villeA');
$patronB = $makeUser('patronB');

$makeOrg = static function (string $code) use ($em, $suffix): Organization {
    $org = (new Organization())
        ->setName('Organisation ' . $code)
        ->setCode($code . '-' . strtoupper($suffix))
        ->setEmail(strtolower($code) . '.' . $suffix . '@test.local')
        ->setPhone('+000')
        ->setStatus(OrganizationStatus::ACTIVE);
    $em->persist($org);

    return $org;
};

$orgA = $makeOrg('ORGA');
$orgB = $makeOrg('ORGB');

$attach = static function (User $user, Organization $org, OrganizationRole $role) use ($em): void {
    $em->persist((new OrganizationUser())->setUser($user)->setOrganization($org)->setRole($role));
};

$attach($patronA, $orgA, OrganizationRole::PATRON);
$attach($adminImmoA, $orgA, OrganizationRole::ADMIN_IMMOBILIER);
$attach($adminVilleA, $orgA, OrganizationRole::ADMIN_VILLE);
$attach($patronB, $orgB, OrganizationRole::PATRON);
$em->flush();

$makeLog = static function (Organization $org, User $actor, string $action) use ($em): AuditLog {
    $log = (new AuditLog())
        ->setAction($action)
        ->setEntityType('App\\Entity\\Identity\\User')
        ->setEntityId($actor->getId())
        ->setOrganization($org)
        ->setUser($actor)
        ->setNewValues(['marker' => $action]);

    $em->persist($log);

    return $log;
};

$logA = $makeLog($orgA, $patronA, 'MARKER_A');
$logB = $makeLog($orgB, $patronB, 'MARKER_B');
$em->flush();

$bearer = [];
foreach (['root', 'patronA', 'immoA', 'villeA', 'patronB'] as $key) {
    $bearer[$key] = $login($key . '.' . $suffix . '@test.local', $plainPassword);
}

foreach ($bearer as $key => $token) {
    check("{$key} est authentifié", $token !== null);
}

if (in_array(null, $bearer, true)) {
    $rollback();
    echo "\nECHECS : " . count($failures) . " sur {$checks}\n";
    exit(1);
}

/** @return list<string> les valeurs du champ `marker` renvoyées par la liste. */
$markersOf = static function (?array $payload): array {
    $items = $payload['data']['items'] ?? $payload['items'] ?? [];
    $markers = [];

    foreach ((array) $items as $item) {
        if (is_array($item) && isset($item['newValues']['marker'])) {
            $markers[] = (string) $item['newValues']['marker'];
        }
    }

    return $markers;
};

// ---------------------------------------------------------------------
// 1. Accessibilité : le contrôleur n'est plus un mur
// ---------------------------------------------------------------------
section('Accessibilité du journal');

[$status, , $raw] = $request('GET', '/api/v1/system/audit-logs', null, $bearer['root']);
check('le SUPER_ADMIN accède au journal', $status === 200, "obtenu {$status} : " . substr($raw, 0, 160));

[$status, , $raw] = $request('GET', '/api/v1/system/audit-logs', null, $bearer['patronA']);
check('le PATRON accède au journal', $status === 200, "obtenu {$status} : " . substr($raw, 0, 160));

[$status, , $raw] = $request('GET', '/api/v1/system/audit-logs', null, $bearer['immoA']);
check("l'ADMIN_IMMOBILIER accède au journal", $status === 200, "obtenu {$status} : " . substr($raw, 0, 160));

[$status] = $request('GET', '/api/v1/system/audit-logs');
check('un appel non authentifié est refusé', $status === 401, "obtenu {$status}");

// ---------------------------------------------------------------------
// 2. Rôle d'Organization exclu
// ---------------------------------------------------------------------
section("L'ADMIN_VILLE reste exclu du journal");

[$status, , $raw] = $request('GET', '/api/v1/system/audit-logs', null, $bearer['villeA']);
check("l'ADMIN_VILLE est refusé", $status === 403, "obtenu {$status} : " . substr($raw, 0, 160));

// ---------------------------------------------------------------------
// 3. Isolation entre Organizations
// ---------------------------------------------------------------------
section('Isolation : aucun journal d\'un autre tenant');

[$status, $payload] = $request('GET', '/api/v1/system/audit-logs', null, $bearer['patronA']);
$markers = $markersOf($payload);
check('le PATRON A voit le journal de sa société', in_array('MARKER_A', $markers, true), implode(',', $markers));
check('le PATRON A ne voit pas le journal de B', !in_array('MARKER_B', $markers, true), implode(',', $markers));

[$status, $payload] = $request('GET', '/api/v1/system/audit-logs', null, $bearer['patronB']);
$markers = $markersOf($payload);
check('le PATRON B voit le journal de sa société', in_array('MARKER_B', $markers, true), implode(',', $markers));
check('le PATRON B ne voit pas le journal de A', !in_array('MARKER_A', $markers, true), implode(',', $markers));

[$status, , $raw] = $request(
    'GET',
    '/api/v1/system/audit-logs?organizationUuid=' . $orgB->getUuid()->toRfc4122(),
    null,
    $bearer['patronA']
);
check('demander explicitement le journal de B est refusé', $status === 403, "obtenu {$status} : " . substr($raw, 0, 160));

[$status, , $raw] = $request(
    'GET',
    '/api/v1/system/audit-logs?organizationUuid=' . $orgA->getUuid()->toRfc4122(),
    null,
    $bearer['patronA']
);
check('demander explicitement son propre journal est accepté', $status === 200, "obtenu {$status} : " . substr($raw, 0, 160));

// ---------------------------------------------------------------------
// 4. Détail par UUID
// ---------------------------------------------------------------------
section('Détail par UUID');

[$status, , $raw] = $request(
    'GET',
    '/api/v1/system/audit-logs/' . $logA->getUuid()->toRfc4122(),
    null,
    $bearer['patronA']
);
check('le PATRON A lit une entrée de sa société', $status === 200, "obtenu {$status} : " . substr($raw, 0, 160));

[$status, , $raw] = $request(
    'GET',
    '/api/v1/system/audit-logs/' . $logB->getUuid()->toRfc4122(),
    null,
    $bearer['patronA']
);
check("le PATRON A ne lit pas une entrée de B", $status === 403, "obtenu {$status} : " . substr($raw, 0, 160));

[$status, , $raw] = $request(
    'GET',
    '/api/v1/system/audit-logs/' . $logB->getUuid()->toRfc4122(),
    null,
    $bearer['patronB']
);
check('le PATRON B lit une entrée de sa société', $status === 200, "obtenu {$status} : " . substr($raw, 0, 160));

[$status] = $request(
    'GET',
    '/api/v1/system/audit-logs/2b2f0f9c-0000-4000-8000-000000000000',
    null,
    $bearer['patronA']
);
check('un UUID inconnu renvoie 404', $status === 404, "obtenu {$status}");

// ---------------------------------------------------------------------
// 5. Non-régression : le SUPER_ADMIN voit toute la plateforme
// ---------------------------------------------------------------------
section('Non-régression : le SUPER_ADMIN reste global');

[$status, $payload] = $request('GET', '/api/v1/system/audit-logs', null, $bearer['root']);
$markers = $markersOf($payload);
check(
    'le SUPER_ADMIN voit les deux Organizations',
    $status === 200 && in_array('MARKER_A', $markers, true) && in_array('MARKER_B', $markers, true),
    implode(',', $markers)
);

$rollback();

echo "\n" . str_repeat('-', 61) . "\n";

if ($failures !== []) {
    echo 'ECHECS : ' . count($failures) . " sur {$checks}\n";

    foreach ($failures as $failure) {
        echo "  - {$failure}\n";
    }

    exit(1);
}

echo "SUCCES : {$checks} contrôles passés\n";
exit(0);