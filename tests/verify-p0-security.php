<?php

declare(strict_types=1);

/*
 * Vérification exécutable des quatre correctifs P0.
 *
 * Chaque correctif a supprimé une faille qui ne se voyait pas au niveau des
 * unités : ils portent sur l'autorisation, l'isolation entre organisations et
 * l'intégrité d'état. Ce harnais les rejoue de bout en bout.
 *
 *  - P0-1  Les rapports exigent l'organisation visée et le rôle réellement
 *          détenu dans cette organisation. Aucun Voter ni role_hierarchy
 *          n'existe : `isGranted('ROLE_PATRON')` était donc refusé à tout le
 *          monde, y compris aux vrais Patrons.
 *  - P0-2  Le cumul des dépenses par ville filtrait sur une liste de villes
 *          vide, donc sur toutes les villes : un rapport PATRON exposait le
 *          chiffre d'affaires des dépenses d'une organisation concurrente.
 *  - P0-3  Les médias (photo de profil, logo, photos de parcelle) étaient
 *          accessibles et supprimables sans contrôle d'appartenance, et la
 *          suppression acceptait un chemin remontant hors de `uploads/`.
 *  - P0-4  Le statut d'un loyer et d'un bail se saisissait dans le corps de
 *          la requête : un PATCH pouvait remettre un loyer soldé à « pending »
 *          en ne changeant que sa date, et un bail actif à « brouillon ».
 *
 * Les requêtes passent par le noyau (`$kernel->handle()`) dans le même
 * processus et la même transaction que la construction du jeu de données :
 * c'est la seule façon d'éprouver le binding des DTO et le pare-feu sans
 *依存 à une base de données séparée.
 *
 * Usage : php tests/verify-p0-security.php
 */

use App\Entity\Expense\Expense;
use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationUser;
use App\Entity\Identity\User;
use App\Entity\Identity\UserCity;
use App\Entity\Property\Building;
use App\Entity\Property\City;
use App\Entity\Property\Parcel;
use App\Entity\Property\Unit;
use App\Entity\Rental\Lease;
use App\Entity\Rental\Payment;
use App\Entity\Rental\Rent;
use App\Entity\Rental\Tenant;
use App\Enum\BuildingType;
use App\Enum\CityStatus;
use App\Enum\Currency;
use App\Enum\ExpenseCategory;
use App\Enum\LeaseStatus;
use App\Enum\OrganizationRole;
use App\Enum\OrganizationStatus;
use App\Enum\PaymentMethod;
use App\Enum\PlatformRole;
use App\Enum\RentStatus;
use App\Enum\TenantType;
use App\Enum\UnitType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

/**
 * Expose les repositories le temps du harnais : ils sont privés, donc
 * inlinés, en production. Le harnais doit pourtant exécuter leurs requêtes.
 */
final class P0Kernel extends App\Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach ($container->getDefinitions() as $id => $definition) {
                    if (
                        str_starts_with($id, 'App\\Repository\\')
                        || str_starts_with($id, 'App\\Service\\')
                        || $id === 'App\\Security\\SecurityService'
                        || $id === 'doctrine'
                        || $id === 'security.token_storage'
                    ) {
                        $definition->setPublic(true);
                    }
                }
            }
        });
    }
}

$kernel = new P0Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

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

/**
 * Exécute une requête à travers le noyau et renvoie [code, corps décodé].
 *
 * @param array<string, mixed>|null $json
 *
 * @return array{0: int, 1: array<string, mixed>|null, 2: string}
 */
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

/** @var EntityManagerInterface $em */
$em = $container->get('doctrine')->getManager();
$container->get('cache.rate_limiter')->clear();

// `ManagerRegistry::resetManager()` détache les entités entre deux requêtes
// passant par le noyau : la base est donc purgée puis la transaction annulée
// en fin de script, pour rester rejouable.
$connection = $em->getConnection();
$database = (string) $connection->fetchOne('SELECT DATABASE()');

$tables = $connection->fetchFirstColumn(
    'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()'
);
$connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

foreach (array_reverse($tables) as $table) {
    // Jamais purger : l'historique des migrations doit survivre.
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
};

register_shutdown_function(static function () use ($rollback): void {
    $rollback();
});

echo "  Base : {$database} (transaction annulée en fin de script)\n";

$suffix = 'p0' . bin2hex(random_bytes(3));

// ---------------------------------------------------------------------
// Jeu de données : deux organisations concurrentes
// ---------------------------------------------------------------------
section('Construction du jeu de données (2 organisations concurrentes)');

$makeUser = static function (string $email, ?PlatformRole $role = null) use ($em, $suffix): User {
    $user = (new User())
        ->setEmail($email . '.' . $suffix . '@test.local')
        ->setFullName($email)
        ->setPassword(password_hash('MotDePasse!123', PASSWORD_BCRYPT))
        ->setIsActive(true);

    if ($role !== null) {
        $user->setPlatformRole($role);
    }

    $em->persist($user);

    return $user;
};

$superAdmin = $makeUser('root', PlatformRole::SUPER_ADMIN);
$patronA = $makeUser('patron.a');
$patronB = $makeUser('patron.b');
$adminImmoA = $makeUser('immo.a');
$adminVilleA = $makeUser('ville.a');
// Compte de AOTH, RATTACHÉ en plus à l'organisation B. C'est le cas que la
// correction d'origine ne gérait pas : sans `organizationUuid` explicite,
// l'ancien code prenait la première appartenance et pouvait lire B par défaut.
$patronDouble = $makeUser('patron.double');

$makeOrg = static function (string $name, string $code) use ($em, $suffix): Organization {
    $org = (new Organization())
        ->setName($name)
        ->setCode($code . '-' . $suffix)
        ->setEmail(strtolower($code) . $suffix . '@orga.test')
        ->setPhone('+000')
        ->setStatus(OrganizationStatus::ACTIVE);

    $em->persist($org);

    return $org;
};

$orgA = $makeOrg('Organisation A', 'ORGA');
$orgB = $makeOrg('Organisation B', 'ORGB');

$membership = static function (User $user, Organization $org, OrganizationRole $role) use ($em): OrganizationUser {
    $ou = (new OrganizationUser())->setUser($user)->setOrganization($org)->setRole($role);
    $em->persist($ou);

    return $ou;
};

$membership($patronA, $orgA, OrganizationRole::PATRON);
$membership($patronB, $orgB, OrganizationRole::PATRON);
$membership($adminImmoA, $orgA, OrganizationRole::ADMIN_IMMOBILIER);
$membership($adminVilleA, $orgA, OrganizationRole::ADMIN_VILLE);
$membership($patronDouble, $orgA, OrganizationRole::PATRON);
$membership($patronDouble, $orgB, OrganizationRole::PATRON);

$makeCity = static function (string $name, string $code, Organization $org) use ($em, $suffix): City {
    $city = (new City())
        ->setName($name)
        ->setCode($code . '-' . $suffix)
        ->setCountry('CD')
        ->setStatus(CityStatus::ACTIVE)
        ->setOrganization($org);

    $em->persist($city);

    return $city;
};

$cityA1 = $makeCity('Ville A1', 'A1', $orgA);
$cityB1 = $makeCity('Ville B1', 'B1', $orgB);

// L'ADMIN_VILLE n'est attribué qu'à A1 : il ne doit pas voir B1.
$em->persist((new UserCity())->setUser($adminVilleA)->setCity($cityA1));

$makeParcel = static function (string $ref, City $city) use ($em): Parcel {
    $p = (new Parcel())
        ->setReference($ref)
        ->setName('Parcelle ' . $ref)
        ->setAddress('Adresse ' . $ref)
        ->setArea('100')
        ->setCity($city);

    $em->persist($p);

    return $p;
};

$parcelA1 = $makeParcel('PA-1-' . $suffix, $cityA1);
$parcelB1 = $makeParcel('PB-1-' . $suffix, $cityB1);

$makeUnit = static function (string $ref, Parcel $parcel) use ($em): Unit {
    // L'association ne cascade pas : le batiment est persiste d'abord.
    $b = (new Building())
        ->setReference($ref . '-B')
        ->setName('Batiment ' . $ref)
        ->setType(BuildingType::MIXED)
        ->setParcel($parcel);
    $em->persist($b);

    $u = (new Unit())
        ->setReference($ref)
        ->setBuilding($b)
        ->setType(UnitType::APARTMENT)
        ->setFloor(1)
        ->setSurface('60')
        ->setMonthlyRent('500.00')
        ->setCurrency(Currency::USD);

    $em->persist($u);

    return $u;
};

$unitA1 = $makeUnit('UA-1-' . $suffix, $parcelA1);
$unitB1 = $makeUnit('UB-1-' . $suffix, $parcelB1);

$tenantA = (new Tenant())
    ->setFullName('Locataire A')
    ->setType(TenantType::INDIVIDUAL)
    ->setPhone('+000')
    ->setOrganization($orgA);
$em->persist($tenantA);

// Dépenses : montants volontairement distincts pour reconnaître à qui elles
// appartiennent dans un cumul.
$makeExpense = static function (City $city, string $amount, User $author) use ($em, $suffix): Expense {
    $e = (new Expense())
        ->setOrganization($city->getOrganization())
        ->setCity($city)
        ->setCreatedBy($author)
        ->setCategory(ExpenseCategory::MAINTENANCE)
        ->setAmount($amount)
        ->setCurrency(Currency::USD)
        ->setExpenseDate(new \DateTimeImmutable('2026-03-15'))
        ->setReference('EXP-' . $suffix . '-' . $amount);

    $em->persist($e);

    return $e;
};

$makeExpense($cityA1, '111.00', $patronA);
$makeExpense($cityB1, '999.00', $patronB);

$leaseA = (new Lease())
    ->setOrganization($orgA)
    ->setTenant($tenantA)
    ->setUnit($unitA1)
    ->setReference('BAIL-A-' . $suffix)
    ->setStartDate(new \DateTimeImmutable('2026-01-01'))
    ->setMonthlyRent('500.00')
    ->setCurrency(Currency::USD)
    ->setStatus(LeaseStatus::ACTIVE);

$em->persist($leaseA);

$rentA = (new Rent())
    ->setLease($leaseA)
    ->setPeriod(new \DateTimeImmutable('2026-01-01'))
    ->setDueDate(new \DateTimeImmutable('2026-01-05'))
    ->setAmount('500.00')
    ->setCurrency(Currency::USD)
    ->setStatus(RentStatus::PENDING);

$em->persist($rentA);
$em->flush();

// Un paiement partiel puis un solde, pour éprouver le recalcul de statut.
$em->persist(
    (new Payment())
        ->setRent($rentA)
        ->setAmount('200.00')
        ->setCurrency(Currency::USD)
        ->setPaymentDate(new \DateTimeImmutable('2026-01-10'))
        ->setCreatedBy($patronA)
        ->setMethod(PaymentMethod::CASH)
        ->setReference('PAY-1-' . $suffix)
);

$em->flush();
$em->clear();

echo "  Jeu de données créé.\n";

$login = static function (string $email) use ($request, $suffix): string {
    [$status, $payload] = $request('POST', '/api/auth/login', [
        'email' => $email . '.' . $suffix . '@test.local',
        'password' => 'MotDePasse!123',
    ]);

    $token = is_array($payload) ? ($payload['accessToken'] ?? null) : null;
    if ($status !== 200 || !is_string($token)) {
        fwrite(STDERR, "[erreur] login {$email} -> {$status}\n");
        exit(1);
    }

    return $token;
};

// ---------------------------------------------------------------------
// P0-1 — Les rapports exigent l'organisation visée et le bon rôle
// ---------------------------------------------------------------------
section('P0-1 : autorisation des rapports (organisation explicite + rôle réel)');

$uuidA = $orgA->getUuid()->toRfc4122();
$uuidB = $orgB->getUuid()->toRfc4122();

$tokenPatronA = $login('patron.a');
$tokenPatronDouble = $login('patron.double');
$tokenImmoA = $login('immo.a');
$tokenVilleA = $login('ville.a');
$tokenSuper = $login('root');

[$status] = $request('GET', '/api/v1/reports/patron', null, $tokenPatronA);
check(
    'sans organizationUuid, le rapport PATRON est refusé (400)',
    $status === 400,
    "obtenu {$status}"
);

[$status] = $request('GET', '/api/v1/reports/patron?organizationUuid=pas-un-uuid', null, $tokenPatronA);
check(
    'un organizationUuid mal formé est refusé (400)',
    $status === 400,
    "obtenu {$status}"
);

[$status] = $request('GET', '/api/v1/reports/patron?organizationUuid=00000000-0000-4000-8000-000000000000', null, $tokenPatronA);
check(
    'une organization inexistante renvoie 404',
    $status === 404,
    "obtenu {$status}"
);

[$status] = $request('GET', '/api/v1/reports/patron?organizationUuid=' . $uuidB, null, $tokenPatronA);
check(
    'le PATRON de A ne lit pas le rapport de B (403)',
    $status === 403,
    "obtenu {$status}"
);

[$status, $payload] = $request('GET', '/api/v1/reports/patron?organizationUuid=' . $uuidA, null, $tokenPatronA);
check(
    'le PATRON de A lit le rapport de A (200)',
    $status === 200,
    "obtenu {$status} | " . substr((string) ($payload['message'] ?? ''), 0, 120)
);

// Le cas que l'ancien code ne gérait pas : un compte appartenant à deux
// organisations doit lire celle qu'il designate, pas « la première ».
[$status, $payload] = $request('GET', '/api/v1/reports/patron?organizationUuid=' . $uuidB, null, $tokenPatronDouble);
check(
    'un compte à double appartenance lit B quand il la désigne (200)',
    $status === 200,
    "obtenu {$status}"
);

[$status] = $request('GET', '/api/v1/reports/admin-immobilier?organizationUuid=' . $uuidA, null, $tokenImmoA);
check(
    "l'ADMIN_IMMOBILIER lit le rapport immobilier de son organisation (200)",
    $status === 200,
    "obtenu {$status}"
);

[$status] = $request('GET', '/api/v1/reports/admin-immobilier?organizationUuid=' . $uuidA, null, $tokenPatronA);
check(
    "un PATRON n'accède pas au rapport d'ADMIN_IMMOBILIER (403)",
    $status === 403,
    "obtenu {$status}"
);

[$status] = $request('GET', '/api/v1/reports/admin-immobilier?organizationUuid=' . $uuidB, null, $tokenImmoA);
check(
    "l'ADMIN_IMMOBILIER de A ne lit pas le rapport de B (403)",
    $status === 403,
    "obtenu {$status}"
);

[$status] = $request('GET', '/api/v1/reports/admin-ville/' . $cityA1->getUuid()->toRfc4122(), null, $tokenVilleA);
check(
    "l'ADMIN_VILLE lit le rapport de SA ville (200)",
    $status === 200,
    "obtenu {$status}"
);

[$status] = $request('GET', '/api/v1/reports/admin-ville/' . $cityB1->getUuid()->toRfc4122(), null, $tokenVilleA);
check(
    "l'ADMIN_VILLE ne lit pas le rapport d'une ville qui n'est pas la sienne (403)",
    $status === 403,
    "obtenu {$status}"
);

[$status] = $request('GET', '/api/v1/reports/super-admin', null, $tokenSuper);
check('le SUPER_ADMIN lit le rapport plateforme (200)', $status === 200, "obtenu {$status}");

[$status] = $request('GET', '/api/v1/reports/super-admin', null, $tokenPatronA);
check('un PATRON n\'accède pas au rapport plateforme (403)', $status === 403, "obtenu {$status}");

[$status] = $request('GET', '/api/v1/reports/patron?organizationUuid=' . $uuidA);
check('un appel anonyme est refusé (401)', $status === 401, "obtenu {$status}");

// ---------------------------------------------------------------------
// P0-2 — Le cumul des dépenses ne traverse pas les organisations
// ---------------------------------------------------------------------
section('P0-2 : isolation des dépenses entre organisations');

$periodFrom = rawurlencode('2026-01-01');
$periodTo = rawurlencode('2026-12-31');

[$status, $payload] = $request(
    'GET',
    "/api/v1/reports/patron?organizationUuid={$uuidA}&periodFrom={$periodFrom}&periodTo={$periodTo}",
    null,
    $tokenPatronA
);

// La forme reelle est inspectee une fois, pour distinguer « aucune dépense »
// d« un champ mal adresse ».
$expensesByCityA = null;
if (is_array($payload)) {
    $expensesByCityA = $payload['expensesByCity']
        ?? $payload['data']['expensesByCity']
        ?? $payload['data']['data']['expensesByCity']
        ?? null;
}
check(
    'le rapport de A agrège ses dépenses par ville',
    is_array($expensesByCityA) && $expensesByCityA !== [],
    json_encode($expensesByCityA) . ' | cles=' . json_encode(is_array($payload) ? array_keys($payload) : null)
);

// Les champs du DTO sont `totalAmount` / `levelLabel` / `levelUuid`.
$montantsA = array_map(
    static fn (array $ligne): float => (float) ($ligne['totalAmount'] ?? 0),
    is_array($expensesByCityA) ? $expensesByCityA : []
);
$villesA = array_map(
    static fn (array $ligne): string => (string) ($ligne['levelLabel'] ?? ''),
    is_array($expensesByCityA) ? $expensesByCityA : []
);
$uploadsA = array_map(
    static fn (array $ligne): string => (string) ($ligne['levelUuid'] ?? ''),
    is_array($expensesByCityA) ? $expensesByCityA : []
);

// Le point central : la dépense de 999,00 appartient à B. Elle ne doit
// apparaître ni en montant ni en libellé de ville dans le rapport de A.
check(
    'le rapport de A ne contient pas le montant de la dépense de B',
    !in_array('999.00', array_map(static fn (float $m): string => number_format($m, 2, '.', ''), $montantsA), true),
    json_encode($montantsA)
);
check(
    "le rapport de A ne cite pas la ville de B",
    !in_array('Ville B1', $villesA, true),
    json_encode($villesA)
);
check(
    'le rapport de A contient bien la dépense de sa ville (111,00)',
    in_array(111.00, $montantsA, true),
    json_encode($montantsA)
);
check(
    'le niveau agrégé est bien la ville de A, pas celle de B',
    in_array($cityA1->getUuid()->toRfc4122(), $uploadsA, true)
    && !in_array($cityB1->getUuid()->toRfc4122(), $uploadsA, true),
    json_encode($uploadsA)
);

// Une organisation sans aucune ville ne doit pas produire un cumul global :
// c'est précisément le cas qui fuyait.
[$status, $payloadVide] = $request(
    'GET',
    "/api/v1/reports/patron?organizationUuid={$uuidA}&periodFrom=" . rawurlencode('1990-01-01') . "&periodTo=" . rawurlencode('1990-12-31'),
    null,
    $tokenPatronA
);
$vide = is_array($payloadVide)
    ? ($payloadVide['data']['expensesByCity']
        ?? $payloadVide['expensesByCity']
        ?? $payloadVide['data']['data']['expensesByCity']
        ?? null)
    : null;
check(
    'une période sans dépense ne renvoie pas de cumul global',
    $status === 200 && $vide === [],
    json_encode($vide)
);

// ---------------------------------------------------------------------
// P0-3 — Les médias sont bornés à leur propriétaire et au dossier uploads
// ---------------------------------------------------------------------
section('P0-3 : autorisation et confines des médias');

$fileUpload = $container->get(App\Service\System\FileUploadService::class);

check(
    'un chemin remontant hors de uploads/ est refusé',
    $fileUpload->resolveInsideUploads('parcels/../../.env') === null
);
check(
    'un chemin absolu est refusé',
    $fileUpload->resolveInsideUploads('/etc/passwd') === null
);
check(
    'un antislash est refusé',
    $fileUpload->resolveInsideUploads('parcels\\..\\..\\.env') === null
);
check(
    'un segment vide ou un point est refusé',
    $fileUpload->resolveInsideUploads('parcels//./x.jpg') === null
);
check(
    'un chemin absent du dossier uploads est refusé',
    $fileUpload->resolveInsideUploads('documents/inexistant.pdf') === null
);

// Le point dur : la suppression d'une photo de parcelle ne doit pas pouvoir
// viser un fichier d'une autre parcelle ni remonter à l'extérieur.
$em->clear();

$parcelUuidA = $parcelA1->getUuid()->toRfc4122();

// Le PATRON administre son organisation entière : la seule frontière qui
// compte ici est celle entre organisations, pas le rôle.
$tokenPatronB = $login('patron.b');

[$status] = $request(
    'DELETE',
    "/api/v1/media/parcels/{$parcelUuidA}/photos/photo.png",
    null,
    $tokenPatronB
);
check(
    "le PATRON de B ne supprime pas une photo d'une parcelle de A (403)",
    $status === 403,
    "obtenu {$status}"
);

[$status] = $request('DELETE', "/api/v1/media/organizations/{$uuidA}/logo", null, $tokenPatronB);
check(
    "le PATRON de B ne supprime pas le logo de A (403)",
    $status === 403,
    "obtenu {$status}"
);

[$status] = $request('DELETE', "/api/v1/media/users/{$patronA->getUuid()->toRfc4122()}/photo", null, $tokenPatronB);
check(
    "le PATRON de B ne supprime pas la photo de profil d'un compte de A (403)",
    $status === 403,
    "obtenu {$status}"
);

// Un chemin de traversée ne doit rien régler, même pour le propriétaire
// légitime de la parcelle : le fichier visé est hors de son dossier.
[$status] = $request(
    'DELETE',
    '/api/v1/media/parcels/' . $parcelUuidA . '/photos/..%2F..%2F.env',
    null,
    $tokenPatronA
);
check(
    'une traversée de chemin sur la suppression est refusée (404, fichier hors dossier)',
    $status === 404,
    "obtenu {$status}"
);

[$status] = $request('DELETE', '/api/v1/media/users/00000000-0000-4000-8000-000000000000/photo', null, $tokenPatronA);
check(
    'une photo de profil inexistante renvoie 404',
    $status === 404,
    "obtenu {$status}"
);

// ---------------------------------------------------------------------
// P0-4 — Le statut n'est plus saisissable
// ---------------------------------------------------------------------
section('P0-4 : intégrité du statut des loyers et des baux');

$leaseUuidA = $leaseA->getUuid()->toRfc4122();
$rentUuidA = $rentA->getUuid()->toRfc4122();

// Un client tente d'imposer un statut. Le DTO ne contient plus le champ :
// la valeur envoyée doit être ignorée, pas appliquée.
// Changement de date d'un loyer soldé : le statut ne doit surtout pas
// revenir à « pending » sous l'effet d'une valeur par défaut du DTO.
// Le groupe « update » exige la charge utile complète : un PATCH n'est pas
// partiel dans cette API, il faut donc renvoyer aussi le montant et la
// devise. C'est précisément ce qui rend le test réaliste : la valeur par
// défaut du DTO qui écrasait le statut était renvoyée par un PATCH «
// juste la date », que la validation aurait dû laisser passer.
[$status, $payload] = $request(
    'PATCH',
    "/api/v1/rents/{$rentUuidA}",
    ['dueDate' => '2026-02-05', 'amount' => '500.00', 'currency' => 'USD'],
    $tokenImmoA
);
check(
    "le PATCH d'une échéance est accepté (200)",
    $status === 200,
    "obtenu {$status} | " . substr((string) ($payload['message'] ?? ''), 0, 120)
);

$em->clear();
$rentApresPatch = $container->get('doctrine')->getManager()
    ->getRepository(Rent::class)
    ->findOneBy(['uuid' => $rentUuidA]);

$statutApresPatch = $rentApresPatch?->getStatus();
check(
    "un PATCH de date ne remet pas l'échéance en attente",
    $statutApresPatch === RentStatus::PARTIALLY_PAID,
    'statut=' . ($statutApresPatch?->value ?? 'null')
);

// La dérivation doit refléter les paiements, pas la saisie.
// L'échéance a reçu 200,00 sur 500,00 : partielle, quelle que soit la date.
$em->clear();
$rentSynthese = (new Rent())
    ->setLease($leaseA)
    ->setPeriod(new \DateTimeImmutable('2026-01-01'))
    ->setDueDate(new \DateTimeImmutable('2000-01-01'))
    ->setAmount('500.00')
    ->setCurrency(Currency::USD);

check(
    'un loyer sans paiement et échu devient OVERDUE (dérivé de la date)',
    $rentSynthese->syncStatus('0.00') === RentStatus::OVERDUE,
    'statut=' . $rentSynthese->getStatus()->value
);

check(
    'un loyer sans paiement et non échu reste PENDING',
    (new Rent())
        ->setLease($leaseA)
        ->setPeriod(new \DateTimeImmutable('2026-01-01'))
        ->setDueDate(new \DateTimeImmutable('2999-01-01'))
        ->setAmount('500.00')
        ->setCurrency(Currency::USD)
        ->syncStatus('0.00') === RentStatus::PENDING
);

check(
    'un paiement partiel donne PARTIALLY_PAID',
    (new Rent())
        ->setLease($leaseA)
        ->setPeriod(new \DateTimeImmutable('2026-01-01'))
        ->setDueDate(new \DateTimeImmutable('2000-01-01'))
        ->setAmount('500.00')
        ->setCurrency(Currency::USD)
        ->syncStatus('200.00') === RentStatus::PARTIALLY_PAID
);

check(
    'un solde donne PAID, même échu',
    (new Rent())
        ->setLease($leaseA)
        ->setPeriod(new \DateTimeImmutable('2026-01-01'))
        ->setDueDate(new \DateTimeImmutable('2000-01-01'))
        ->setAmount('500.00')
        ->setCurrency(Currency::USD)
        ->syncStatus('500.00') === RentStatus::PAID
);

// Un centime d'écart d'arrondi ne doit pas rouvrir un impayé.
check(
    'un solde avec centime d\'arrondi reste PAID',
    (new Rent())
        ->setLease($leaseA)
        ->setPeriod(new \DateTimeImmutable('2026-01-01'))
        ->setDueDate(new \DateTimeImmutable('2000-01-01'))
        ->setAmount('500.00')
        ->setCurrency(Currency::USD)
        ->syncStatus('499.995') === RentStatus::PAID
);

// Bail : le statut ne se saisit plus, il se transitioned.
[$status] = $request(
    'PATCH',
    "/api/v1/leases/{$leaseUuidA}",
    [
        'startDate' => '2026-01-01',
        'monthlyRent' => '500.00',
        'currency' => 'USD',
        'status' => 'DRAFT',
    ],
    $tokenImmoA
);
check(
    "un PATCH ne peut pas faire tomber un bail actif en brouillon",
    $status === 200,
    "obtenu {$status}"
);

$em->clear();
$bailApresPatch = $container->get('doctrine')->getManager()
    ->getRepository(Lease::class)
    ->findOneBy(['uuid' => $leaseUuidA]);

check(
    'le bail reste ACTIVE après un PATCH contenant status',
    $bailApresPatch?->getStatus() === LeaseStatus::ACTIVE,
    'statut=' . ($bailApresPatch?->getStatus()->value ?? 'null')
);

// Les transitions dédiées existent et sont soumises à leur contrôle.
[$status] = $request('PATCH', "/api/v1/leases/{$leaseUuidA}/terminate", ['reason' => 'Test'], $tokenImmoA);
check('la résiliation d\'un bail actif réussit (200)', $status === 200, "obtenu {$status}");

$em->clear();
$bailApresResiliation = $container->get('doctrine')->getManager()
    ->getRepository(Lease::class)
    ->findOneBy(['uuid' => $leaseUuidA]);
check(
    'le bail résilié passe à TERMINATED',
    $bailApresResiliation?->getStatus() === LeaseStatus::TERMINATED,
    'statut=' . ($bailApresResiliation?->getStatus()->value ?? 'null')
);

[$status] = $request('PATCH', "/api/v1/leases/{$leaseUuidA}/activate", null, $tokenImmoA);
check(
    'un bail résilié ne peut plus être activé (409)',
    $status === 409,
    "obtenu {$status}"
);

[$status] = $request('PATCH', "/api/v1/leases/{$leaseUuidA}/cancel", ['reason' => 'Test'], $tokenImmoA);
check(
    'un bail résilié ne peut plus être annulé (409)',
    $status === 409,
    "obtenu {$status}"
);

// Un PATRON ne doit pas pouvoir piloter les transitions.
// Les requetes precedentes ont detache les entites : on les recharge, sinon
// Doctrine refuse de persister un bail qui pointe vers une entite fantome.
$em->clear();
$manager = $container->get('doctrine')->getManager();
$orgA = $manager->getRepository(Organization::class)->findOneBy(['uuid' => $uuidA]);
$tenantA = $manager->getRepository(Tenant::class)->findOneBy(['organization' => $orgA]);
$unitA1 = $manager->getRepository(Unit::class)->findOneBy(['reference' => 'UA-1-' . $suffix]);

$leaseDraft = (new Lease())
    ->setOrganization($orgA)
    ->setTenant($tenantA)
    ->setUnit($unitA1)
    ->setReference('BAIL-DRAFT-' . $suffix)
    ->setStartDate(new \DateTimeImmutable('2026-01-01'))
    ->setMonthlyRent('500.00')
    ->setCurrency(Currency::USD);
$em->persist($leaseDraft);
$em->flush();
$leaseDraftUuid = $leaseDraft->getUuid()->toRfc4122();

// Le PATRON administre toute son organisation : l'activation par lui est
// voulue. Ce qui ne l'est pas, c'est piloter un bail d'une AUTRE
// organisation — c'est la frontière à éprouver.
[$status] = $request('PATCH', "/api/v1/leases/{$leaseDraftUuid}/activate", null, $tokenPatronB);
check(
    "le PATRON de B n'active pas un bail de A (403)",
    $status === 403,
    "obtenu {$status}"
);

[$status] = $request('PATCH', "/api/v1/leases/{$leaseDraftUuid}/activate", null, $tokenPatronA);
check(
    "le PATRON de A active un bail de SA propre organisation (200)",
    $status === 200,
    "obtenu {$status}"
);

[$status] = $request('PATCH', "/api/v1/leases/{$leaseDraftUuid}/activate", null, $tokenImmoA);
check(
    "un bail déjà actif ne s'active pas deux fois (409)",
    $status === 409,
    "obtenu {$status}"
);

$em->clear();
$bailActive = $container->get('doctrine')->getManager()
    ->getRepository(Lease::class)
    ->findOneBy(['uuid' => $leaseDraftUuid]);
check(
    'le bail activé passe à ACTIVE',
    $bailActive?->getStatus() === LeaseStatus::ACTIVE,
    'statut=' . ($bailActive?->getStatus()->value ?? 'null')
);

[$status] = $request('PATCH', "/api/v1/leases/{$leaseDraftUuid}/cancel", ['reason' => 'Erreur de saisie'], $tokenImmoA);
check(
    'un bail actif ne s\'annule pas mais se résilie (409)',
    $status === 409,
    "obtenu {$status}"
);

// ---------------------------------------------------------------------
section('Annulation de la transaction');

$rollback();

echo "\n" . str_repeat('-', 60) . "\n";

if ($failures === []) {
    echo "SUCCES : {$checks} contrôles passés\n";

    exit(0);
}

echo 'ECHEC : ' . count($failures) . " contrôle(s) en échec sur {$checks}\n";

foreach ($failures as $f) {
    echo "  - {$f}\n";
}

exit(1);
