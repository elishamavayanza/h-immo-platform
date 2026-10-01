<?php

declare(strict_types=1);

/**
 * Empêche l'escalade de privilèges via `PUT /api/v1/identity/users/{uuid}`.
 *
 * `checkUserAccess()` ignorait son paramètre `$action` : il accordait l'accès
 * dès que l'appelant était la cible, ou partageait une Organization, sans
 * jamais consulter `applyRoleRuleOnOrganization()`. Comme `UserMapper` recopie
 * `platformRole` et applique `isActive` inconditionnellement, n'importe quel
 * compte authentifié pouvait :
 *
 *   - s'auto-attribuer `super_admin` (cas « soi-même », granted avant tout
 *     contrôle de rôle) ;
 *   - désactiver ou supprimer un autre membre de son Organization, y compris
 *     le PATRON, dont la matrice de rôle les exclut explicitement ;
 *   - réécrire le mot de passe d'un autre compte et en prendre le contrôle.
 *
 * Ce script fixe la matrice attendue : l'auto-service de profil reste ouvert,
 * les champs de privilège (platformRole, isActive) ne le sont pas.
 *
 *   php tests/verify-user-privilege-escalation.php
 */

use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationUser;
use App\Entity\Identity\User;
use App\Entity\Property\City;
use App\Enum\CityStatus;
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

final class EscalationKernel extends App\Kernel
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

$kernel = new EscalationKernel('dev', false);
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

$suffix = 'escalation';
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
// Jeu de données : une organisation, un PATRON, un ADMIN_VILLE, un membre
// ---------------------------------------------------------------------
section('Construction du jeu de données');

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

$patron = $makeUser('patron');
$adminVille = $makeUser('ville');
$membre = $makeUser('membre');

$org = (new Organization())
    ->setName('Organisation Test')
    ->setCode('ORG-' . strtoupper($suffix))
    ->setEmail('org.' . $suffix . '@test.local')
    ->setPhone('+000')
    ->setStatus(OrganizationStatus::ACTIVE);
$em->persist($org);

$city = (new City())
    ->setName('Ville Test')
    ->setCode('VILLE-' . strtoupper($suffix))
    ->setCountry('CD')
    ->setStatus(CityStatus::ACTIVE)
    ->setOrganization($org);
$em->persist($city);

$em->persist((new OrganizationUser())->setUser($patron)->setOrganization($org)->setRole(OrganizationRole::PATRON));
$em->persist((new OrganizationUser())->setUser($adminVille)->setOrganization($org)->setRole(OrganizationRole::ADMIN_VILLE));
$em->persist((new OrganizationUser())->setUser($membre)->setOrganization($org)->setRole(OrganizationRole::ADMIN_IMMOBILIER));
$em->flush();

$patronBearer = $login('patron.' . $suffix . '@test.local', $plainPassword);
$rootBearer = $login('root.' . $suffix . '@test.local', $plainPassword);
$membreBearer = $login('membre.' . $suffix . '@test.local', $plainPassword);

check('le PATRON est authentifié', $patronBearer !== null);
check('le SUPER_ADMIN est authentifié', $rootBearer !== null);
check('le membre est authentifié', $membreBearer !== null);

if ($patronBearer === null || $rootBearer === null || $membreBearer === null) {
    $rollback();
    echo "\nECHECS : " . count($failures) . " sur {$checks}\n";
    exit(1);
}

/** Corps de mise à jour valide : `firstName`/`lastName` sont requis par le DTO. */
$payload = static fn (array $overrides = []): array => array_merge([
    'firstName' => 'Nom',
    'lastName' => 'Test',
], $overrides);

$platformRoleOf = static function (User $user) use ($em): ?string {
    $em->clear();

    return $em->getRepository(User::class)->find($user->getId())?->getPlatformRole()?->value;
};

// ---------------------------------------------------------------------
// 1. Auto-promotion vers SUPER_ADMIN
// ---------------------------------------------------------------------
section('Un membre ne peut pas s\'auto-attribuer super_admin');

$ownUuid = $membre->getUuid()->toRfc4122();
[$status, , $raw] = $request('PUT', '/api/v1/identity/users/' . $ownUuid, $payload([
    'platformRole' => 'super_admin',
]), $membreBearer);

check('la mise à jour est refusée', $status === 403, "obtenu {$status} : " . substr($raw, 0, 200));
check(
    'le rôle de plateforme est resté nul',
    $platformRoleOf($membre) === null,
    'platformRole=' . var_export($platformRoleOf($membre), true)
);

// ---------------------------------------------------------------------
// 2. Désactivation d'un autre membre
// ---------------------------------------------------------------------
section('Un ADMIN_VILLE ne peut pas désactiver le PATRON');

$patronUuid = $patron->getUuid()->toRfc4122();
[$status, , $raw] = $request('PUT', '/api/v1/identity/users/' . $patronUuid, $payload([
    'isActive' => false,
]), $membreBearer);

check('la désactivation est refusée', $status === 403, "obtenu {$status} : " . substr($raw, 0, 200));

$em->clear();
$patronStillActive = $em->getRepository(User::class)->find($patron->getId())?->isActive();
check('le PATRON est toujours actif', $patronStillActive === true, 'isActive=' . var_export($patronStillActive, true));

// ---------------------------------------------------------------------
// 3. Prise de contrôle par réécriture de mot de passe
// ---------------------------------------------------------------------
section('Un membre ne peut pas réécrire le mot de passe du PATRON');

$hashBefore = (string) $connection->fetchOne('SELECT password FROM user WHERE id = ?', [$patron->getId()]);

[$status, , $raw] = $request('PUT', '/api/v1/identity/users/' . $patronUuid, $payload([
    'password' => 'MotDePassePiratE!2026',
]), $membreBearer);

check('la réécriture est refusée', $status === 403, "obtenu {$status} : " . substr($raw, 0, 200));

$hashAfter = (string) $connection->fetchOne('SELECT password FROM user WHERE id = ?', [$patron->getId()]);
check('le mot de passe du PATRON est inchangé', $hashBefore === $hashAfter);

// Le mot de passe d'origine fonctionne toujours : preuve qu'aucun
// détournement n'a eu lieu.
[$loginStatus] = $request('POST', '/api/auth/login', [
    'email' => 'patron.' . $suffix . '@test.local',
    'password' => $plainPassword,
]);
check('le PATRON peut toujours se connecter avec son mot de passe', $loginStatus === 200, "obtenu {$loginStatus}");

// ---------------------------------------------------------------------
// 4. Suppression logique d'un autre membre
// ---------------------------------------------------------------------
section('Un membre ne peut pas supprimer le PATRON');

[$status, , $raw] = $request('DELETE', '/api/v1/identity/users/' . $patronUuid, null, $membreBearer);
check('la suppression est refusée', $status === 403, "obtenu {$status} : " . substr($raw, 0, 200));

$stillThere = (int) $connection->fetchOne(
    'SELECT COUNT(*) FROM user WHERE id = ? AND deleted_at IS NULL',
    [$patron->getId()]
);
check('le PATRON existe toujours', $stillThere === 1, "count={$stillThere}");

// ---------------------------------------------------------------------
// 5. Non-régression : l'auto-service de profil reste ouvert
// ---------------------------------------------------------------------
section('Non-régression : auto-service de profil');

[$status, , $raw] = $request('PUT', '/api/v1/identity/users/' . $ownUuid, $payload([
    'phone' => '+243990000001',
]), $membreBearer);
check('un membre peut modifier sa propre fiche', $status === 200, "obtenu {$status} : " . substr($raw, 0, 200));

$em->clear();
$phone = $em->getRepository(User::class)->find($membre->getId())?->getPhone();
check('le téléphone a bien été enregistré', $phone === '+243990000001', (string) $phone);

// ---------------------------------------------------------------------
// 6. Non-régression : le PATRON gère toujours les membres de son tenant
// ---------------------------------------------------------------------
section('Non-régression : le PATRON gère les membres de son Organization');

$membreUuid = $membre->getUuid()->toRfc4122();
[$status, , $raw] = $request('PUT', '/api/v1/identity/users/' . $membreUuid, $payload([
    'phone' => '+243990000002',
]), $patronBearer);
check('le PATRON peut modifier un membre', $status === 200, "obtenu {$status} : " . substr($raw, 0, 200));

// ---------------------------------------------------------------------
// 7. Non-régression : le SUPER_ADMIN administre toujours la plateforme
// ---------------------------------------------------------------------
section('Non-régression : le SUPER_ADMIN reste administrateur');

[$status, , $raw] = $request('PUT', '/api/v1/identity/users/' . $membreUuid, $payload([
    'platformRole' => 'super_admin',
]), $rootBearer);
check('le SUPER_ADMIN peut fixer platformRole', $status === 200, "obtenu {$status} : " . substr($raw, 0, 200));
check('le rôle a été appliqué', $platformRoleOf($membre) === 'super_admin', 'platformRole=' . var_export($platformRoleOf($membre), true));

// ---------------------------------------------------------------------
// 8. Non-régression : isActive absent ne doit rien modifier
// ---------------------------------------------------------------------
section('Non-régression : un PUT sans isActive ne réactive pas un compte');

$connection->executeStatement('UPDATE user SET is_active = 0 WHERE id = ?', [$membre->getId()]);

[$status] = $request('PUT', '/api/v1/identity/users/' . $membreUuid, $payload([
    'phone' => '+243990000003',
]), $patronBearer);

$em->clear();
$isActive = $em->getRepository(User::class)->find($membre->getId())?->isActive();
check(
    'un compte suspendu reste suspendu après un PUT sans isActive',
    $status === 200 && $isActive === false,
    "statut={$status}, isActive=" . var_export($isActive, true)
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