<?php

declare(strict_types=1);

/*
 * ADMIN_IMMOBILIER — espaces locataires / loyers / patrimoine.
 *
 * Couvre les endpoints de liste et d'archivage ajoutés pour l'espace
 * ADMIN_IMMOBILIER, avec un compte ADMIN_IMMOBILIER de l'organisation A et
 * deux organisations concurrentes :
 *
 *  - GET  /api/v1/tenants                  (liste + search + organizationId)
 *  - PATCH /api/v1/tenants/{uuid}/archive  (suppression logique + audit)
 *  - GET  /api/v1/cities | /parcels | /buildings | /units (filtres parents)
 *  - GET  /api/v1/rents                    (status calculé overdue, leaseUuid)
 *  - GET  /api/v1/workers | /expenses | /worker-assignments (mapping query, 415)
 *
 * Les règles vérifiées :
 *  - un ADMIN_IMMOBILIER de A ne lit jamais une ressource de B ;
 *  - une organisation ou un parent hors périmètre renvoie une liste vide
 *    (200), jamais le contenu d'un autre tenant ;
 *  - `overdue` est un statut calculé à la lecture, absent des writes ;
 *  - l'archivage est tracé dans le journal d'audit et écarté des listes.
 *
 * Usage : php tests/verify-admin-immobilier.php
 */

use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationUser;
use App\Entity\Identity\User;
use App\Entity\Identity\UserCity;
use App\Entity\Expense\Expense;
use App\Entity\Property\Building;
use App\Entity\Property\City;
use App\Entity\Property\Parcel;
use App\Entity\Property\Unit;
use App\Entity\Rental\Lease;
use App\Entity\Rental\Rent;
use App\Entity\Rental\Tenant;
use App\Entity\Staff\Worker;
use App\Entity\Staff\WorkerAssignment;
use App\Entity\System\AuditLog;
use App\Enum\BuildingType;
use App\Enum\CityStatus;
use App\Enum\Currency;
use App\Enum\ExpenseCategory;
use App\Enum\LeaseStatus;
use App\Enum\OrganizationRole;
use App\Enum\OrganizationStatus;
use App\Enum\RentStatus;
use App\Enum\TenantType;
use App\Enum\UnitType;
use App\Enum\WorkerRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

/** Expose `doctrine` le temps du harnais : il est privé, donc inliné. */
final class AdminImmoKernel extends App\Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach ($container->getDefinitions() as $id => $definition) {
                    if ($id === 'doctrine' || $id === 'security.token_storage') {
                        $definition->setPublic(true);
                    }
                }
            }
        });
    }
}

$kernel = new AdminImmoKernel('dev', true);
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
 * Requête à travers le noyau.
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

$connection = $em->getConnection();
$database = (string) $connection->fetchOne('SELECT DATABASE()');

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

$rollback = static function () use ($connection): void {
    if ($connection->isTransactionActive()) {
        $connection->rollBack();
    }
};

register_shutdown_function(static function () use ($rollback): void {
    $rollback();
});

echo "  Base : {$database} (transaction annulée en fin de script)\n";

$suffix = 'admi' . bin2hex(random_bytes(3));

// ---------------------------------------------------------------------
section('Jeu de données : deux organisations, un ADMIN_IMMOBILIER de A');

$adminA = (new User())
    ->setEmail('admin.a.' . $suffix . '@test.local')
    ->setFullName('Admin Immobilier A')
    ->setPassword(password_hash('MotDePasse!123', PASSWORD_BCRYPT))
    ->setIsActive(true);
$em->persist($adminA);

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

$em->persist((new OrganizationUser())->setUser($adminA)->setOrganization($orgA)->setRole(OrganizationRole::ADMIN_IMMOBILIER));

$adminVilleA = (new User())
    ->setEmail('ville.a.' . $suffix . '@test.local')
    ->setFullName('Admin Ville A')
    ->setPassword(password_hash('MotDePasse!123', PASSWORD_BCRYPT))
    ->setIsActive(true);
$em->persist($adminVilleA);
$em->persist((new OrganizationUser())->setUser($adminVilleA)->setOrganization($orgA)->setRole(OrganizationRole::ADMIN_VILLE));

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

$cityA = $makeCity('Ville A', 'VA', $orgA);
$cityA2 = $makeCity('Ville A2', 'VA2', $orgA);
$cityB = $makeCity('Ville B', 'VB', $orgB);
$em->persist((new UserCity())->setUser($adminVilleA)->setCity($cityA));

/** @return array{parcel: Parcel, building: Building, unit: Unit} */
$makeChain = static function (string $ref, City $city) use ($em): array {
    $parcel = (new Parcel())
        ->setReference($ref . '-P')
        ->setName('Parcelle ' . $ref)
        ->setAddress('Adresse ' . $ref)
        ->setArea('100')
        ->setCity($city);
    $em->persist($parcel);

    $building = (new Building())
        ->setReference($ref . '-B')
        ->setName('Batiment ' . $ref)
        ->setType(BuildingType::MIXED)
        ->setParcel($parcel);
    $em->persist($building);

    $unit = (new Unit())
        ->setReference($ref)
        ->setBuilding($building)
        ->setType(UnitType::APARTMENT)
        ->setFloor(1)
        ->setSurface('60')
        ->setMonthlyRent('500.00')
        ->setCurrency(Currency::USD);
    $em->persist($unit);

    return ['parcel' => $parcel, 'building' => $building, 'unit' => $unit];
};

$chainA = $makeChain('U-A', $cityA);
$chainB = $makeChain('U-B', $cityB);

$makeTenant = static function (string $name, Organization $org) use ($em): Tenant {
    $tenant = (new Tenant())
        ->setFullName($name)
        ->setType(TenantType::INDIVIDUAL)
        ->setPhone('+000')
        ->setOrganization($org);
    $em->persist($tenant);

    return $tenant;
};

$tenantA = $makeTenant('Locataire Alpha ' . $suffix, $orgA);
$tenantB = $makeTenant('Locataire Beta ' . $suffix, $orgB);

/** @return array{lease: Lease, rentOver: Rent, rentFuture: Rent} */
$makeLeaseWithRents = static function (string $ref, Organization $org, Unit $unit, Tenant $tenant) use ($em): array {
    $lease = (new Lease())
        ->setOrganization($org)
        ->setTenant($tenant)
        ->setUnit($unit)
        ->setReference($ref)
        ->setStartDate(new \DateTimeImmutable('now -60 days'))
        ->setMonthlyRent('500.00')
        ->setCurrency(Currency::USD)
        ->setStatus(LeaseStatus::ACTIVE);
    $em->persist($lease);

    $rentOver = (new Rent())
        ->setLease($lease)
        ->setPeriod((new \DateTimeImmutable('now -10 days'))->setTime(0, 0))
        ->setDueDate((new \DateTimeImmutable('now -5 days'))->setTime(0, 0))
        ->setAmount('500.00')
        ->setCurrency(Currency::USD)
        ->setStatus(RentStatus::PENDING);
    $em->persist($rentOver);

    $rentFuture = (new Rent())
        ->setLease($lease)
        ->setPeriod((new \DateTimeImmutable('now +30 days'))->setTime(0, 0))
        ->setDueDate((new \DateTimeImmutable('now +35 days'))->setTime(0, 0))
        ->setAmount('500.00')
        ->setCurrency(Currency::USD)
        ->setStatus(RentStatus::PENDING);
    $em->persist($rentFuture);

    return ['lease' => $lease, 'rentOver' => $rentOver, 'rentFuture' => $rentFuture];
};

$rentalA = $makeLeaseWithRents('BAIL-A', $orgA, $chainA['unit'], $tenantA);
$rentalB = $makeLeaseWithRents('BAIL-B', $orgB, $chainB['unit'], $tenantB);

$workerA = (new Worker())
    ->setOrganization($orgA)
    ->setFullName('Ouvrier Admin A')
    ->setPhone('+000')
    ->setEmail('ouvrier.' . $suffix . '@test.local');
$em->persist($workerA);

$workerB = (new Worker())
    ->setOrganization($orgB)
    ->setFullName('Ouvrier Admin B')
    ->setPhone('+000')
    ->setEmail('ouvrier.b.' . $suffix . '@test.local');
$em->persist($workerB);

$makeExpense = static function (string $amount, Organization $org, City $city, User $author) use ($em): Expense {
    $expense = (new Expense())
        ->setOrganization($org)
        ->setCity($city)
        ->setCreatedBy($author)
        ->setCategory(ExpenseCategory::MAINTENANCE)
        ->setAmount($amount)
        ->setCurrency(Currency::USD)
        ->setExpenseDate(new \DateTimeImmutable('now -3 days'));
    $em->persist($expense);

    return $expense;
};

$expenseA = $makeExpense('120.00', $orgA, $cityA, $adminA);
$expenseB = $makeExpense('999.00', $orgB, $cityB, $adminA);

$makeAssignment = static function (Worker $worker, City $city) use ($em): WorkerAssignment {
    $assignment = (new WorkerAssignment())
        ->setWorker($worker)
        ->setCity($city)
        ->setRole(WorkerRole::GARDIEN)
        ->setMonthlySalary('150.00')
        ->setCurrency(Currency::USD)
        ->setStartDate(new \DateTimeImmutable('now -20 days'));
    $em->persist($assignment);

    return $assignment;
};

$assignmentA = $makeAssignment($workerA, $cityA);
$assignmentB = $makeAssignment($workerB, $cityB);

$em->flush();
$em->clear();

echo "  Jeu de données créé.\n";

$uuidOrgA = $orgA->getUuid()->toRfc4122();
$uuidOrgB = $orgB->getUuid()->toRfc4122();
$uuidCityA = $cityA->getUuid()->toRfc4122();
$uuidCityA2 = $cityA2->getUuid()->toRfc4122();
$uuidAdminVilleA = $adminVilleA->getUuid()->toRfc4122();
$uuidCityB = $cityB->getUuid()->toRfc4122();
$uuidParcelA = $chainA['parcel']->getUuid()->toRfc4122();
$uuidParcelB = $chainB['parcel']->getUuid()->toRfc4122();
$uuidBuildingA = $chainA['building']->getUuid()->toRfc4122();
$uuidBuildingB = $chainB['building']->getUuid()->toRfc4122();
$uuidUnitA = $chainA['unit']->getUuid()->toRfc4122();
$uuidUnitB = $chainB['unit']->getUuid()->toRfc4122();
$uuidTenantA = $tenantA->getUuid()->toRfc4122();
$uuidTenantB = $tenantB->getUuid()->toRfc4122();
$uuidLeaseA = $rentalA['lease']->getUuid()->toRfc4122();
$uuidLeaseB = $rentalB['lease']->getUuid()->toRfc4122();
$uuidRentOverA = $rentalA['rentOver']->getUuid()->toRfc4122();
$uuidRentFutureA = $rentalA['rentFuture']->getUuid()->toRfc4122();
$uuidRentB = $rentalB['rentOver']->getUuid()->toRfc4122();
$uuidWorkerA = $workerA->getUuid()->toRfc4122();
$uuidExpenseA = $expenseA->getUuid()->toRfc4122();
$uuidExpenseB = $expenseB->getUuid()->toRfc4122();
$uuidAssignmentA = $assignmentA->getUuid()->toRfc4122();
$uuidAssignmentB = $assignmentB->getUuid()->toRfc4122();

[$status, $payload] = $request('POST', '/api/auth/login', [
    'email' => 'admin.a.' . $suffix . '@test.local',
    'password' => 'MotDePasse!123',
]);

$token = is_array($payload) ? ($payload['accessToken'] ?? null) : null;
check('Connexion de l\'ADMIN_IMMOBILIER de A', $status === 200 && is_string($token), "obtenu {$status}");

if (!is_string($token)) {
    fwrite(STDERR, "[erreur] jeton absent, poursuite impossible\n");
    $rollback();
    exit(1);
}

[$status, $payload] = $request('GET', '/api/v1/identity/user-cities/user/' . $uuidAdminVilleA . '?page=1&limit=100', null, $token);
check(
    'ADMIN_IMMOBILIER : consulter les villes attribuées à un ADMIN_VILLE de son organisation',
    $status === 200 && count($payload['data']['items'] ?? []) === 1,
    "obtenu {$status} : " . substr(json_encode($payload), 0, 300)
);

[$status, $payload] = $request('POST', '/api/v1/identity/user-cities', [
    'userUuid' => $uuidAdminVilleA,
    'cityUuid' => $uuidCityA2,
], $token);
$uuidCityLink = is_array($payload) ? ($payload['data']['id'] ?? null) : null;
check(
    'ADMIN_IMMOBILIER : ajouter une seconde ville à un ADMIN_VILLE de son organisation',
    in_array($status, [200, 201], true) && is_string($uuidCityLink),
    "obtenu {$status} : " . substr(json_encode($payload), 0, 300)
);

[$status, $payload] = $request('GET', '/api/v1/identity/user-cities/user/' . $uuidAdminVilleA . '?page=1&limit=100', null, $token);
check(
    'ADMIN_IMMOBILIER : le périmètre affiche maintenant deux villes',
    $status === 200 && count($payload['data']['items'] ?? []) === 2,
    "obtenu {$status} : " . substr(json_encode($payload), 0, 300)
);

if (is_string($uuidCityLink)) {
    [$status] = $request('DELETE', '/api/v1/identity/user-cities/' . $uuidCityLink, null, $token);
    check('ADMIN_IMMOBILIER : retirer une seule ville du périmètre', $status === 200, "obtenu {$status}");
}

$adminImmobilierToken = $token;

section('Patrimoine : l’ADMIN_IMMOBILIER peut modifier les fiches de son organisation');

foreach ([
    ['Parcelle', '/api/v1/parcels/' . $uuidParcelA, ['reference' => 'EDIT-' . strtoupper(substr($suffix, 0, 8)) . '-P']],
    ['Bâtiment', '/api/v1/property/buildings/' . $uuidBuildingA, ['reference' => 'EDIT-' . strtoupper(substr($suffix, 0, 8)) . '-B']],
    ['Unité', '/api/v1/units/' . $uuidUnitA, ['reference' => 'EDIT-' . strtoupper(substr($suffix, 0, 8)) . '-U']],
] as [$resource, $path, $payload]) {
    [$status] = $request('PUT', $path, $payload, $adminImmobilierToken);
    check("ADMIN_IMMOBILIER modifie une fiche {$resource} de son organisation", $status === 200, 'obtenu ' . $status);
}

foreach ([
    ['Parcelle', '/api/v1/parcels/' . $uuidParcelB, ['reference' => 'DENY-' . strtoupper(substr($suffix, 0, 8)) . '-P']],
    ['Bâtiment', '/api/v1/property/buildings/' . $uuidBuildingB, ['reference' => 'DENY-' . strtoupper(substr($suffix, 0, 8)) . '-B']],
    ['Unité', '/api/v1/units/' . $uuidUnitB, ['reference' => 'DENY-' . strtoupper(substr($suffix, 0, 8)) . '-U']],
] as [$resource, $path, $payload]) {
    [$status] = $request('PUT', $path, $payload, $adminImmobilierToken);
    check("ADMIN_IMMOBILIER ne modifie pas une fiche {$resource} d’une autre organisation", $status === 403, 'obtenu ' . $status);
}

[$localLoginStatus, $localLoginPayload] = $request('POST', '/api/auth/login', [
    'email' => 'ville.a.' . $suffix . '@test.local',
    'password' => 'MotDePasse!123',
]);
$localToken = is_array($localLoginPayload) ? ($localLoginPayload['accessToken'] ?? null) : null;
check('Connexion de l’ADMIN_VILLE de A pour les opérations locales', $localLoginStatus === 200 && is_string($localToken), 'obtenu ' . $localLoginStatus);
if (!is_string($localToken)) {
    $rollback();
    exit(1);
}
$token = $localToken;

$photoContents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j3ioAAAAASUVORK5CYII=', true);
$uploadedPhotoPaths = [];
$photoTempPaths = [];
register_shutdown_function(static function () use (&$uploadedPhotoPaths, &$photoTempPaths): void {
    foreach ($uploadedPhotoPaths as $path) @unlink(dirname(__DIR__) . '/public/uploads/' . $path);
    foreach ($photoTempPaths as $path) @unlink($path);
});
$uploadSinglePhoto = static function (string $uri, string $field) use ($kernel, $token, $photoContents, &$photoTempPaths): array {
    $tempPath = tempnam(sys_get_temp_dir(), 'admin-immo-photo-');
    file_put_contents($tempPath, $photoContents);
    $photoTempPaths[] = $tempPath;
    $file = new Symfony\Component\HttpFoundation\File\UploadedFile($tempPath, 'photo.png', 'image/png', UPLOAD_ERR_OK, true);
    $server = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $uri, 'HTTP_HOST' => 'localhost', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token];
    $req = Request::create($uri, 'POST', [], [], [$field => $file], $server);
    $response = $kernel->handle($req);
    $result = [$response->getStatusCode(), json_decode((string) $response->getContent(), true)];
    $kernel->terminate($req, $response);

    return $result;
};
$trackUploadedPhotoPaths = static function (array $payload) use (&$uploadedPhotoPaths): void {
    foreach (($payload['data']['uploaded'] ?? []) as $file) {
        if (is_string($file['path'] ?? null)) $uploadedPhotoPaths[] = $file['path'];
    }
    foreach (($payload['data'] ?? []) as $photo) {
        if (!is_array($photo) || !is_string($photo['url'] ?? null)) continue;
        $path = parse_url($photo['url'], PHP_URL_PATH);
        if (is_string($path) && str_starts_with($path, '/uploads/')) $uploadedPhotoPaths[] = substr($path, strlen('/uploads/'));
    }
};

section('Photos : upload unique et rattachement à l’organisation');
[$status] = $request('DELETE', '/api/v1/media/organizations/' . $uuidOrgA . '/logo', null, $adminImmobilierToken);
check('ADMIN_IMMOBILIER peut gérer le logo de son organisation sans droit général de modification', $status === 404, 'obtenu ' . $status . ' (404 attendu car aucun logo n’est encore enregistré)');
[$status, $parcelPhotoPayload] = $uploadSinglePhoto('/api/v1/media/parcels/' . $uuidParcelA . '/photos', 'files');
$trackUploadedPhotoPaths($parcelPhotoPayload ?? []);
check('ADMIN_IMMOBILIER ajoute une photo à une parcelle', $status === 200 && count($parcelPhotoPayload['data']['uploaded'] ?? []) === 1, 'obtenu ' . $status);
[$status, $buildingPhotoPayload] = $uploadSinglePhoto('/api/v1/media/buildings/' . $uuidBuildingA . '/photos', 'files');
$trackUploadedPhotoPaths($buildingPhotoPayload ?? []);
check('ADMIN_IMMOBILIER ajoute une photo à un bâtiment', $status === 200 && count($buildingPhotoPayload['data']['uploaded'] ?? []) === 1, 'obtenu ' . $status);
[$status, $unitPhotoPayload] = $uploadSinglePhoto('/api/v1/units/' . $uuidUnitA . '/photos', 'file');
$trackUploadedPhotoPaths($unitPhotoPayload ?? []);
check('ADMIN_IMMOBILIER ajoute une photo à une unité', $status === 201 && count($unitPhotoPayload['data'] ?? []) === 1, 'obtenu ' . $status);
[$status, $unitPhotosPayload] = $request('GET', '/api/v1/units/' . $uuidUnitA . '/photos', null, $token);
check('ADMIN_IMMOBILIER liste les photos de son unité', $status === 200 && is_string($unitPhotosPayload['data'][0]['uuid'] ?? null), 'obtenu ' . $status);
if ($status === 200 && is_string($unitPhotosPayload['data'][0]['uuid'] ?? null)) {
    $unitPhotoUuid = $unitPhotosPayload['data'][0]['uuid'];
    $unitPhotoUrl = (string) ($unitPhotosPayload['data'][0]['url'] ?? '');
    $unitPhotoPath = parse_url($unitPhotoUrl, PHP_URL_PATH);
    $unitPhotoFile = is_string($unitPhotoPath) ? dirname(__DIR__) . '/public' . $unitPhotoPath : '';
    [$removePhotoStatus] = $request('DELETE', '/api/v1/units/' . $uuidUnitA . '/photos/' . $unitPhotoUuid, null, $token);
    [$visiblePhotosStatus, $visiblePhotos] = $request('GET', '/api/v1/units/' . $uuidUnitA . '/photos', null, $token);
    $unitPhotoRow = $connection->fetchAssociative(
        'SELECT id, deleted_at FROM unit_photo WHERE uuid = ?',
        [\Symfony\Component\Uid\Uuid::fromString($unitPhotoUuid)->toBinary()]
    );
    check('retirer une photo la masque sans supprimer son fichier', $removePhotoStatus === 200 && $visiblePhotosStatus === 200 && ($visiblePhotos['data'] ?? []) === [] && is_array($unitPhotoRow) && $unitPhotoRow['deleted_at'] !== null && is_file($unitPhotoFile));
    [$restorePhotoStatus, $restoredPhotos] = $request('POST', '/api/v1/units/' . $uuidUnitA . '/photos/' . $unitPhotoUuid . '/restore', null, $token);
    $restoredPhotoRow = $connection->fetchAssociative(
        'SELECT id, deleted_at FROM unit_photo WHERE uuid = ?',
        [\Symfony\Component\Uid\Uuid::fromString($unitPhotoUuid)->toBinary()]
    );
    $photoAuditCount = is_array($restoredPhotoRow)
        ? (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM audit_log WHERE entity_type = ? AND entity_id = ? AND action IN (?, ?)',
            [App\Entity\Property\UnitPhoto::class, $restoredPhotoRow['id'], 'LISTING_PHOTO_REMOVED', 'RESTORE_UNIT_PHOTO']
        )
        : 0;
    check('restaurer une photo la réaffiche et conserve sa ligne', $restorePhotoStatus === 200 && count($restoredPhotos['data'] ?? []) === 1 && is_array($restoredPhotoRow) && $restoredPhotoRow['deleted_at'] === null && is_file($unitPhotoFile));
    check('les événements de retrait et restauration restent dans l’audit', $photoAuditCount === 2, 'événements trouvés : ' . $photoAuditCount);
}
[$status] = $request('GET', '/api/v1/units/' . $uuidUnitB . '/photos', null, $token);
check('ADMIN_IMMOBILIER ne lit pas une galerie hors organisation', in_array($status, [403, 404], true), 'obtenu ' . $status);

/** @return list<string> */
$payloadIds = static function (array $payload): array {
    $items = $payload['data']['items'] ?? [];

    return is_array($items) ? array_values(array_map(static fn ($x) => $x['id'] ?? null, $items)) : [];
};

// ---------------------------------------------------------------------
section('Locataires : liste, recherche, périmètre, archivage');

[$adminImmoArchiveStatus] = $request('PATCH', '/api/v1/tenants/' . $uuidTenantA . '/archive', null, $adminImmobilierToken);
check('ADMIN_IMMOBILIER ne peut pas archiver directement un locataire d’une ville', $adminImmoArchiveStatus === 403, 'obtenu ' . $adminImmoArchiveStatus);

[$status, $payload, $rawTenants] = $request('GET', '/api/v1/tenants', null, $adminImmobilierToken);
check(
    'GET /api/v1/tenants : 200',
    $status === 200,
    "obtenu {$status}"
);
check(
    'GET /api/v1/tenants : seul le locataire de A est listé',
    $payloadIds($payload) === [$uuidTenantA],
    'liste=' . implode(',', $payloadIds($payload)) . ' | raw=' . substr($rawTenants, 0, 500)
);

[$status, $payload] = $request('GET', '/api/v1/tenants?organizationId=' . $uuidOrgB, null, $adminImmobilierToken);
check(
    'GET /api/v1/tenants?organizationId=<B> : liste vide pour A (pas d\'énumération)',
    $status === 200 && $payloadIds($payload) === [],
    "obtenu {$status}, items=" . implode(',', $payloadIds($payload))
);

[$status, $payload] = $request('GET', '/api/v1/tenants?search=' . $suffix, null, $adminImmobilierToken);
check(
    'GET /api/v1/tenants?search=<suffix> : ne remonte que le locataire de A',
    $payloadIds($payload) === [$uuidTenantA],
    'liste=' . implode(',', $payloadIds($payload))
);

[$status, $payload] = $request('PATCH', '/api/v1/tenants/' . $uuidTenantA . '/archive', null, $token);
check(
    'PATCH /api/v1/tenants/{A}/archive : 200',
    $status === 200,
    "obtenu {$status}"
);

[$status] = $request('PATCH', '/api/v1/tenants/' . $uuidTenantB . '/archive', null, $token);
check(
    'PATCH /api/v1/tenants/{B}/archive : refusé pour A (403/404)',
    in_array($status, [403, 404], true),
    "obtenu {$status}"
);

$auditCount = (int) $em->getRepository(AuditLog::class)->createQueryBuilder('a')
    ->select('COUNT(a.id)')
    ->andWhere('a.action = :action')
    ->andWhere('a.entityType = :type')
    ->setParameter('action', 'ARCHIVE_TENANT')
    ->setParameter('type', Tenant::class)
    ->getQuery()
    ->getSingleScalarResult();
check('Audit : une entrée ARCHIVE_TENANT a été tracée', $auditCount === 1, "count={$auditCount}");

[$status, $payload] = $request('GET', '/api/v1/tenants', null, $adminImmobilierToken);
check(
    'GET /api/v1/tenants : le locataire archivé disparaît de la liste',
    $status === 200 && $payloadIds($payload) === [],
    'liste=' . implode(',', $payloadIds($payload))
);

// ---------------------------------------------------------------------
section('Patrimoine : villes, parcelles, bâtiments, unités (filtres parents)');

[$status, $payload, $rawCities] = $request('GET', '/api/v1/property/cities', null, $token);
check(
    'GET /api/v1/property/cities : la ville de B ne fuite pas',
    in_array($uuidCityA, $payloadIds($payload), true) && !in_array($uuidCityB, $payloadIds($payload), true),
    'liste=' . implode(',', $payloadIds($payload)) . ' | raw=' . substr($rawCities, 0, 300)
);

[$status, $payload] = $request('GET', '/api/v1/parcels?cityUuid=' . $uuidCityA, null, $token);
check(
    'GET /api/v1/parcels?cityUuid=<A> : parcelle de A trouvée',
    in_array($uuidParcelA, $payloadIds($payload), true),
    'liste=' . implode(',', $payloadIds($payload))
);

[$status, $payload] = $request('GET', '/api/v1/parcels?cityUuid=' . $uuidCityB, null, $token);
check(
    'GET /api/v1/parcels?cityUuid=<B> : liste vide (hors périmètre)',
    $status === 200 && $payloadIds($payload) === [],
    "obtenu {$status}, items=" . implode(',', $payloadIds($payload))
);

[$status, $payload, $rawBuild] = $request('GET', '/api/v1/property/buildings?parcelUuid=' . $uuidParcelA, null, $token);
check(
    'GET /api/v1/property/buildings?parcelUuid=<A> : bâtiment de A trouvé',
    in_array($uuidBuildingA, $payloadIds($payload), true),
    'liste=' . implode(',', $payloadIds($payload)) . ' | raw=' . substr($rawBuild, 0, 300)
);

[$status, $payload, $rawBuildB] = $request('GET', '/api/v1/property/buildings?parcelUuid=' . $uuidParcelB, null, $token);
check(
    'GET /api/v1/property/buildings?parcelUuid=<B> : liste vide (hors périmètre)',
    $status === 200 && $payloadIds($payload) === [],
    "obtenu {$status}, items=" . implode(',', $payloadIds($payload)) . ' | raw=' . substr($rawBuildB, 0, 300)
);

[$status, $payload] = $request('GET', '/api/v1/units?buildingUuid=' . $uuidBuildingA, null, $token);
check(
    'GET /api/v1/units?buildingUuid=<A> : unité de A trouvée',
    in_array($uuidUnitA, $payloadIds($payload), true),
    'liste=' . implode(',', $payloadIds($payload))
);

[$status, $payload] = $request('GET', '/api/v1/units?buildingUuid=' . $uuidBuildingB, null, $token);
check(
    'GET /api/v1/units?buildingUuid=<B> : liste vide (hors périmètre)',
    $status === 200 && $payloadIds($payload) === [],
    "obtenu {$status}, items=" . implode(',', $payloadIds($payload))
);

// ---------------------------------------------------------------------
section('Loyers : liste, statut calculé overdue, filtres bail/org');

[$status, $payload] = $request('GET', '/api/v1/rents', null, $token);
$listed = $payloadIds($payload);
check(
    'GET /api/v1/rents : les échéances de A sont listées, celle de B non',
    in_array($uuidRentOverA, $listed, true)
        && in_array($uuidRentFutureA, $listed, true)
        && !in_array($uuidRentB, $listed, true),
    'liste=' . implode(',', $listed)
);

[$status, $payload] = $request('GET', '/api/v1/rents?status=overdue', null, $token);
check(
    'GET /api/v1/rents?status=overdue : seule l\'échéance passée et impayée',
    $payloadIds($payload) === [$uuidRentOverA],
    'liste=' . implode(',', $payloadIds($payload))
);

[$status, $payload] = $request('GET', '/api/v1/rents?status=pending', null, $token);
check(
    'GET /api/v1/rents?status=pending : l\'échéance future n\'est pas OVERDUE',
    $payloadIds($payload) === [$uuidRentFutureA],
    'liste=' . implode(',', $payloadIds($payload))
);

[$status, $payload] = $request('GET', '/api/v1/rents?leaseUuid=' . $uuidLeaseA, null, $token);
check(
    'GET /api/v1/rents?leaseUuid=<A> : les deux échéances du bail de A',
    count($payloadIds($payload)) === 2
        && in_array($uuidRentOverA, $payloadIds($payload), true)
        && in_array($uuidRentFutureA, $payloadIds($payload), true),
    'liste=' . implode(',', $payloadIds($payload))
);

[$status, $payload] = $request('GET', '/api/v1/rents?leaseUuid=' . $uuidLeaseB, null, $token);
check(
    'GET /api/v1/rents?leaseUuid=<B> : refusé ou vide, jamais l\'échéance de B',
    ($status === 403 || $status === 404 || ($status === 200 && $payloadIds($payload) === []))
        && !in_array($uuidRentB, $payloadIds($payload), true),
    "obtenu {$status}, items=" . implode(',', $payloadIds($payload))
);

[$status, $payload] = $request('GET', '/api/v1/rents?organizationId=' . $uuidOrgB, null, $token);
check(
    'GET /api/v1/rents?organizationId=<B> : liste vide',
    $status === 200 && $payloadIds($payload) === [],
    "obtenu {$status}, items=" . implode(',', $payloadIds($payload))
);

// ---------------------------------------------------------------------
section('Expenses / Workers / Worker-assignments : le GET ne renvoie plus 415');

[$status, $payload, $rawWorkers] = $request('GET', '/api/v1/workers', null, $token);
check(
    'GET /api/v1/workers : 200 (mapping query string)',
    $status === 200,
    "obtenu {$status} : " . substr($payload['message'] ?? '', 0, 100)
);
check(
    'GET /api/v1/workers : le travailleur de A est listé',
    in_array($uuidWorkerA, $payloadIds($payload), true),
    'liste=' . implode(',', $payloadIds($payload)) . ' | raw=' . substr($rawWorkers, 0, 300)
);

[$status, $payload, $rawCreatedWorker] = $request('POST', '/api/v1/workers', [
    'organizationUuid' => $uuidOrgA,
    'fullName' => 'Ouvrier affecté ' . $suffix,
    'phone' => '+243990000099',
], $adminImmobilierToken);
$uuidCreatedWorker = is_array($payload) ? ($payload['data']['id'] ?? null) : null;
check(
    'POST /api/v1/workers : l’ADMIN_IMMOBILIER crée un collaborateur organisationnel',
    $status === 200 && is_string($uuidCreatedWorker),
    "obtenu {$status} : " . substr($rawCreatedWorker, 0, 250)
);

[$status, $payload, $rawCreatedAssignment] = $request('POST', '/api/v1/worker-assignments', [
    'workerUuid' => $uuidCreatedWorker,
    'cityUuid' => $uuidCityA,
    'parcelUuid' => $uuidParcelA,
    'role' => 'gardien',
    'monthlySalary' => '150.00',
    'currency' => 'USD',
    'startDate' => (new DateTimeImmutable('today'))->format('Y-m-d'),
], $token);
check(
    'POST /api/v1/worker-assignments : rattachement au même tenant accepté',
    $status === 200,
    "obtenu {$status} : " . substr($rawCreatedAssignment, 0, 250)
);

[$status, $payload, $rawExpenses] = $request('GET', '/api/v1/expenses', null, $token);
check(
    'GET /api/v1/expenses : 200 (mapping query string)',
    $status === 200,
    "obtenu {$status} : " . substr($rawExpenses, 0, 150)
);
check(
    'GET /api/v1/expenses : la dépense de A est listée',
    in_array($uuidExpenseA, $payloadIds($payload), true),
    'liste=' . implode(',', $payloadIds($payload)) . ' | raw=' . substr($rawExpenses, 0, 300)
);
check(
    'GET /api/v1/expenses : la dépense de B n\'est PAS listée (isolation)',
    !in_array($uuidExpenseB, $payloadIds($payload), true),
    'fuite inter-tenant : ' . substr($rawExpenses, 0, 300)
);

[$status, $payload, $rawExpenses] = $request('GET', '/api/v1/expenses?cityIds[]=' . $uuidCityB, null, $token);
check(
    'GET /api/v1/expenses?cityIds=B : 200 mais liste vide (filtre qui ne peut que restreindre)',
    $status === 200 && $payloadIds($payload) === [],
    "obtenu {$status}, items=" . implode(',', $payloadIds($payload)) . ' | raw=' . substr($rawExpenses, 0, 300)
);

[$status, $payload, $rawAssignments] = $request('GET', '/api/v1/worker-assignments', null, $token);
check(
    'GET /api/v1/worker-assignments : 200 (mapping query string)',
    $status === 200,
    "obtenu {$status} : " . substr($rawAssignments, 0, 150)
);
check(
    'GET /api/v1/worker-assignments : l\'affectation de A est listée',
    in_array($uuidAssignmentA, $payloadIds($payload), true),
    'liste=' . implode(',', $payloadIds($payload)) . ' | raw=' . substr($rawAssignments, 0, 300)
);
check(
    'GET /api/v1/worker-assignments : l\'affectation de B n\'est PAS listée (isolation)',
    !in_array($uuidAssignmentB, $payloadIds($payload), true),
    'fuite inter-tenant : ' . substr($rawAssignments, 0, 300)
);

// ---------------------------------------------------------------------
section('Patrimoine : cycle de vie des villes et droits CRUD organisationnels');

[$status, $payload] = $request('POST', '/api/v1/property/cities', [
    'organizationUuid' => $uuidOrgA,
    'name' => 'Ville créée par Admin Immobilier',
    'code' => 'NEW' . strtoupper(substr($suffix, 0, 6)),
    'country' => 'CD',
], $adminImmobilierToken);
check('ADMIN_IMMOBILIER crée une ville dans son organisation', $status === 201, 'obtenu ' . $status . ' : ' . substr(json_encode($payload), 0, 300));
$createdCityUuid = is_array($payload)
    ? ($payload['data']['uuid'] ?? $payload['data']['id'] ?? null)
    : null;

[$status] = $request('POST', '/api/v1/property/cities', [
    'organizationUuid' => $uuidOrgB,
    'name' => 'Ville hors organisation',
    'code' => 'OUT' . strtoupper(substr($suffix, 0, 6)),
    'country' => 'CD',
], $adminImmobilierToken);
check('ADMIN_IMMOBILIER ne crée pas de ville dans une autre organisation', $status === 403, 'obtenu ' . $status);

[$status, $payload] = $request('PUT', '/api/v1/property/cities/' . $uuidCityA, [
    'name' => 'Ville modifiée par Admin Immobilier',
    'code' => 'EDIT' . strtoupper(substr($suffix, 0, 4)),
], $adminImmobilierToken);
check('ADMIN_IMMOBILIER modifie une ville de son organisation', $status === 200, 'obtenu ' . $status . ' : ' . substr(json_encode($payload), 0, 300));

[$status] = $request('PUT', '/api/v1/property/cities/' . $uuidCityB, [
    'name' => 'Tentative de modification inter-organisation',
    'code' => 'OUT' . strtoupper(substr($suffix, 0, 4)),
], $adminImmobilierToken);
check('ADMIN_IMMOBILIER ne modifie pas une ville d’une autre organisation', $status === 403, 'obtenu ' . $status);

[$status, $payload] = $request('PUT', '/api/v1/property/cities/' . $uuidCityA, [
    'status' => 'inactive',
], $adminImmobilierToken);
check('ADMIN_IMMOBILIER désactive une ville de son organisation', $status === 200 && ($payload['data']['status'] ?? null) === 'inactive', 'obtenu ' . $status);
[$status, $payload] = $request('PUT', '/api/v1/property/cities/' . $uuidCityA, [
    'status' => 'active',
], $adminImmobilierToken);
check('ADMIN_IMMOBILIER réactive une ville de son organisation', $status === 200 && ($payload['data']['status'] ?? null) === 'active', 'obtenu ' . $status);

if ($createdCityUuid !== null) {
    [$status, $payload] = $request('POST', '/api/v1/parcels', [
        'cityUuid' => $createdCityUuid,
        'reference' => 'CRUD-' . strtoupper(substr($suffix, 0, 8)) . '-P',
        'name' => 'Parcelle test ADMIN_IMMOBILIER',
        'address' => 'Avenue de test',
        'area' => '500.00',
    ], $adminImmobilierToken);
    check('ADMIN_IMMOBILIER crée une parcelle dans une ville de son organisation', $status === 201, 'obtenu ' . $status);
    $createdParcelUuid = is_array($payload)
        ? ($payload['data']['uuid'] ?? $payload['data']['id'] ?? null)
        : null;

    $createdBuildingUuid = null;
    if ($createdParcelUuid !== null) {
        [$status, $payload] = $request('POST', '/api/v1/property/buildings', [
            'parcelUuid' => $createdParcelUuid,
            'reference' => 'CRUD-' . strtoupper(substr($suffix, 0, 8)) . '-B',
            'name' => 'Bâtiment test ADMIN_IMMOBILIER',
            'type' => 'apartment',
            'numberOfFloors' => 2,
        ], $adminImmobilierToken);
        check('ADMIN_IMMOBILIER crée un bâtiment sur une parcelle de son organisation', $status === 201, 'obtenu ' . $status);
        $createdBuildingUuid = is_array($payload)
            ? ($payload['data']['uuid'] ?? $payload['data']['id'] ?? null)
            : null;
    } else {
        check('ADMIN_IMMOBILIER crée un bâtiment sur une parcelle de son organisation', false, 'UUID de parcelle absent');
    }

    $createdUnitUuid = null;
    if ($createdBuildingUuid !== null) {
        [$status] = $request('POST', '/api/v1/units', [
            'buildingUuid' => $createdBuildingUuid,
            'reference' => 'CRUD-' . strtoupper(substr($suffix, 0, 8)) . '-TOO-HIGH',
            'type' => 'apartment',
            'operationalStatus' => 'available',
            'floor' => 3,
            'surface' => '50.00',
            'monthlyRent' => '100.00',
            'currency' => 'USD',
        ], $adminImmobilierToken);
        check('ADMIN_IMMOBILIER ne crée pas une unité au-dessus des étages déclarés', $status === 422, 'obtenu ' . $status);

        [$status] = $request('POST', '/api/v1/property/buildings/' . $createdBuildingUuid . '/levels', [
            'reference' => 'LEVEL-TOO-HIGH-' . strtoupper(substr($suffix, 0, 6)),
            'label' => 'Troisième étage non autorisé',
            'displayOrder' => 3,
        ], $adminImmobilierToken);
        check('ADMIN_IMMOBILIER ne déclare pas un niveau au-dessus des étages du bâtiment', $status === 422, 'obtenu ' . $status);

        [$status, $levelPayload] = $request('POST', '/api/v1/property/buildings/' . $createdBuildingUuid . '/levels', [
            'reference' => 'LEVEL-' . strtoupper(substr($suffix, 0, 8)),
            'label' => 'Deuxième étage',
            'displayOrder' => 2,
        ], $adminImmobilierToken);
        $createdLevelUuid = is_array($levelPayload) ? ($levelPayload['data']['id'] ?? null) : null;
        check('ADMIN_IMMOBILIER peut déclarer le dernier étage autorisé', $status === 201 && $createdLevelUuid !== null, 'obtenu ' . $status);

        [$status, $payload] = $request('POST', '/api/v1/units', [
            'buildingUuid' => $createdBuildingUuid,
            'reference' => 'CRUD-' . strtoupper(substr($suffix, 0, 8)) . '-U',
            'type' => 'apartment',
            'operationalStatus' => 'available',
            'floor' => 2,
            'levelUuid' => $createdLevelUuid,
            'surface' => '50.00',
            'monthlyRent' => '100.00',
            'currency' => 'USD',
        ], $adminImmobilierToken);
        check('ADMIN_IMMOBILIER crée une unité au niveau déclaré dans son bâtiment', $status === 201, 'obtenu ' . $status);
        $createdUnitUuid = is_array($payload)
            ? ($payload['data']['uuid'] ?? $payload['data']['id'] ?? null)
            : null;

        [$status] = $request('PUT', '/api/v1/property/building-levels/' . $createdLevelUuid, [
            'reference' => 'LEVEL-' . strtoupper(substr($suffix, 0, 8)),
            'label' => 'Premier étage',
            'displayOrder' => 1,
        ], $adminImmobilierToken);
        check('Modifier un niveau partagé met à jour son étage sur les unités liées', $status === 200, 'obtenu ' . $status);
        if ($createdUnitUuid !== null) {
            [$status, $unitPayload] = $request('GET', '/api/v1/units/' . $createdUnitUuid, null, $adminImmobilierToken);
            check('L’unité liée reprend le numéro du niveau partagé modifié', $status === 200 && ($unitPayload['data']['floor'] ?? null) === 1, 'obtenu ' . $status);
        }

        [$status] = $request('PUT', '/api/v1/property/buildings/' . $createdBuildingUuid, ['numberOfFloors' => 0], $adminImmobilierToken);
        check('ADMIN_IMMOBILIER ne réduit pas les étages sous un niveau encore utilisé', $status === 409, 'obtenu ' . $status);
    } else {
        check('ADMIN_IMMOBILIER crée une unité dans un bâtiment de son organisation', false, 'UUID de bâtiment absent');
    }

    if ($createdUnitUuid !== null) {
        [$status] = $request('DELETE', '/api/v1/property/buildings/' . $createdBuildingUuid, null, $adminImmobilierToken);
        check('ADMIN_IMMOBILIER ne supprime pas un bâtiment qui contient encore une unité', $status === 409, 'obtenu ' . $status);
        [$status] = $request('DELETE', '/api/v1/parcels/' . $createdParcelUuid, null, $adminImmobilierToken);
        check('ADMIN_IMMOBILIER ne supprime pas une parcelle qui contient encore un bâtiment', $status === 409, 'obtenu ' . $status);
        [$status] = $request('DELETE', '/api/v1/units/' . $createdUnitUuid, null, $adminImmobilierToken);
        check('ADMIN_IMMOBILIER supprime logiquement une unité de son organisation', $status === 200, 'obtenu ' . $status);
    } else {
        check('ADMIN_IMMOBILIER supprime logiquement une unité de son organisation', false, 'UUID d’unité absent');
    }
    if ($createdBuildingUuid !== null) {
        [$status] = $request('DELETE', '/api/v1/property/buildings/' . $createdBuildingUuid, null, $adminImmobilierToken);
        check('ADMIN_IMMOBILIER supprime logiquement un bâtiment de son organisation', $status === 200, 'obtenu ' . $status);
    } else {
        check('ADMIN_IMMOBILIER supprime logiquement un bâtiment de son organisation', false, 'UUID de bâtiment absent');
    }
    if ($createdParcelUuid !== null) {
        [$status] = $request('DELETE', '/api/v1/parcels/' . $createdParcelUuid, null, $adminImmobilierToken);
        check('ADMIN_IMMOBILIER supprime logiquement une parcelle de son organisation', $status === 200, 'obtenu ' . $status);
    } else {
        check('ADMIN_IMMOBILIER supprime logiquement une parcelle de son organisation', false, 'UUID de parcelle absent');
    }

    [$status] = $request('DELETE', '/api/v1/property/cities/' . $createdCityUuid, null, $adminImmobilierToken);
    check('ADMIN_IMMOBILIER supprime logiquement une ville de son organisation', $status === 200, 'obtenu ' . $status);
} else {
    check('ADMIN_IMMOBILIER crée une parcelle dans une ville de son organisation', false, 'UUID de ville absent');
    check('ADMIN_IMMOBILIER crée un bâtiment sur une parcelle de son organisation', false, 'UUID de ville absent');
    check('ADMIN_IMMOBILIER crée une unité dans un bâtiment de son organisation', false, 'UUID de ville absent');
    check('ADMIN_IMMOBILIER supprime logiquement une unité de son organisation', false, 'UUID de ville absent');
    check('ADMIN_IMMOBILIER supprime logiquement un bâtiment de son organisation', false, 'UUID de ville absent');
    check('ADMIN_IMMOBILIER supprime logiquement une parcelle de son organisation', false, 'UUID de ville absent');
    check('ADMIN_IMMOBILIER supprime logiquement une ville de son organisation', false, 'UUID absent de la réponse de création');
}

// Les dates métier rétroactives restent distinctes de la saisie serveur.
section('Dates opérationnelles : paiement, bail et intervention');
$paymentDate = (new \DateTimeImmutable('2 days ago', new \DateTimeZone('UTC')))->format('Y-m-d');
[$status, $payload] = $request('POST', '/api/v1/payments', [
    'rentUuid' => $uuidRentFutureA,
    'amount' => '25.00',
    'currency' => 'USD',
    'paymentDate' => $paymentDate,
    'method' => 'cash',
], $token);
$payment = $payload['data'] ?? [];
check(
    'un paiement rétroactif conserve date métier, auteur et date de saisie',
    $status === 201
        && str_starts_with((string) ($payment['paymentDate'] ?? ''), $paymentDate)
        && ($payment['createdByName'] ?? null) === 'Admin Ville A'
        && isset($payment['createdAt']),
    "HTTP {$status}, paymentDate=" . ($payment['paymentDate'] ?? 'absente') . ', createdByName=' . ($payment['createdByName'] ?? 'absent') . ', createdAt=' . ($payment['createdAt'] ?? 'absente')
);
$todayPaymentDate = (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))->format('Y-m-d');
[$sameDayPaymentStatus, $sameDayPaymentPayload] = $request('POST', '/api/v1/payments', [
    'rentUuid' => $uuidRentFutureA,
    'amount' => '10.00',
    'currency' => 'USD',
    'paymentDate' => $todayPaymentDate,
    'method' => 'cash',
], $token);
check(
    'un paiement effectué et saisi le même jour conserve sa date métier',
    $sameDayPaymentStatus === 201 && str_starts_with((string) ($sameDayPaymentPayload['data']['paymentDate'] ?? ''), $todayPaymentDate),
    "HTTP {$sameDayPaymentStatus}"
);
[$futurePaymentStatus] = $request('POST', '/api/v1/payments', [
    'rentUuid' => $uuidRentFutureA,
    'amount' => '10.00',
    'currency' => 'USD',
    'paymentDate' => (new \DateTimeImmutable('tomorrow', new \DateTimeZone('UTC')))->format('Y-m-d'),
    'method' => 'cash',
], $token);
check('un paiement daté dans le futur est refusé', $futurePaymentStatus === 422, "HTTP {$futurePaymentStatus}");
[$invalidPaymentDateStatus] = $request('POST', '/api/v1/payments', [
    'rentUuid' => $uuidRentFutureA,
    'amount' => '10.00',
    'currency' => 'USD',
    'paymentDate' => '2026-02-30',
    'method' => 'cash',
], $token);
check('une date civile impossible pour un paiement est refusée', in_array($invalidPaymentDateStatus, [400, 422], true), "HTTP {$invalidPaymentDateStatus}");
[$crossTenantPaymentStatus] = $request('POST', '/api/v1/payments', [
    'rentUuid' => $uuidRentB,
    'amount' => '25.00',
    'currency' => 'USD',
    'paymentDate' => $paymentDate,
    'method' => 'cash',
], $token);
check('un paiement rétroactif hors organisation reste interdit', in_array($crossTenantPaymentStatus, [403, 404], true), "obtenu {$crossTenantPaymentStatus}");

$effectiveTermination = (new \DateTimeImmutable('2 days ago', new \DateTimeZone('UTC')))->format('Y-m-d');
[$terminationStatus, $terminationPayload] = $request('PATCH', '/api/v1/leases/' . $uuidLeaseA . '/terminate', [
    'reason' => 'Fin effective déclarée après coup',
    'effectiveDate' => $effectiveTermination,
], $token);
check(
    'la résiliation accepte et conserve une date effective passée',
    $terminationStatus === 200 && str_starts_with((string) ($terminationPayload['data']['terminationDate'] ?? ''), $effectiveTermination),
    "HTTP {$terminationStatus}, date=" . ($terminationPayload['data']['terminationDate'] ?? 'absente')
);

$reportedDate = (new \DateTimeImmutable('2 days ago', new \DateTimeZone('UTC')))->format('Y-m-d');
[$maintenanceStatus, $maintenancePayload] = $request('POST', '/api/v1/maintenance/work-orders', [
    'organizationUuid' => $uuidOrgA,
    'cityUuid' => $uuidCityA,
    'unitUuid' => $uuidUnitA,
    'title' => 'Test date rétroactive ' . $suffix,
    'description' => 'Contrôle transactionnel des dates métier.',
    'priority' => 'normal',
    'reportedAt' => $reportedDate,
], $token);
$maintenance = $maintenancePayload['data'] ?? [];
check(
    'un signalement rétroactif garde date, auteur et horodatage d’enregistrement',
    $maintenanceStatus === 201
        && ($maintenance['reportedAt'] ?? null) === $reportedDate
        && ($maintenance['createdBy'] ?? null) === 'Admin Ville A'
        && isset($maintenance['createdAt']),
    "HTTP {$maintenanceStatus}, reportedAt=" . ($maintenance['reportedAt'] ?? 'absente')
);
[$futureReportedStatus] = $request('POST', '/api/v1/maintenance/work-orders', [
    'organizationUuid' => $uuidOrgA,
    'cityUuid' => $uuidCityA,
    'unitUuid' => $uuidUnitA,
    'title' => 'Test date future ' . $suffix,
    'description' => 'Une date de constatation ne peut pas être future.',
    'priority' => 'normal',
    'reportedAt' => (new \DateTimeImmutable('tomorrow', new \DateTimeZone('UTC')))->format('Y-m-d'),
], $token);
check('un signalement daté dans le futur est refusé', $futureReportedStatus === 422, "obtenu {$futureReportedStatus}");
[$startMaintenanceStatus] = $request('PATCH', '/api/v1/maintenance/work-orders/' . ($maintenance['uuid'] ?? 'invalid') . '/status', [
    'organizationUuid' => $uuidOrgA,
    'cityUuid' => $uuidCityA,
    'status' => 'in_progress',
], $token);
check('une intervention signalée peut passer en cours', $startMaintenanceStatus === 200, "HTTP {$startMaintenanceStatus}");
[$futureCompletionStatus] = $request('PATCH', '/api/v1/maintenance/work-orders/' . ($maintenance['uuid'] ?? 'invalid') . '/status', [
    'organizationUuid' => $uuidOrgA,
    'cityUuid' => $uuidCityA,
    'status' => 'completed',
    'completedAt' => (new \DateTimeImmutable('tomorrow', new \DateTimeZone('UTC')))->format('Y-m-d'),
], $token);
check('une date future d’achèvement est refusée', $futureCompletionStatus === 422, "HTTP {$futureCompletionStatus}");
$effectiveCompletion = (new \DateTimeImmutable('yesterday', new \DateTimeZone('UTC')))->format('Y-m-d');
[$completionStatus, $completionPayload] = $request('PATCH', '/api/v1/maintenance/work-orders/' . ($maintenance['uuid'] ?? 'invalid') . '/status', [
    'organizationUuid' => $uuidOrgA,
    'cityUuid' => $uuidCityA,
    'status' => 'completed',
    'completedAt' => $effectiveCompletion,
], $token);
check(
    'une intervention terminée tardivement conserve sa date réelle distincte de la saisie',
    $completionStatus === 200 && ($completionPayload['data']['completedAt'] ?? null) === $effectiveCompletion,
    "HTTP {$completionStatus}, completedAt=" . ($completionPayload['data']['completedAt'] ?? 'absente')
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
