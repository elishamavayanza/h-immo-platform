<?php

declare(strict_types=1);

/*
 * P0-7 — Les trois endpoints de liste ajoutés par P1-5 lisaient
 * `$this->cityRepository` (et `$this->organizationRepository`) alors que ces
 * repositories n'étaient ni déclarés ni injectés au constructeur. L'appel
 *levait donc `Error: Call to a member function …() on null` et renvoyait 500
 * pour tout le monde, dès le premier appel.
 *
 * `php bin/console lint:container` ne voit pas ce défaut : il valide le type
 * des arguments fournis, pas l'existence des propriétés lues par le corps de
 * la méthode. Seule une requête HTTP réelle sur les trois routes le prouve.
 *
 * Ce harnais appelle donc les trois routes à travers le noyau, avec un compte
 * PATRON, et exige 200 — pas 500 — ainsi que l'absence de fuite vers
 * l'organisation concurrente.
 *
 * Usage : php tests/verify-p0-7-list-endpoints.php
 */

use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationUser;
use App\Entity\Identity\User;
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
use App\Enum\LeaseStatus;
use App\Enum\OrganizationRole;
use App\Enum\OrganizationStatus;
use App\Enum\PaymentMethod;
use App\Enum\RentStatus;
use App\Enum\TenantType;
use App\Enum\UnitType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

/** Expose `doctrine` le temps du harnais : il est privé, donc inliné. */
final class P07Kernel extends App\Kernel
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

$kernel = new P07Kernel('dev', true);
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
 * Requête à travers le noyau : elle éprouve le pare-feu, le binding des DTO
 * et l'injection de dépendances du service, dans le même processus que la
 * construction du jeu de données.
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
// passant par le noyau : la base est purgée, puis la transaction est annulée
// en fin de script pour rester rejouable.
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

$suffix = 'p07' . bin2hex(random_bytes(3));

// ---------------------------------------------------------------------
section('Jeu de données : deux organisations concurrentes, un PATRON de A');

$patronA = (new User())
    ->setEmail('patron.a.' . $suffix . '@test.local')
    ->setFullName('Patron A')
    ->setPassword(password_hash('MotDePasse!123', PASSWORD_BCRYPT))
    ->setIsActive(true);
$em->persist($patronA);

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

$em->persist((new OrganizationUser())->setUser($patronA)->setOrganization($orgA)->setRole(OrganizationRole::PATRON));

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

$makeChain = static function (string $ref, City $city) use ($em): Unit {
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

    return $unit;
};

$unitA = $makeChain('U-A', $cityA);
$unitB = $makeChain('U-B', $cityB);

/** Un bail actif et une échéance impayée (donc en retard) par organisation. */
$makeRental = static function (string $ref, Organization $org, Unit $unit) use ($em): Rent {
    $tenant = (new Tenant())
        ->setFullName('Locataire ' . $ref)
        ->setType(TenantType::INDIVIDUAL)
        ->setPhone('+000')
        ->setOrganization($org);
    $em->persist($tenant);

    $lease = (new Lease())
        ->setOrganization($org)
        ->setTenant($tenant)
        ->setUnit($unit)
        ->setReference($ref)
        ->setStartDate(new \DateTimeImmutable('2026-01-01'))
        ->setMonthlyRent('500.00')
        ->setCurrency(Currency::USD)
        ->setStatus(LeaseStatus::ACTIVE);
    $em->persist($lease);

    $rent = (new Rent())
        ->setLease($lease)
        ->setPeriod(new \DateTimeImmutable('2026-01-01'))
        ->setDueDate(new \DateTimeImmutable('2026-01-05'))
        ->setAmount('500.00')
        ->setCurrency(Currency::USD)
        ->setStatus(RentStatus::PENDING);
    $em->persist($rent);

    return $rent;
};

$rentA = $makeRental('BAIL-A', $orgA, $unitA);
$rentB = $makeRental('BAIL-B', $orgB, $unitB);
$leaseA = $rentA->getLease();
$leaseB = $rentB->getLease();

$em->flush();

// Un règlement sur l'échéance de A : la liste des paiements n'est pas vide,
// ce qui exerce aussi la conversion entité -> DTO de réponse.
$paymentA = (new Payment())
    ->setRent($rentA)
    ->setAmount('200.00')
    ->setCurrency(Currency::USD)
    ->setPaymentDate(new \DateTimeImmutable('2026-01-10'))
    ->setCreatedBy($patronA)
    ->setMethod(PaymentMethod::CASH)
    ->setReference('PAY-A');
$em->persist($paymentA);

$em->flush();
$em->clear();

echo "  Jeu de données créé.\n";

$uuidA = $orgA->getUuid()->toRfc4122();
$uuidB = $orgB->getUuid()->toRfc4122();
$leaseUuidA = $leaseA->getUuid()->toRfc4122();
$leaseUuidB = $leaseB->getUuid()->toRfc4122();
$rentUuidA = $rentA->getUuid()->toRfc4122();
$paymentUuidA = $paymentA->getUuid()->toRfc4122();

[$status, $payload] = $request('POST', '/api/auth/login', [
    'email' => 'patron.a.' . $suffix . '@test.local',
    'password' => 'MotDePasse!123',
]);

$token = is_array($payload) ? ($payload['accessToken'] ?? null) : null;
check('Connexion du PATRON de A', $status === 200 && is_string($token), "obtenu {$status}");

if (!is_string($token)) {
    fwrite(STDERR, "[erreur] jeton absent, poursuite impossible\n");
    $rollback();
    exit(1);
}

// ---------------------------------------------------------------------
// Les trois routes qui renvoyaient 500
// ---------------------------------------------------------------------
section('P0-7 : les trois endpoints de liste répondent 200 (et non 500)');

[$status, $payload, $raw] = $request('GET', '/api/v1/payments', null, $token);
check(
    'GET /api/v1/payments',
    $status === 200,
    $status === 500 ? 'Error fatale (repository non injecté)' : "obtenu {$status} : " . substr($raw, 0, 200)
);

$items = is_array($payload) ? ($payload['data']['items'] ?? null) : null;
$listed = is_array($items) ? array_column($items, 'id') : [];
check(
    'GET /api/v1/payments : le paiement de A est présent',
    $listed === [$paymentUuidA],
    'liste=' . implode(',', array_map(strval(...), $listed))
);

[$status, $payload, $raw] = $request('GET', '/api/v1/leases', null, $token);
check(
    'GET /api/v1/leases',
    $status === 200,
    $status === 500 ? 'Error fatale (repository non injecté)' : "obtenu {$status} : " . substr($raw, 0, 200)
);

$items = is_array($payload) ? ($payload['data']['items'] ?? null) : null;
$listed = is_array($items) ? array_column($items, 'id') : [];
check(
    'GET /api/v1/leases : seul le bail de A est listé',
    $listed === [$leaseUuidA],
    'liste=' . implode(',', array_map(strval(...), $listed))
);

[$status, $payload, $raw] = $request('GET', '/api/v1/rents/overdue', null, $token);
check(
    'GET /api/v1/rents/overdue',
    $status === 200,
    $status === 500 ? 'Error fatale (repository non injecté)' : "obtenu {$status} : " . substr($raw, 0, 200)
);

$items = is_array($payload) ? ($payload['data']['items'] ?? null) : null;
$listed = is_array($items) ? array_column($items, 'id') : [];
check(
    'GET /api/v1/rents/overdue : seule l\'échéance de A est listée',
    $listed === [$rentUuidA],
    'liste=' . implode(',', array_map(strval(...), $listed))
);

// ---------------------------------------------------------------------
section('Les trois endpoints ne fuient pas l\'organisation concurrente');

[$status, $payload] = $request('GET', '/api/v1/leases?organizationId=' . $uuidB, null, $token);
$items = is_array($payload) ? ($payload['data']['items'] ?? null) : null;
check(
    'GET /api/v1/leases?organizationId=<B> : liste vide pour un PATRON de A',
    $status === 200 && is_array($items) && $items === [],
    "obtenu {$status}, items=" . (is_array($items) ? count($items) : 'n/a')
);

[$status, $payload] = $request('GET', '/api/v1/payments?organizationId=' . $uuidB, null, $token);
$items = is_array($payload) ? ($payload['data']['items'] ?? null) : null;
check(
    'GET /api/v1/payments?organizationId=<B> : liste vide pour un PATRON de A',
    $status === 200 && is_array($items) && $items === [],
    "obtenu {$status}, items=" . (is_array($items) ? count($items) : 'n/a')
);

[$status, $payload] = $request('GET', '/api/v1/rents/overdue?organizationId=' . $uuidA, null, $token);
check(
    'GET /api/v1/rents/overdue?organizationId=<A> : reste accessible à A',
    $status === 200,
    "obtenu {$status}"
);

// Le bail de B reste hors d'atteinte, y compris par UUID direct.
[$status] = $request('GET', '/api/v1/leases/' . $leaseUuidB, null, $token);
check(
    'GET /api/v1/leases/<uuid de B> : refusé (ni 200 ni 500)',
    $status === 403 || $status === 404,
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
