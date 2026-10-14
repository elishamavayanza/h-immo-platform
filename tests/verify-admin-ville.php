<?php

declare(strict_types=1);

/*
 * ADMIN_VILLE — espace de l'administrateur de ville.
 *
 * Couvre les endpoints utilisés par les pages de l'espace ADMIN_VILLE, avec
 * un ADMIN_VILLE rattaché à UNE SEULE ville (UserCity) dans l'organization A,
 * une seconde ville de la même organization (non attribuée) et une
 * organization concurrente B :
 *
 *  - GET  /api/v1/reports/admin-ville/{cityUuid}   (rapport borné à la ville)
 *  - GET  /api/v1/property/cities | /parcels | /units (périmètre de ville)
 *  - GET  /api/v1/tenants                          (D-V1 : sans bail visible)
 *  - POST /api/v1/parcels                          (CREATE_PARCEL autorisé)
 *  - POST /api/v1/property/cities                  (CREATE_CITY refusé)
 *  - POST /api/v1/workers                          (CREATE_WORKER refusé)
 *  - POST /api/v1/expenses/{uuid}/cancel           (D-V2 : DELETE_EXPENSE refusé)
 *
 * Les règles vérifiées :
 *  - un ADMIN_VILLE ne lit jamais une ville de sa propre organization qu'il
 *    ne s'est pas vue attribuer (c'est le point qu'un simple `isAdminVille()`
 *    global ne garantit pas) ;
 *  - le rapport admin-ville respecte le contrat `AdminVilleReportResponse` ;
 *  - D-V1 : un locataire sans bail reste visible ; un locataire dont le bail
 *    porte sur une ville non attribuée reste invisible ;
 *  - D-V2 : l'annulation de dépense est refusée à l'ADMIN_VILLE (le bouton
 *    est masqué côté front, mais l'API reste l'autorité).
 *
 * Usage : php tests/verify-admin-ville.php
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
final class AdminVilleKernel extends App\Kernel
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

$kernel = new AdminVilleKernel('dev', true);
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

$suffix = 'adv' . bin2hex(random_bytes(3));

// ---------------------------------------------------------------------
section('Jeu de données : ADMIN_VILLE de A (ville A1), villes A1/A2/B1');

$adminVille = (new User())
    ->setEmail('admin.ville.' . $suffix . '@test.local')
    ->setFullName('Admin Ville A')
    ->setPassword(password_hash('MotDePasse!123', PASSWORD_BCRYPT))
    ->setIsActive(true);
$em->persist($adminVille);

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

$em->persist(
    (new OrganizationUser())->setUser($adminVille)->setOrganization($orgA)->setRole(OrganizationRole::ADMIN_VILLE)
);

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
$cityA2 = $makeCity('Ville A2', 'A2', $orgA);
$cityB1 = $makeCity('Ville B1', 'B1', $orgB);

// L'ADMIN_VILLE n'est attribué qu'à A1 : A2 est dans la même organization
// mais hors de son périmètre.
$em->persist((new UserCity())->setUser($adminVille)->setCity($cityA1));

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

$chainA1 = $makeChain('U-A1', $cityA1);
$chainA2 = $makeChain('U-A2', $cityA2);
$chainB1 = $makeChain('U-B1', $cityB1);

$makeTenant = static function (string $name, Organization $org) use ($em): Tenant {
    $tenant = (new Tenant())
        ->setFullName($name)
        ->setType(TenantType::INDIVIDUAL)
        ->setPhone('+000')
        ->setOrganization($org);
    $em->persist($tenant);

    return $tenant;
};

$tenantLeaseA1 = $makeTenant('Locataire A1 ' . $suffix, $orgA);
$tenantLeaseA2 = $makeTenant('Locataire A2 ' . $suffix, $orgA);
$tenantNoLease = $makeTenant('Locataire sans bail ' . $suffix, $orgA)->setCity($cityA1);
$tenantB = $makeTenant('Locataire B ' . $suffix, $orgB);

$em->flush();

$makeLease = static function (string $ref, Organization $org, Unit $unit, Tenant $tenant) use ($em): Lease {
    $lease = (new Lease())
        ->setOrganization($org)
        ->setTenant($tenant)
        ->setUnit($unit)
        ->setReference($ref . '-' . bin2hex(random_bytes(2)))
        ->setStartDate(new \DateTimeImmutable('now -60 days'))
        ->setMonthlyRent('500.00')
        ->setCurrency(Currency::USD)
        ->setStatus(LeaseStatus::ACTIVE);
    $em->persist($lease);

    return $lease;
};

$leaseA1 = $makeLease('BAIL-A1', $orgA, $chainA1['unit'], $tenantLeaseA1);
$leaseA2 = $makeLease('BAIL-A2', $orgA, $chainA2['unit'], $tenantLeaseA2);
$leaseB1 = $makeLease('BAIL-B1', $orgB, $chainB1['unit'], $tenantB);

$em->flush();

$rentA1 = (new Rent())
    ->setLease($leaseA1)
    ->setPeriod((new \DateTimeImmutable('now -10 days'))->setTime(0, 0))
    ->setDueDate((new \DateTimeImmutable('now -5 days'))->setTime(0, 0))
    ->setAmount('500.00')
    ->setCurrency(Currency::USD)
    ->setStatus(RentStatus::PENDING);
$em->persist($rentA1);

$workerA1 = (new Worker())
    ->setOrganization($orgA)
    ->setFullName('Ouvrier A1 ' . $suffix)
    ->setPhone('+000')
    ->setEmail('ouvrier.a1.' . $suffix . '@test.local');
$em->persist($workerA1);
$workerA2 = (new Worker())
    ->setOrganization($orgA)
    ->setFullName('Ouvrier A2 ' . $suffix)
    ->setPhone('+001')
    ->setEmail('ouvrier.a2.' . $suffix . '@test.local');
$em->persist($workerA2);
$workerA3 = (new Worker())
    ->setOrganization($orgA)
    ->setFullName('Ouvrier A1 disponible ' . $suffix)
    ->setPhone('+002')
    ->setEmail('ouvrier.a1.disponible.' . $suffix . '@test.local');
$em->persist($workerA3);

$em->flush();

$assignmentA1 = (new WorkerAssignment())
    ->setWorker($workerA1)
    ->setCity($cityA1)
    ->setRole(WorkerRole::GARDIEN)
    ->setMonthlySalary('150.00')
    ->setCurrency(Currency::USD)
    ->setStartDate(new \DateTimeImmutable('now -20 days'));
$em->persist($assignmentA1);
$assignmentA2 = (new WorkerAssignment())
    ->setWorker($workerA2)
    ->setCity($cityA2)
    ->setRole(WorkerRole::GARDIEN)
    ->setMonthlySalary('150.00')
    ->setCurrency(Currency::USD)
    ->setStartDate(new \DateTimeImmutable('now -20 days'));
$em->persist($assignmentA2);
$historicalAssignmentA1 = (new WorkerAssignment())
    ->setWorker($workerA3)
    ->setCity($cityA1)
    ->setParcel($chainA1['parcel'])
    ->setRole(WorkerRole::AGENT_ENTRETIEN)
    ->setMonthlySalary('120.00')
    ->setCurrency(Currency::USD)
    ->setStartDate(new \DateTimeImmutable('now -60 days'))
    ->setEndDate(new \DateTimeImmutable('now -30 days'));
$em->persist($historicalAssignmentA1);

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

$expenseA1 = $makeExpense('120.00', $orgA, $cityA1, $adminVille);
$expenseA2 = $makeExpense('340.00', $orgA, $cityA2, $adminVille);
$expenseB1 = $makeExpense('999.00', $orgB, $cityB1, $adminVille);

$em->flush();
$em->clear();

echo "  Jeu de données créé.\n";

$uuidOrgA = $orgA->getUuid()->toRfc4122();
$uuidCityA1 = $cityA1->getUuid()->toRfc4122();
$uuidCityA2 = $cityA2->getUuid()->toRfc4122();
$uuidCityB1 = $cityB1->getUuid()->toRfc4122();
$uuidParcelA1 = $chainA1['parcel']->getUuid()->toRfc4122();
$uuidParcelA2 = $chainA2['parcel']->getUuid()->toRfc4122();
$uuidBuildingA1 = $chainA1['building']->getUuid()->toRfc4122();
$uuidBuildingB1 = $chainB1['building']->getUuid()->toRfc4122();
$uuidUnitA1 = $chainA1['unit']->getUuid()->toRfc4122();
$uuidUnitB1 = $chainB1['unit']->getUuid()->toRfc4122();
$uuidRentA1 = $rentA1->getUuid()->toRfc4122();
$uuidTenantLeaseA1 = $tenantLeaseA1->getUuid()->toRfc4122();
$uuidTenantLeaseA2 = $tenantLeaseA2->getUuid()->toRfc4122();
$uuidTenantNoLease = $tenantNoLease->getUuid()->toRfc4122();
$uuidTenantB = $tenantB->getUuid()->toRfc4122();
$uuidExpenseA1 = $expenseA1->getUuid()->toRfc4122();
$uuidExpenseA2 = $expenseA2->getUuid()->toRfc4122();
$uuidWorkerA1 = $workerA1->getUuid()->toRfc4122();
$uuidWorkerA2 = $workerA2->getUuid()->toRfc4122();
$uuidWorkerA3 = $workerA3->getUuid()->toRfc4122();

[$status, $payload] = $request('POST', '/api/auth/login', [
    'email' => 'admin.ville.' . $suffix . '@test.local',
    'password' => 'MotDePasse!123',
]);

$token = is_array($payload) ? ($payload['accessToken'] ?? null) : null;
check("Connexion de l'ADMIN_VILLE de A", $status === 200 && is_string($token), "obtenu {$status}");

if (!is_string($token)) {
    fwrite(STDERR, "[erreur] jeton absent, poursuite impossible\n");
    $rollback();
    exit(1);
}

[$status] = $request('DELETE', '/api/v1/media/organizations/' . $uuidOrgA . '/logo', null, $token);
check('ADMIN_VILLE ne peut pas modifier le logo de l’organisation', $status === 403, 'obtenu ' . $status);

// Un seul fichier multipart peut arriver sous `files` comme UploadedFile
// (et non comme tableau) dans FileBag : ce cas doit rester accepté.
$imageContents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j3ioAAAAASUVORK5CYII=', true);
$uploadedPaths = [];
$tempUploadPaths = [];
register_shutdown_function(static function () use (&$uploadedPaths, &$tempUploadPaths): void {
    foreach ($uploadedPaths as $path) {
        @unlink(dirname(__DIR__) . '/public/uploads/' . $path);
    }
    foreach ($tempUploadPaths as $path) @unlink($path);
});
$uploadSinglePhoto = static function (string $uri, string $field) use ($kernel, $token, $imageContents, &$tempUploadPaths): array {
    $server = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $uri, 'HTTP_HOST' => 'localhost', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token];
    $imagePath = tempnam(sys_get_temp_dir(), 'admin-ville-photo-');
    file_put_contents($imagePath, $imageContents);
    $tempUploadPaths[] = $imagePath;
    $file = new Symfony\Component\HttpFoundation\File\UploadedFile($imagePath, 'parcelle.png', 'image/png', UPLOAD_ERR_OK, true);
    $req = Request::create($uri, 'POST', [], [], [$field => $file], $server);
    $response = $kernel->handle($req);
    $result = [$response->getStatusCode(), json_decode((string) $response->getContent(), true)];
    $kernel->terminate($req, $response);

    return $result;
};
$trackUploadedPaths = static function (array $payload) use (&$uploadedPaths): void {
    foreach (($payload['data']['uploaded'] ?? []) as $file) {
        if (is_string($file['path'] ?? null)) $uploadedPaths[] = $file['path'];
    }
    foreach (($payload['data'] ?? []) as $photo) {
        if (!is_array($photo) || !is_string($photo['url'] ?? null)) continue;
        $path = parse_url($photo['url'], PHP_URL_PATH);
        if (is_string($path) && str_starts_with($path, '/uploads/')) $uploadedPaths[] = substr($path, strlen('/uploads/'));
    }
};

section('Photos : upload multipart d’un fichier unique');
[$status, $parcelPhotoPayload] = $uploadSinglePhoto('/api/v1/media/parcels/' . $uuidParcelA1 . '/photos', 'files');
$trackUploadedPaths($parcelPhotoPayload ?? []);
check('ADMIN_VILLE ajoute une photo unique à une parcelle de sa ville', $status === 200 && count($parcelPhotoPayload['data']['uploaded'] ?? []) === 1, 'obtenu ' . $status);
[$status, $buildingPhotoPayload] = $uploadSinglePhoto('/api/v1/media/buildings/' . $uuidBuildingA1 . '/photos', 'files');
$trackUploadedPaths($buildingPhotoPayload ?? []);
check('ADMIN_VILLE ajoute une photo unique à un bâtiment de sa ville', $status === 200 && count($buildingPhotoPayload['data']['uploaded'] ?? []) === 1, 'obtenu ' . $status);
[$status, $unitPhotoPayload] = $uploadSinglePhoto('/api/v1/units/' . $uuidUnitA1 . '/photos', 'file');
$trackUploadedPaths($unitPhotoPayload ?? []);
check('ADMIN_VILLE ajoute une photo à une unité de sa ville', $status === 201 && count($unitPhotoPayload['data'] ?? []) === 1, 'obtenu ' . $status);
[$status, $unitPhotosPayload] = $request('GET', '/api/v1/units/' . $uuidUnitA1 . '/photos', null, $token);
check('ADMIN_VILLE consulte les photos de son unité avec leur UUID de suppression', $status === 200 && is_string($unitPhotosPayload['data'][0]['uuid'] ?? null), 'obtenu ' . $status);
[$status] = $request('GET', '/api/v1/units/' . $uuidUnitB1 . '/photos', null, $token);
check('ADMIN_VILLE ne consulte pas les photos d’une unité hors ville', in_array($status, [403, 404], true), 'obtenu ' . $status);

/** @return list<string> */
$payloadIds = static function (array $payload): array {
    $items = $payload['data']['items'] ?? [];

    return is_array($items) ? array_values(array_map(static fn ($x) => $x['id'] ?? null, $items)) : [];
};

[$status, $workerList, $rawWorkers] = $request('GET', '/api/v1/workers?organizationId=' . $uuidOrgA, null, $token);
$workerIds = $payloadIds($workerList);
check(
    'GET /api/v1/workers : travailleurs limités aux villes attribuées (A1 visible, A2 masqué)',
    $status === 200 && in_array($uuidWorkerA1, $workerIds, true) && !in_array($uuidWorkerA2, $workerIds, true),
    "obtenu {$status} | liste=" . implode(',', $workerIds) . ' | raw=' . substr($rawWorkers, 0, 350)
);

[$status, $createdAssignment, $rawCreatedAssignment] = $request('POST', '/api/v1/worker-assignments', [
    'workerUuid' => $uuidWorkerA3,
    'cityUuid' => $uuidCityA1,
    'parcelUuid' => $uuidParcelA1,
    'role' => WorkerRole::GARDIEN->value,
    'monthlySalary' => '175.00',
    'currency' => Currency::USD->value,
    'startDate' => (new DateTimeImmutable('today'))->format('Y-m-d'),
], $token);
$createdAssignmentUuid = $createdAssignment['data']['id'] ?? null;
check(
    'ADMIN_VILLE crée une affectation pour un employé déjà autorisé dans sa ville',
    $status === 200 && is_string($createdAssignmentUuid),
    "obtenu {$status} : " . substr($rawCreatedAssignment, 0, 250)
);

if (is_string($createdAssignmentUuid)) {
    [$status, $updatedAssignment, $rawUpdatedAssignment] = $request('PUT', '/api/v1/worker-assignments/' . $createdAssignmentUuid, [
        'cityUuid' => $uuidCityA1,
        'parcelUuid' => $uuidParcelA1,
        'role' => WorkerRole::AGENT_ENTRETIEN->value,
        'monthlySalary' => '185.00',
        'currency' => Currency::USD->value,
        'startDate' => (new DateTimeImmutable('today'))->format('Y-m-d'),
        'notes' => 'Mise à jour de vérification',
    ], $token);
    check(
        'ADMIN_VILLE modifie une affectation de sa ville (UUID BINARY(16))',
        $status === 200 && ($updatedAssignment['data']['monthlySalary'] ?? null) === '185.00',
        "obtenu {$status} : " . substr($rawUpdatedAssignment, 0, 250)
    );

    [$status, $endedAssignment, $rawEndedAssignment] = $request('POST', '/api/v1/worker-assignments/' . $createdAssignmentUuid . '/end', [
        'endDate' => (new DateTimeImmutable('today'))->format('Y-m-d'),
    ], $token);
    check(
        'ADMIN_VILLE termine une affectation de sa ville',
        $status === 200 && str_contains((string) ($endedAssignment['flushDescription'] ?? ''), 'terminée'),
        "obtenu {$status} : " . substr($rawEndedAssignment, 0, 250)
    );
}

// ---------------------------------------------------------------------
section('Patrimoine : borné aux villes attribuées (UserCity)');

[$status, $payload, $rawCities] = $request('GET', '/api/v1/property/cities', null, $token);
$cityIds = $payloadIds($payload);
check(
    'GET /api/v1/property/cities : la ville attribuée (A1) est listée',
    $status === 200 && in_array($uuidCityA1, $cityIds, true),
    "obtenu {$status} | liste=" . implode(',', $cityIds) . ' | raw=' . substr($rawCities, 0, 300)
);
check(
    'GET /api/v1/property/cities : la ville A2 (même org, non attribuée) ne fuite pas',
    !in_array($uuidCityA2, $cityIds, true),
    'liste=' . implode(',', $cityIds)
);
check(
    'GET /api/v1/property/cities : la ville B1 (autre org) ne fuite pas',
    !in_array($uuidCityB1, $cityIds, true),
    'liste=' . implode(',', $cityIds)
);

[$status, $payload, $rawParcels] = $request('GET', '/api/v1/parcels?cityUuid=' . $uuidCityA1, null, $token);
check(
    'GET /api/v1/parcels?cityUuid=A1 : la parcelle de A1 est trouvée',
    $status === 200 && in_array($uuidParcelA1, $payloadIds($payload), true),
    "obtenu {$status} | liste=" . implode(',', $payloadIds($payload)) . ' | raw=' . substr($rawParcels, 0, 300)
);

[$status, $payload] = $request('GET', '/api/v1/parcels?cityUuid=' . $uuidCityA2, null, $token);
check(
    'GET /api/v1/parcels?cityUuid=A2 : refusé ou vide (ville non attribuée)',
    ($status === 403 || ($status === 200 && $payloadIds($payload) === []))
        && !in_array($uuidParcelA2, $payloadIds($payload), true),
    "obtenu {$status}, items=" . implode(',', $payloadIds($payload))
);

[$status, $payload] = $request('GET', '/api/v1/units?buildingUuid=' . $uuidBuildingB1, null, $token);
check(
    'GET /api/v1/units?buildingUuid=B1 : refusé ou vide (autre org)',
    $status === 403 || $status === 404 || ($status === 200 && $payloadIds($payload) === []),
    "obtenu {$status}, items=" . implode(',', $payloadIds($payload))
);

// ---------------------------------------------------------------------
section('Rapport admin-ville : contrat et périmètre');

[$status, $report, $rawReport] = $request('GET', '/api/v1/reports/admin-ville/' . $uuidCityA1, null, $token);
check(
    'GET /api/v1/reports/admin-ville/{A1} : 200',
    $status === 200 && is_array($report),
    "obtenu {$status} | raw=" . substr($rawReport, 0, 300)
);
check(
    'Rapport : cityUuid et cityName conformes à la ville demandée',
    ($report['cityUuid'] ?? null) === $uuidCityA1 && ($report['cityName'] ?? null) === 'Ville A1',
    'cityUuid=' . ($report['cityUuid'] ?? 'null') . ', cityName=' . ($report['cityName'] ?? 'null')
);
check(
    'Rapport : totalUnits, occupancyRate, totalExpenses et currency présents et typés',
    isset($report['totalUnits'], $report['occupancyRate'], $report['totalExpenses'], $report['currency'])
        && is_int($report['totalUnits'])
        && (is_float($report['occupancyRate']) || is_int($report['occupancyRate']))
        && is_string($report['totalExpenses'])
        && is_string($report['currency']),
    'champs=' . json_encode(array_intersect_key($report ?? [], array_flip(['totalUnits', 'occupancyRate', 'totalExpenses', 'currency'])))
);
check(
    'Rapport : occupation par parcelle et par immeuble exposées en tableaux',
    is_array($report['occupancyByParcel'] ?? null) && is_array($report['occupancyByBuilding'] ?? null),
    'raw=' . substr($rawReport, 0, 200)
);
$reportWorkerUuids = is_array($report['workers'] ?? null)
    ? array_map(static fn ($w) => $w['workerUuid'] ?? null, $report['workers'])
    : [];
check(
    'Rapport : le personnel affecté à A1 apparaît dans `workers`',
    in_array($uuidWorkerA1, $reportWorkerUuids, true),
    'workers=' . implode(',', $reportWorkerUuids)
);
check(
    'Rapport : seules les dépenses de A1 sont agrégées (totalExpenses = 120.00, pas 460.00)',
    ($report['totalExpenses'] ?? null) === '120.00',
    'totalExpenses=' . ($report['totalExpenses'] ?? 'null')
);

[$status, $payload, $raw] = $request(
    'GET',
    '/api/v1/reports/admin-ville/' . $uuidCityA1 . '?periodFrom=2020-01-01&periodTo=' . (new \DateTimeImmutable('now +1 day'))->format('Y-m-d'),
    null,
    $token
);
check(
    'GET /api/v1/reports/admin-ville/{A1}?periodFrom&periodTo : 200',
    $status === 200 && is_array($payload),
    "obtenu {$status} | raw=" . substr($raw, 0, 200)
);

[$status, $payload, $raw] = $request('GET', '/api/v1/reports/admin-ville/' . $uuidCityA2, null, $token);
check(
    'GET /api/v1/reports/admin-ville/{A2} : 403 (ville de la même org non attribuée)',
    $status === 403,
    "obtenu {$status} | raw=" . substr($raw, 0, 250)
);

[$status, $payload, $raw] = $request('GET', '/api/v1/reports/admin-ville/' . $uuidCityB1, null, $token);
check(
    'GET /api/v1/reports/admin-ville/{B1} : 403 ou 404 (autre organization)',
    $status === 403 || $status === 404,
    "obtenu {$status} | raw=" . substr($raw, 0, 250)
);

[$status, $payload, $raw] = $request('GET', '/api/v1/reports/admin-ville/' . $uuidCityA1 . '?format=pdf', null, $token);
check(
    'GET /api/v1/reports/admin-ville/{A1}?format=pdf : flux PDF servi',
    $status === 200 && str_starts_with((string) $raw, '%PDF'),
    "obtenu {$status}, début=" . substr($raw, 0, 5)
);

// ---------------------------------------------------------------------
section('Locataires : D-V1 (sans bail visible, ville non attribuée invisible)');

[$status, $payload] = $request('GET', '/api/v1/rents?organizationId=' . $uuidOrgA . '&cityUuid=' . $uuidCityA1, null, $token);
$rentIdsA1 = $payloadIds($payload);
check(
    'GET /api/v1/rents?cityUuid=A1 : échéance de la ville active visible',
    $status === 200 && in_array($uuidRentA1, $rentIdsA1, true),
    'status=' . $status . ' liste=' . implode(',', $rentIdsA1)
);
[$status, $payload] = $request('GET', '/api/v1/rents?organizationId=' . $uuidOrgA . '&cityUuid=' . $uuidCityA2, null, $token);
check(
    'GET /api/v1/rents?cityUuid=A2 : ville non attribuée sans échéances',
    $status === 200 && $payloadIds($payload) === [],
    'status=' . $status . ' liste=' . implode(',', $payloadIds($payload))
);

[$status, $payload, $rawTenants] = $request('GET', '/api/v1/tenants?organizationId=' . $uuidOrgA . '&cityUuid=' . $uuidCityA1, null, $token);
$tenantIds = $payloadIds($payload);
check(
    'GET /api/v1/tenants : le locataire avec bail sur A1 est listé',
    $status === 200 && in_array($uuidTenantLeaseA1, $tenantIds, true),
    "obtenu {$status} | liste=" . implode(',', $tenantIds) . ' | raw=' . substr($rawTenants, 0, 400)
);
check(
    'GET /api/v1/tenants : D-V1 — le locataire sans bail est visible',
    in_array($uuidTenantNoLease, $tenantIds, true),
    'liste=' . implode(',', $tenantIds)
);
check(
    'GET /api/v1/tenants : le locataire dont le bail porte sur A2 (non attribuée) reste invisible',
    !in_array($uuidTenantLeaseA2, $tenantIds, true),
    'liste=' . implode(',', $tenantIds)
);
check(
    'GET /api/v1/tenants : le locataire de B ne fuite pas',
    !in_array($uuidTenantB, $tenantIds, true),
    'liste=' . implode(',', $tenantIds)
);

[$status, $payload] = $request('GET', '/api/v1/tenants?organizationId=' . $uuidOrgA . '&cityUuid=' . $uuidCityA2, null, $token);
$tenantIdsCityA2 = $payloadIds($payload);
check(
    'GET /api/v1/tenants?cityUuid=A2 : une ville non attribuée ne renvoie aucun locataire',
    $status === 200 && $tenantIdsCityA2 === [],
    'status=' . $status . ' liste=' . implode(',', $tenantIdsCityA2)
);

[$status, $payload] = $request('GET', '/api/v1/tenants/' . $uuidTenantNoLease, null, $token);
check(
    'GET /api/v1/tenants/{sans bail} : 200 (D-V1)',
    $status === 200,
    "obtenu {$status}"
);

[$status, $payload] = $request('GET', '/api/v1/tenants/' . $uuidTenantLeaseA2, null, $token);
check(
    'GET /api/v1/tenants/{bail sur A2} : refusé (403/404)',
    in_array($status, [403, 404], true),
    "obtenu {$status}"
);

[$status, $payload] = $request('PATCH', '/api/v1/tenants/' . $uuidTenantLeaseA1 . '/archive', null, $token);
check(
    'PATCH /api/v1/tenants/{A1}/archive : 200 (ARCHIVE_TENANT autorisé)',
    $status === 200,
    "obtenu {$status}"
);

[$status] = $request('PATCH', '/api/v1/tenants/' . $uuidTenantLeaseA2 . '/archive', null, $token);
check(
    'PATCH /api/v1/tenants/{A2}/archive : refusé (403/404)',
    in_array($status, [403, 404], true),
    "obtenu {$status}"
);

// ---------------------------------------------------------------------
section('Matrice de permissions ADMIN_VILLE');

[$status, $payload, $raw] = $request('POST', '/api/v1/property/cities', [
    'organizationUuid' => $uuidOrgA,
    'name' => 'Ville interdite',
    'code' => 'NO' . strtoupper(substr($suffix, 0, 6)),
], $token);
check(
    'POST /api/v1/property/cities : 403 (CREATE_CITY refusé à ADMIN_VILLE)',
    $status === 403,
    "obtenu {$status} | raw=" . substr($raw, 0, 250)
);

[$status, $payload, $raw] = $request('POST', '/api/v1/workers', [
    'organizationUuid' => $uuidOrgA,
    'fullName' => 'Ouvrier interdit ' . $suffix,
    'phone' => '+243990000088',
], $token);
check(
    'POST /api/v1/workers : 403 (CREATE_WORKER refusé à ADMIN_VILLE)',
    $status === 403,
    "obtenu {$status} | raw=" . substr($raw, 0, 250)
);

[$status, $payload, $raw] = $request('POST', '/api/v1/expenses/' . $uuidExpenseA1 . '/cancel', [
    'reason' => 'Tentative admin ville',
], $token);
check(
    'POST /api/v1/expenses/{A1}/cancel : 403 (D-V2 — DELETE_EXPENSE refusé à ADMIN_VILLE)',
    $status === 403,
    "obtenu {$status} | raw=" . substr($raw, 0, 250)
);

[$status, $payload, $raw] = $request('POST', '/api/v1/parcels', [
    'cityUuid' => $uuidCityA1,
    'reference' => 'PARC-ADV-' . strtoupper(substr($suffix, 0, 6)),
    'name' => 'Parcelle créée par admin ville',
    'address' => 'Adresse créée',
    'area' => '100',
], $token);
check(
    'POST /api/v1/parcels dans A1 : création autorisée (CREATE_PARCEL)',
    $status === 200 || $status === 201,
    "obtenu {$status} | raw=" . substr($raw, 0, 250)
);

[$status, $payload, $raw] = $request('POST', '/api/v1/parcels', [
    'cityUuid' => $uuidCityA2,
    'reference' => 'PARC-NO-' . strtoupper(substr($suffix, 0, 6)),
    'name' => 'Parcelle interdite',
    'address' => 'Adresse interdite',
    'area' => '100',
], $token);
check(
    'POST /api/v1/parcels dans A2 : 403 (ville non attribuée)',
    $status === 403,
    "obtenu {$status} | raw=" . substr($raw, 0, 250)
);

[$status, $payload, $raw] = $request('POST', '/api/v1/expenses', [
    'cityUuid' => $uuidCityA1,
    'category' => 'maintenance',
    'amount' => '75.00',
    'currency' => 'USD',
    'expenseDate' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
], $token);
check(
    'POST /api/v1/expenses dans A1 : création autorisée (CREATE_EXPENSE)',
    $status === 200 || $status === 201,
    "obtenu {$status} | raw=" . substr($raw, 0, 250)
);

[$status, $payload, $raw] = $request('POST', '/api/v1/expenses', [
    'cityUuid' => $uuidCityA2,
    'category' => 'maintenance',
    'amount' => '75.00',
    'currency' => 'USD',
    'expenseDate' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
], $token);
check(
    'POST /api/v1/expenses dans A2 : 403 (ville non attribuée)',
    $status === 403,
    "obtenu {$status} | raw=" . substr($raw, 0, 250)
);

// ---------------------------------------------------------------------
section('Dépenses : lecture bornée');

[$status, $payload, $rawExpenses] = $request('GET', '/api/v1/expenses', null, $token);
check(
    'GET /api/v1/expenses : la dépense de A1 est listée',
    $status === 200 && in_array($uuidExpenseA1, $payloadIds($payload), true),
    "obtenu {$status} | liste=" . implode(',', $payloadIds($payload)) . ' | raw=' . substr($rawExpenses, 0, 300)
);
check(
    'GET /api/v1/expenses : la dépense de A2 (ville non attribuée) ne fuite pas',
    !in_array($uuidExpenseA2, $payloadIds($payload), true),
    'liste=' . implode(',', $payloadIds($payload))
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
