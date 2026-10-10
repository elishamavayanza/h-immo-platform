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
$cityB = $makeCity('Ville B', 'VB', $orgB);

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

/** @return list<string> */
$payloadIds = static function (array $payload): array {
    $items = $payload['data']['items'] ?? [];

    return is_array($items) ? array_values(array_map(static fn ($x) => $x['id'] ?? null, $items)) : [];
};

// ---------------------------------------------------------------------
section('Locataires : liste, recherche, périmètre, archivage');

[$status, $payload, $rawTenants] = $request('GET', '/api/v1/tenants', null, $token);
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

[$status, $payload] = $request('GET', '/api/v1/tenants?organizationId=' . $uuidOrgB, null, $token);
check(
    'GET /api/v1/tenants?organizationId=<B> : liste vide pour A (pas d\'énumération)',
    $status === 200 && $payloadIds($payload) === [],
    "obtenu {$status}, items=" . implode(',', $payloadIds($payload))
);

[$status, $payload] = $request('GET', '/api/v1/tenants?search=' . $suffix, null, $token);
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

[$status, $payload] = $request('GET', '/api/v1/tenants', null, $token);
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
], $token);
$uuidCreatedWorker = is_array($payload) ? ($payload['data']['id'] ?? null) : null;
check(
    'POST /api/v1/workers : l’organisation active est prise en compte',
    $status === 201 && is_string($uuidCreatedWorker),
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
    $status === 201,
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
section('Villes : CRUD, unicité du code, isolation inter-organisation');

$newCityCode = 'CIT' . strtoupper(substr($suffix, 0, 8));

[$status, $payload, $raw] = $request('POST', '/api/v1/property/cities', [
    'organizationUuid' => $uuidOrgA,
    'name' => 'Ville Créée',
    'code' => $newCityCode,
    'province' => 'Nord-Kivu',
    'country' => 'RDC',
], $token);
$uuidCreatedCity = is_array($payload) ? ($payload['data']['id'] ?? null) : null;
check(
    'POST /api/v1/property/cities : ville créée (200 — D14 : autoInitFlush écrase le 201)',
    $status === 200 && is_string($uuidCreatedCity),
    "obtenu {$status} : " . substr($raw, 0, 300)
);

[$status, $payload] = $request('POST', '/api/v1/property/cities', [
    'organizationUuid' => $uuidOrgA,
    'name' => 'Doublon',
    'code' => $newCityCode,
], $token);
check(
    'POST /api/v1/property/cities : code déjà utilisé refusé (422 — D14 écrase le 409)',
    $status === 422 && isset($payload['errors']['code']),
    "obtenu {$status} : " . substr(json_encode($payload), 0, 300)
);

[$status, $payload] = $request('POST', '/api/v1/property/cities', [
    'organizationUuid' => $uuidOrgB,
    'name' => 'Intrusion',
    'code' => 'INTRUS' . strtoupper(substr($suffix, 0, 4)),
], $token);
check(
    'POST /api/v1/property/cities dans une autre organization : 403',
    $status === 403,
    "obtenu {$status}"
);

if (is_string($uuidCreatedCity)) {
    [$status, $payload] = $request('PUT', '/api/v1/property/cities/' . $uuidCreatedCity, [
        'name' => 'Ville Modifiée',
        'code' => $newCityCode,
        'status' => 'inactive',
    ], $token);
    check(
        'PUT /api/v1/property/cities/{uuid} : 200 et nom modifié',
        $status === 200 && (($payload['data']['name'] ?? null) === 'Ville Modifiée'),
        "obtenu {$status}"
    );

    [$status, $payload] = $request('DELETE', '/api/v1/property/cities/' . $uuidCreatedCity, null, $token);
    check('DELETE /api/v1/property/cities/{uuid} : 200', $status === 200, "obtenu {$status}");
}

[$status, $payload] = $request('PUT', '/api/v1/property/cities/' . $uuidCityB, [
    'name' => 'Piratage',
    'code' => 'HACK' . strtoupper(substr($suffix, 0, 4)),
], $token);
check('PUT ville d\'une autre organization : 403', $status === 403, "obtenu {$status}");

[$status, $payload] = $request('DELETE', '/api/v1/property/cities/' . $uuidCityB, null, $token);
check('DELETE ville d\'une autre organization : 403', $status === 403, "obtenu {$status}");

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
