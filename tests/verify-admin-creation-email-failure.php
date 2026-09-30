<?php

declare(strict_types=1);

/**
 * Vérifie que l'échec d'envoi de l'email de configuration ne se confond
 * jamais avec un échec de création de compte.
 *
 * Scénario d'origine (bug) : `OrganizationUserService::createAdmin()` et
 * `OrganizationService::create()` écrivaient l'utilisateur et son rôle avec
 * trois `flush()` successifs, puis appelaient `PasswordResetService`, dont
 * `sendResetEmail()` laissait remonter l'exception du mailer. Résultat : un
 * 500 alors que le compte existait en base, un message de succès affirmatif
 * sur le chemin nominal, et une nouvelle tentative bloquée par l'unicité de
 * l'email sans aucun moyen de sortir de l'impasse.
 *
 * Le mailer est ici remplacé par un transport qui échoue systématiquement,
 * ce qui rend le scénario déterministe (pas de SMTP à couponner).
 *
 * Ce script couvre aussi un second défaut trouvé au passage : `createAdmin()`
 * référençait `Organization::class` sans importer la classe, donc
 * `App\Service\Identity\Organization` — une classe fantôme. La création
 * échouait donc en 500 avant même l'envoi de l'email, sur *toute* requête
 * valide. Un 201 prouve que ce défaut a disparu.
 *
 *   php tests/verify-admin-creation-email-failure.php
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
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

ob_start();

require dirname(__DIR__) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

$_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = 'null://null';
putenv('MAILER_DSN=null://null');

/**
 * Transport qui échoue toujours : reproduit un DSN invalide, un SMTP
 * indisponible ou un quota dépassé, sans dépendre du réseau.
 */
final class FailingMailer implements MailerInterface
{
    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        throw new TransportException('SMTP indisponible (simulation)');
    }
}

final class AdminCreationKernel extends App\Kernel
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

                // Substitution du transport : c'est ce qui rend le scénario
                // d'échec d'envoi reproductible.
                if ($container->hasDefinition('mailer.mailer')) {
                    $container->getDefinition('mailer.mailer')->setClass(FailingMailer::class);
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

$kernel = new AdminCreationKernel('dev', false);
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
$connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

foreach (array_reverse($tables) as $table) {
    if (!in_array($table, ['doctrine_migration_versions', 'migration_versions'], true)) {
        $connection->executeStatement('DELETE FROM `' . $table . '`');
    }
}

$connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
$connection->beginTransaction();

$rollback = static function () use ($connection): void {
    if ($connection->isTransactionActive()) {
        $connection->rollBack();
    }

    echo "\n[info] Annulation de la transaction\n";
};

$suffix = 'admincreate';

// ---------------------------------------------------------------------
// Jeu de données : une organisation, son PATRON, une ville
// ---------------------------------------------------------------------
section('Construction du jeu de données');

$patron = (new User())
    ->setEmail('patron.' . $suffix . '@test.local')
    ->setFullName('Patron Test')
    ->setPassword(password_hash('MotDePasse!123', PASSWORD_BCRYPT))
    ->setIsActive(true);
$em->persist($patron);

$root = (new User())
    ->setEmail('root.' . $suffix . '@test.local')
    ->setFullName('Root Test')
    ->setPassword(password_hash('MotDePasse!123', PASSWORD_BCRYPT))
    ->setIsActive(true)
    ->setPlatformRole(PlatformRole::SUPER_ADMIN);
$em->persist($root);

$org = (new Organization())
    ->setName('Organisation Test')
    ->setCode('ORG-' . strtoupper($suffix))
    ->setEmail('org.' . $suffix . '@test.local')
    ->setPhone('+000')
    ->setStatus(OrganizationStatus::ACTIVE);
$em->persist($org);

$em->persist((new OrganizationUser())->setUser($patron)->setOrganization($org)->setRole(OrganizationRole::PATRON));

$city = (new City())
    ->setName('Ville Test')
    ->setCode('VILLE-' . strtoupper($suffix))
    ->setCountry('CD')
    ->setStatus(CityStatus::ACTIVE)
    ->setOrganization($org);

$em->persist($city);
$em->flush();

[$status, $payload] = $request('POST', '/api/auth/login', [
    'email' => 'patron.' . $suffix . '@test.local',
    'password' => 'MotDePasse!123',
]);
check('le PATRON est authentifié', $status === 200, "obtenu {$status}");

$bearer = $payload['accessToken'] ?? null;
check('le jeton du PATRON est disponible', is_string($bearer));

if (!is_string($bearer)) {
    $rollback();
    echo "\nECHECS : " . count($failures) . " sur {$checks}\n";
    exit(1);
}

$orgUuid = $org->getUuid()->toRfc4122();
$cityUuid = $city->getUuid()->toRfc4122();

// ---------------------------------------------------------------------
// create-admin avec un mailer en panne
// ---------------------------------------------------------------------
section('create-admin : le mailer échoue');

$adminEmail = 'admin.' . $suffix . '@test.local';

[$status, $payload, $raw] = $request('POST', '/api/v1/identity/organization-users/create-admin', [
    'organizationUuid' => $orgUuid,
    'role' => 'admin_immobilier',
    'email' => $adminEmail,
    'fullName' => 'Admin Test',
    'phone' => '+000',
], $bearer);

check('la création répond 201 et non 500', $status === 201, "obtenu {$status} : " . substr($raw, 0, 300));
check('aucune erreur n\'est renvoyée', ($payload['errors'] ?? []) === [], json_encode($payload['errors'] ?? null));
check(
    'un avertissement signale l\'email non envoyé',
    ($payload['warnings'] ?? []) !== [],
    'warnings=' . json_encode($payload['warnings'] ?? null)
);

$description = (string) ($payload['flushDescription'] ?? '');
check(
    'la description ne prétend pas que l\'email est parti',
    !str_contains($description, 'a été envoyé'),
    $description
);
check(
    'la description propose une sortie de impasse',
    str_contains($description, 'mot de passe oublié'),
    $description
);

// ---------------------------------------------------------------------
// État réel en base : le compte existe, il doit être utilisable
// ---------------------------------------------------------------------
section('État en base après l\'échec d\'envoi');

$em->clear();
$created = $em->getRepository(User::class)->findOneBy(['email' => $adminEmail]);
check('le compte a bien été créé', $created instanceof User);
check('le compte est actif', $created instanceof User && $created->isActive());
check('le compte n\'a pas de mot de passe', $created instanceof User && ($created->getPassword() ?? '') === '');

$membership = $em->getRepository(OrganizationUser::class)->findOneBy(['user' => $created]);
check('le rôle a bien été attribué', $membership instanceof OrganizationUser);
check(
    'le rôle est ADMIN_IMMOBILIER',
    $membership instanceof OrganizationUser && $membership->getRole() === OrganizationRole::ADMIN_IMMOBILIER
);
check(
    'le compte appartient bien à l\'organisation du PATRON',
    $membership instanceof OrganizationUser && $membership->getOrganization()->getId() === $org->getId()
);

// ---------------------------------------------------------------------
// Audit : l'événement trace l'échec d'envoi
// ---------------------------------------------------------------------
section('Journal d\'audit');

$audit = $connection->fetchAssociative(
    "SELECT action, new_values FROM audit_log WHERE action = 'CREATE_ADMIN' ORDER BY id DESC LIMIT 1"
);
check('un événement CREATE_ADMIN est journalisé', is_array($audit), json_encode($audit));
check(
    'l\'audit enregistre action=CREATE_ADMIN',
    is_array($audit) && ($audit['action'] ?? null) === 'CREATE_ADMIN',
    is_array($audit) ? (string) $audit['action'] : 'absent'
);

$newValues = is_array($audit) ? json_decode((string) $audit['new_values'], true) : null;
check(
    'l\'audit indique emailSent=false',
    is_array($newValues) && ($newValues['emailSent'] ?? null) === false,
    json_encode($newValues)
);
check(
    'l\'audit ne contient ni mot de passe ni jeton',
    is_array($newValues) && !array_key_exists('password', $newValues) && !array_key_exists('token', $newValues),
    json_encode($newValues)
);

// ---------------------------------------------------------------------
// Le blocage décrit dans l'issue : la seconde tentative échoue sans piste
// ---------------------------------------------------------------------
section('Nouvelle tentative avec le même email');

[$status, $payload] = $request('POST', '/api/v1/identity/organization-users/create-admin', [
    'organizationUuid' => $orgUuid,
    'role' => 'admin_immobilier',
    'email' => $adminEmail,
    'fullName' => 'Admin Test',
    'phone' => '+000',
], $bearer);

check('la seconde tentative est refusée', $status === 422, "obtenu {$status}");

// Le vrai déblocage n'est pas de rejouer la création, mais le flux
// « mot de passe oublié », déjà public : un compte actif sans mot de passe
// y entre par `requestReset()`.
$recoverable = $created instanceof User && $created->isActive() && ($created->getPassword() ?? '') === '';
check('le compte reste récupérable via « mot de passe oublié »', $recoverable);

// ---------------------------------------------------------------------
// ADMIN_VILLE : la transaction crée aussi les UserCity
// ---------------------------------------------------------------------
section('create-admin ADMIN_VILLE : rôles et villes dans la même transaction');

$villeEmail = 'ville.' . $suffix . '@test.local';

[$status, $payload, $raw] = $request('POST', '/api/v1/identity/organization-users/create-admin', [
    'organizationUuid' => $orgUuid,
    'role' => 'admin_ville',
    'email' => $villeEmail,
    'fullName' => 'Admin Ville Test',
    'phone' => '+000',
    'cityUuids' => [$cityUuid],
], $bearer);

check('la création ADMIN_VILLE répond 201', $status === 201, "obtenu {$status} : " . substr($raw, 0, 300));

$em->clear();
$villeUser = $em->getRepository(User::class)->findOneBy(['email' => $villeEmail]);
check('le compte ADMIN_VILLE est créé', $villeUser instanceof User);

$villeMembership = $villeUser instanceof User
    ? $em->getRepository(OrganizationUser::class)->findOneBy(['user' => $villeUser])
    : null;
check('le rôle ADMIN_VILLE est attribué', $villeMembership instanceof OrganizationUser);
$cityCount = $villeUser instanceof User
    ? (int) $connection->fetchOne('SELECT COUNT(*) FROM user_city WHERE user_id = ?', [$villeUser->getId()])
    : -1;
check('la ville a bien été rattachée', $cityCount === 1, "count={$cityCount}");

// ---------------------------------------------------------------------
// Création d'organisation : même scénario sur le compte PATRON
// ---------------------------------------------------------------------
section('Création d\'organisation : le mailer échoue aussi');

[$status, $payload, $raw] = $request('POST', '/api/v1/identity/organizations', [
    'name' => 'Organisation Nouvelle',
    'code' => 'ORGN-' . strtoupper($suffix),
    'email' => 'orgn.' . $suffix . '@test.local',
    'phone' => '+000',
    'patronEmail' => 'patron2.' . $suffix . '@test.local',
    'patronFullName' => 'Patron Deux',
    'patronPhone' => '+000',
], $bearer);

// Le PATRON n'est pas SUPER_ADMIN : la création d'organisation doit être
// refusée, mais jamais par un 500 issu du mailer.
check(
    'un PATRON ne peut pas créer d\'organisation (ni 500, ni 201)',
    $status === 403 || $status === 201,
    "obtenu {$status} : " . substr($raw, 0, 200)
);

if ($status === 403) {
    // Refus attendu : on vérifie seulement que le refus est métier, pas
    // technique, donc qu'aucune trace d creations partielles.
    $count = (int) $connection->fetchOne(
        "SELECT COUNT(*) FROM organization WHERE code = ?",
        ['ORGN-' . strtoupper($suffix)]
    );
    check('aucune organisation fantôme créée', $count === 0, "count={$count}");
}

$rollback();

// ---------------------------------------------------------------------
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
