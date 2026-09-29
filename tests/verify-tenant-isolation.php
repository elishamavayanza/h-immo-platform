<?php

declare(strict_types=1);

/*
 * Vérification exécutable du périmètre multi-tenant.
 *
 * Ce script est un harnais de vérification, pas un test unitaire : il
 * construit un jeu de données minimal (deux organizations concurrentes)
 * puis exécute réellement les requêtes des repositories et des services
 * afin de détecter les erreurs DQL, qui ne se révèlent qu'à l\'exécution.
 *
 * Usage : php bin/console dbal:run-sql "..." n\'est pas suffisant, donc
 *        php .opencode/verify-tenant-isolation.php
 */

use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationUser;
use App\Entity\Identity\User;
use App\Entity\Property\Building;
use App\Entity\Property\City;
use App\Entity\Property\Parcel;
use App\Entity\Property\Unit;
use App\Entity\Rental\Lease;
use App\Entity\Rental\Rent;
use App\Entity\Rental\Tenant;
use App\Enum\BuildingType;
use App\Enum\CityStatus;
use App\Enum\Currency;
use App\Enum\LeaseStatus;
use App\Enum\OrganizationRole;
use App\Enum\OrganizationStatus;
use App\Enum\PlatformRole;
use App\Enum\RentStatus;
use App\Enum\TenantType;
use App\Enum\UnitType;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\Identity\UserCityRepository;
use App\Repository\Property\BuildingRepository;
use App\Repository\Property\CityRepository;
use App\Repository\Property\ParcelRepository;
use App\Repository\Property\UnitRepository;
use App\Repository\Rental\LeaseRepository;
use App\Repository\Rental\RentRepository;
use App\Repository\Rental\TenantRepository;
use App\Repository\System\AuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Uid\Uuid;

require dirname(__DIR__) . '/vendor/autoload.php';

// Le noyau ne charge pas les variables d\'environnement tout seul : c\'est le
// rôle de `bin/console`, absent de ce harnais.
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

/**
 * Rend publics les repositories et SecurityService le temps de ce harnais.
 *
 * Ces services sont privés (et donc inlinés) en production, ce qui est
 * correct : ils ne sont accessibles que par injection dans les services.
 * Le harnais doit pourtant les instancier pour exécuter leurs requêtes, il
 * est donc plus simple d\'exposer ces services depuis ce seul noyau plutôt
 * que d\'alourdir la configuration du projet.
 */
final class HarnessKernel extends App\Kernel
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

$kernel = new HarnessKernel('dev', true);
$kernel->boot();

/** @var EntityManagerInterface $em */
$em = $kernel->getContainer()->get('doctrine')->getManager();
$container = $kernel->getContainer();

$failures = [];
$checks = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures, $checks;
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

// ---------------------------------------------------------------------
// Isolation du harnais
//
// Le harnais travaille sur une transaction unique, ouverte au début et
// annulée à la fin : la base est donc rendue exactement dans son état
// initial, et le script est rejouable sans jamais détruire de données.
//
// Deux pièges évités ici :
//
//  1. `TRUNCATE TABLE` provoque un COMMIT IMPLICITE en MySQL/MariaDB. Il
//     annulait silencieusement la transaction et rendait le harnais
//     destructif. On utilise `DELETE`, qui est transactionnel en InnoDB.
//  2. Purger `doctrine_migration_versions` effaçait l'historique des
//     migrations : la base comme « non migrée » alors que son
//     schéma était à jour. Ces tables sont désormais exclues.
// ---------------------------------------------------------------------
section('Préparation du jeu de données (transaction annulée en fin de script)');

$connection = $em->getConnection();
$database = (string) $connection->fetchOne('SELECT DATABASE()');

$connection->beginTransaction();

/**
 * Filet de sécurité : garantit l'annulation même en cas d\'erreur fatale,
 * pour laquelle le `finally` n\'est pas exécuté.
 */
$rollback = static function () use ($connection): void {
    if ($connection->isTransactionActive()) {
        $connection->rollBack();
    }
};

register_shutdown_function(static function () use ($rollback): void {
    $rollback();
});

$tables = $connection->fetchFirstColumn(
    "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()"
);

$migrationTables = ['doctrine_migration_versions', 'migration_versions'];
$wiped = 0;
$skipped = [];

$connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

foreach (array_reverse($tables) as $table) {
    if (in_array($table, $migrationTables, true)) {
        // Jamais purger : l'historique des migrations doit survivre.
        $skipped[] = $table;
        continue;
    }

    $connection->executeStatement('DELETE FROM `' . $table . '`');
    $wiped++;
}

$connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

echo "  Base : {$database}\n";
echo "  Tables vidées dans la transaction : {$wiped}";

if ($skipped !== []) {
    echo "\n  Tables préservées (migrations) : " . implode(', ', $skipped);
}

echo "\n";

// ---------------------------------------------------------------------
// Jeu de données : deux organizations concurrentes
// ---------------------------------------------------------------------
section('Création du jeu de données (2 organizations concurrentes)');

$makeUser = static function (string $email, string $fullName, ?PlatformRole $role) use ($em): User {
    $user = new User();
    $user->setEmail($email);
    $user->setFullName($fullName);
    $user->setPassword(password_hash('Password123!', PASSWORD_BCRYPT));
    $user->setPlatformRole($role);
    $user->setIsActive(true);

    $em->persist($user);

    return $user;
};

$superAdmin = $makeUser('root@plateforme.test', 'Root Plateforme', PlatformRole::SUPER_ADMIN);
$patronA = $makeUser('patron.a@orga-a.test', 'Patron A', null);
$patronB = $makeUser('patron.b@orga-b.test', 'Patron B', null);
$adminVilleA = $makeUser('ville.a@orga-a.test', 'Admin Ville A', null);

$makeOrg = static function (string $name, string $code) use ($em): Organization {
    $org = new Organization();
    $org->setName($name);
    $org->setCode($code);
    // `email` et `phone` sont NOT NULL au niveau du schéma.
    $org->setEmail(strtolower($code) . '@orga.test');
    $org->setPhone('+000');
    $org->setStatus(OrganizationStatus::ACTIVE);

    $em->persist($org);

    return $org;
};

$orgA = $makeOrg('Organisation A', 'ORGA');
$orgB = $makeOrg('Organisation B', 'ORGB');

$membership = static function (User $user, Organization $org, OrganizationRole $role) use ($em): OrganizationUser {
    $ou = new OrganizationUser();
    $ou->setUser($user);
    $ou->setOrganization($org);
    $ou->setRole($role);

    $em->persist($ou);

    return $ou;
};

$membership($patronA, $orgA, OrganizationRole::PATRON);
$membership($patronB, $orgB, OrganizationRole::PATRON);
// Admin de ville rattaché à l'organization A, mais avec une seule ville.
$membership($adminVilleA, $orgA, OrganizationRole::ADMIN_VILLE);

$makeCity = static function (string $name, string $code, Organization $org) use ($em): City {
    $city = new City();
    $city->setName($name);
    $city->setCode($code);
    $city->setCountry('CD');
    $city->setStatus(CityStatus::ACTIVE);
    $city->setOrganization($org);

    $em->persist($city);

    return $city;
};

$cityA1 = $makeCity('Ville A1', 'A1', $orgA);
$cityA2 = $makeCity('Ville A2', 'A2', $orgA);
$cityB1 = $makeCity('Ville B1', 'B1', $orgB);

// L'admin de ville n\'est attribué qu\'à A1.
$userCity = new \App\Entity\Identity\UserCity();
$userCity->setUser($adminVilleA);
$userCity->setCity($cityA1);
$em->persist($userCity);

$makeParcel = static function (string $ref, City $city) use ($em): Parcel {
    $p = new Parcel();
    $p->setReference($ref);
    $p->setName('Parcelle ' . $ref);
    $p->setAddress('Adresse ' . $ref);
    $p->setArea('100');
    $p->setCity($city);
    $em->persist($p);

    return $p;
};

$parcelA1 = $makeParcel('PA-1', $cityA1);
$parcelA2 = $makeParcel('PA-2', $cityA2);
$parcelB1 = $makeParcel('PB-1', $cityB1);

$makeBuilding = static function (string $ref, Parcel $parcel) use ($em): Building {
    $b = new Building();
    $b->setReference($ref);
    $b->setName('Batiment ' . $ref);
    $b->setType(BuildingType::MIXED);
    $b->setParcel($parcel);
    $em->persist($b);

    return $b;
};

$buildingA1 = $makeBuilding('BA-1', $parcelA1);
$buildingA2 = $makeBuilding('BA-2', $parcelA2);
$buildingB1 = $makeBuilding('BB-1', $parcelB1);

$makeUnit = static function (string $ref, Building $building) use ($em): Unit {
    $u = new Unit();
    $u->setReference($ref);
    $u->setBuilding($building);
    $u->setType(UnitType::APARTMENT);
    $u->setFloor(1);
    $u->setSurface('60');
    $u->setMonthlyRent('500.00');
    $u->setCurrency(Currency::USD);
    $em->persist($u);

    return $u;
};

$unitA1 = $makeUnit('UA-1', $buildingA1);
$unitA2 = $makeUnit('UA-2', $buildingA2);
$unitB1 = $makeUnit('UB-1', $buildingB1);

$makeTenant = static function (string $name, Organization $org) use ($em): Tenant {
    $t = new Tenant();
    $t->setFullName($name);
    $t->setType(TenantType::INDIVIDUAL);
    $t->setPhone('+000');
    $t->setOrganization($org);
    $em->persist($t);

    return $t;
};

$tenantA = $makeTenant('Locataire A', $orgA);
$tenantB = $makeTenant('Locataire B', $orgB);

$em->flush();

// Un bail actif sur l'unité A1, un bail actif sur B1.
$leaseA = new Lease();
$leaseA->setOrganization($orgA);
$leaseA->setTenant($tenantA);
$leaseA->setUnit($unitA1);
$leaseA->setReference('BAIL-A-1');
$leaseA->setStartDate(new \DateTimeImmutable('2026-01-01'));
$leaseA->setMonthlyRent('500.00');
$leaseA->setCurrency(Currency::USD);
$leaseA->setStatus(LeaseStatus::ACTIVE);
$em->persist($leaseA);

$leaseB = new Lease();
$leaseB->setOrganization($orgB);
$leaseB->setTenant($tenantB);
$leaseB->setUnit($unitB1);
$leaseB->setReference('BAIL-B-1');
$leaseB->setStartDate(new \DateTimeImmutable('2026-01-01'));
$leaseB->setMonthlyRent('600.00');
$leaseB->setCurrency(Currency::USD);
$leaseB->setStatus(LeaseStatus::ACTIVE);
$em->persist($leaseB);

$em->flush();

$rentA = new Rent();
$rentA->setLease($leaseA);
$rentA->setPeriod(new \DateTimeImmutable('2026-01-01'));
$rentA->setDueDate(new \DateTimeImmutable('2026-01-05'));
$rentA->setAmount('500.00');
$rentA->setCurrency(Currency::USD);
$rentA->setStatus(RentStatus::PENDING);
$em->persist($rentA);

$em->flush();

// -------------------------------------------------------------------------
// Cas multi-rôles : PATRON dans A, ADMIN_VILLE (une seule ville) dans B.
// C'est le cas qui distingue une résolution de rôle « dans l'organization de
// la ressource » d'un prédicat global `isAdminVille()` : ce dernier trouve un
// rôle quelque part et applique à tort la restriction de B sur A.
// -------------------------------------------------------------------------
$patronEtAdminVille = $makeUser('mixte.a-b@orga.test', 'Patron A / Admin Ville B', null);
$membership($patronEtAdminVille, $orgA, OrganizationRole::PATRON);
$membership($patronEtAdminVille, $orgB, OrganizationRole::ADMIN_VILLE);

$userCityMixte = new \App\Entity\Identity\UserCity();
$userCityMixte->setUser($patronEtAdminVille);
$userCityMixte->setCity($cityB1);
$em->persist($userCityMixte);

$workerA = new \App\Entity\Staff\Worker();
$workerA->setFullName('Travailleur A');
$workerA->setPhone('+000');
$workerA->setOrganization($orgA);
$em->persist($workerA);

$workerB = new \App\Entity\Staff\Worker();
$workerB->setFullName('Travailleur B');
$workerB->setPhone('+000');
$workerB->setOrganization($orgB);
$em->persist($workerB);

$em->flush();

echo "  Jeu de données créé.\n";

// ---------------------------------------------------------------------
// Exécution réelle des requêtes
// ---------------------------------------------------------------------
section('Exécution des requêtes de repositories');

/** @var CityRepository $cityRepo */
$cityRepo = $container->get(CityRepository::class);
/** @var ParcelRepository $parcelRepo */
$parcelRepo = $container->get(ParcelRepository::class);
/** @var BuildingRepository $buildingRepo */
$buildingRepo = $container->get(BuildingRepository::class);
/** @var UnitRepository $unitRepo */
$unitRepo = $container->get(UnitRepository::class);
/** @var LeaseRepository $leaseRepo */
$leaseRepo = $container->get(LeaseRepository::class);
/** @var RentRepository $rentRepo */
$rentRepo = $container->get(RentRepository::class);
/** @var TenantRepository $tenantRepo */
$tenantRepo = $container->get(TenantRepository::class);
/** @var OrganizationRepository $orgRepo */
$orgRepo = $container->get(OrganizationRepository::class);
/** @var AuditLogRepository $auditRepo */
$auditRepo = $container->get(AuditLogRepository::class);

try {
    $r = $cityRepo->findPaginatedAccessible([$orgA, $orgB], null, 1, 50, null);
    check('CityRepository::findPaginatedAccessible (2 orgs)', $r['total'] === 3, 'total=' . $r['total']);
} catch (\Throwable $e) {
    check('CityRepository::findPaginatedAccessible (2 orgs)', false, $e->getMessage());
}

try {
    // Restriction type ADMIN_VILLE : uniquement A1.
    $r = $cityRepo->findPaginatedAccessible([$orgA, $orgB], [$cityA1], 1, 50, null);
    check('CityRepository::findPaginatedAccessible (1 ville autorisée)', $r['total'] === 1, 'total=' . $r['total']);
} catch (\Throwable $e) {
    check('CityRepository::findPaginatedAccessible (1 ville autorisée)', false, $e->getMessage());
}

try {
    $r = $parcelRepo->findPaginatedAccessible([$cityA1, $cityA2, $cityB1], 1, 50, null);
    check('ParcelRepository::findPaginatedAccessible', $r['total'] === 3, 'total=' . $r['total']);
} catch (\Throwable $e) {
    check('ParcelRepository::findPaginatedAccessible', false, $e->getMessage());
}

try {
    // Isolation : seules les parcelles de A1 et A2, pas B1.
    $r = $parcelRepo->findPaginatedAccessible([$cityA1, $cityA2], 1, 50, null);
    $refs = array_map(static fn (Parcel $p): string => $p->getReference(), $r['items']);
    sort($refs);
    check(
        'ParcelRepository::findPaginatedAccessible exclut l\'autre organization',
        $r['total'] === 2 && $refs === ['PA-1', 'PA-2'],
        'total=' . $r['total'] . ' refs=' . implode(',', $refs)
    );
} catch (\Throwable $e) {
    check('ParcelRepository::findPaginatedAccessible exclut l\'autre organization', false, $e->getMessage());
}

try {
    $r = $buildingRepo->findPaginatedAccessible([$cityA1, $cityA2, $cityB1], 1, 50, null);
    check('BuildingRepository::findPaginatedAccessible (jointure parcel->city)', $r['total'] === 3, 'total=' . $r['total']);
} catch (\Throwable $e) {
    check('BuildingRepository::findPaginatedAccessible (jointure parcel->city)', false, $e->getMessage());
}

try {
    $r = $unitRepo->findPaginatedAccessible([$cityA1, $cityA2, $cityB1], 1, 50, null);
    check('UnitRepository::findPaginatedAccessible (jointure profonde)', $r['total'] === 3, 'total=' . $r['total']);
} catch (\Throwable $e) {
    check('UnitRepository::findPaginatedAccessible (jointure profonde)', false, $e->getMessage());
}

try {
    $r = $unitRepo->findAvailableByBuilding($buildingA1);
    check('UnitRepository::findAvailableByBuilding exclut l\'unité louée', count($r) === 0, 'count=' . count($r));
} catch (\Throwable $e) {
    check('UnitRepository::findAvailableByBuilding exclut l\'unité louée', false, $e->getMessage());
}

try {
    // Le verrou doit être pris DANS une transaction, sinon MariaDB
    // l'accepte mais relâche le verrou immédiatement.
    $em->wrapInTransaction(function () use ($unitRepo, $unitA1): void {
        $unitRepo->lockForUpdate($unitA1);
    });
    check('UnitRepository::lockForUpdate (SELECT ... FOR UPDATE) en transaction', true);
} catch (\Throwable $e) {
    check('UnitRepository::lockForUpdate (SELECT ... FOR UPDATE) en transaction', false, $e->getMessage());
}

// Le garde-fou « doit être appelé dans une transaction » ne peut pas être
// testé ici : le harnais tient lui-même une transaction ouverte pour
// l'annulation finale, donc `isTransactionActive()` est toujours vrai.


try {
    $active = $leaseRepo->findActiveLeaseForUnit($unitA1);
    check(
        'LeaseRepository::findActiveLeaseForUnit trouve le bail actif',
        $active !== null && $active->getReference() === 'BAIL-A-1',
        $active?->getReference() ?? 'null'
    );
} catch (\Throwable $e) {
    check('LeaseRepository::findActiveLeaseForUnit trouve le bail actif', false, $e->getMessage());
}

try {
    $other = $leaseRepo->findActiveLeaseForUnit($unitA1, $leaseA->getUuid());
    check(
        'LeaseRepository::findActiveLeaseForUnit exclut le bail mis à jour',
        $other === null,
        $other?->getReference() ?? 'null'
    );
} catch (\Throwable $e) {
    check('LeaseRepository::findActiveLeaseForUnit exclut le bail mis à jour', false, $e->getMessage());
}

try {
    $r = $leaseRepo->findPaginatedByOrganization($orgA, 1, 50, null);
    check('LeaseRepository::findPaginatedByOrganization', $r['total'] === 1, 'total=' . $r['total']);
} catch (\Throwable $e) {
    check('LeaseRepository::findPaginatedByOrganization', false, $e->getMessage());
}

try {
    $r = $rentRepo->findPaginatedByOrganization($orgA, 1, 50, null);
    check('RentRepository::findPaginatedByOrganization', $r['total'] === 1, 'total=' . $r['total']);
} catch (\Throwable $e) {
    check('RentRepository::findPaginatedByOrganization', false, $e->getMessage());
}

try {
    $overdue = $rentRepo->findOverdueByOrganization($orgA);
    check('RentRepository::findOverdueByOrganization s\'exécute', is_array($overdue), 'count=' . count($overdue));
} catch (\Throwable $e) {
    check('RentRepository::findOverdueByOrganization s\'exécute', false, $e->getMessage());
}

// Régression ciblée : la requête qui interrogeait un champ inexistant.
try {
    $found = $tenantRepo->searchByOrganization($orgA, 'Locataire');
    check(
        'TenantRepository::searchByOrganization (régression t.lastName -> t.fullName)',
        count($found) === 1,
        'count=' . count($found)
    );
} catch (\Throwable $e) {
    check('TenantRepository::searchByOrganization (régression t.lastName -> t.fullName)', false, $e->getMessage());
}

try {
    $r = $tenantRepo->findPaginatedAccessible([$orgA, $orgB], null, 1, 50, null);
    check('TenantRepository::findPaginatedAccessible (sans filtre ville)', $r['total'] === 2, 'total=' . $r['total']);
} catch (\Throwable $e) {
    check('TenantRepository::findPaginatedAccessible (sans filtre ville)', false, $e->getMessage());
}

try {
    // ADMIN_VILLE de A1 : voit le locataire A (bail sur unité de A1) mais pas B.
    $r = $tenantRepo->findPaginatedAccessible([$orgA, $orgB], [$cityA1], 1, 50, null);
    check(
        'TenantRepository::findPaginatedAccessible restreint par ville (via baux)',
        $r['total'] === 1,
        'total=' . $r['total']
    );
} catch (\Throwable $e) {
    check('TenantRepository::findPaginatedAccessible restreint par ville (via baux)', false, $e->getMessage());
}

try {
    $r = $orgRepo->findPaginatedByUuids([(string) $orgA->getUuid()], 1, 50, null);
    check('OrganizationRepository::findPaginatedByUuids', $r['total'] === 1, 'total=' . $r['total']);
} catch (\Throwable $e) {
    check('OrganizationRepository::findPaginatedByUuids', false, $e->getMessage());
}

try {
    $empty = $orgRepo->findPaginatedByUuids([], 1, 50, null);
    check('OrganizationRepository::findPaginatedByUuids([]) renvoie vide', $empty['total'] === 0, 'total=' . $empty['total']);
} catch (\Throwable $e) {
    check('OrganizationRepository::findPaginatedByUuids([]) renvoie vide', false, $e->getMessage());
}

// -------------------------------------------------------------------------
// Invariant BINARY(16) : toute résolution d'UUID public doit Aboutir
//
// Les colonnes UUID sont stockées en BINARY(16). Un objet Uuid ou une
// chaîne « xxxxxxxx-… » liés tels quels dans une comparaison ne
// correspondent jamais : la requête renvoie zéro résultat sans erreur.
// Ce piège a rendu tous les endpoints de détail inopérants (404 sur des
// lignes existantes). Ces contrôles verrouillent le comportement correct.
// -------------------------------------------------------------------------
section('Résolution des UUID publics (colonnes BINARY(16))');

$userRepo = $kernel->getContainer()->get(\App\Repository\Identity\UserRepository::class);
$probeUuid = $patronA->getUuid();

try {
    $byObject = $userRepo->findOneBy(['uuid' => $probeUuid]);
    check('UserRepository::findOneBy([uuid => Uuid])', $byObject instanceof User, 'null');
} catch (\Throwable $e) {
    check('UserRepository::findOneBy([uuid => Uuid])', false, $e->getMessage());
}

try {
    $byString = $userRepo->findOneBy(['uuid' => $probeUuid->toRfc4122()]);
    check('UserRepository::findOneBy([uuid => string])', $byString instanceof User, 'null — une chaîne ne correspond pas en BINARY(16)');
} catch (\Throwable $e) {
    check('UserRepository::findOneBy([uuid => string])', false, $e->getMessage());
}

try {
    $byBinary = $userRepo->findOneBy(['uuid' => $probeUuid->toBinary()]);
    check('UserRepository::findOneBy([uuid => binaire])', $byBinary instanceof User, 'null');
} catch (\Throwable $e) {
    check('UserRepository::findOneBy([uuid => binaire])', false, $e->getMessage());
}

try {
    $byHelper = $userRepo->findOneByUuid($probeUuid);
    check('UserRepository::findOneByUuid() — helper canonique', $byHelper instanceof User, 'null');
} catch (\Throwable $e) {
    check('UserRepository::findOneByUuid() — helper canonique', false, $e->getMessage());
}

try {
    $orgByHelper = $orgRepo->findOneByUuid($orgA->getUuid());
    check('OrganizationRepository::findOneByUuid()', $orgByHelper instanceof Organization, 'null');
} catch (\Throwable $e) {
    check('OrganizationRepository::findOneByUuid()', false, $e->getMessage());
}

try {
    $r = $auditRepo->findByFilter($orgA, null, null, null, null, 1, 50);
    check('AuditLogRepository::findByFilter s\'exécute', is_array($r), 'total=' . $r['total']);
} catch (\Throwable $e) {
    check('AuditLogRepository::findByFilter s\'exécute', false, $e->getMessage());
}

try {
    $ids = $container->get(\App\Repository\Identity\UserCityRepository::class)->findCityIdsForUser($adminVilleA);
    check(
        'UserCityRepository::findCityIdsForUser (IDENTIFY)',
        $ids === [$cityA1->getId()],
        'ids=' . implode(',', $ids)
    );
} catch (\Throwable $e) {
    check('UserCityRepository::findCityIdsForUser (IDENTIFY)', false, $e->getMessage());
}

// ---------------------------------------------------------------------
// Règle métier : un seul bail actif par unité
// ---------------------------------------------------------------------
section('Règle métier : un seul bail actif par unité');

try {
    $conflict = $leaseRepo->findActiveLeaseForUnit($unitA1);
    check(
        'Un second bail actif sur la même unité est détecté comme conflit',
        $conflict !== null && $conflict->getUuid()->equals($leaseA->getUuid())
    );
} catch (\Throwable $e) {
    check('Un second bail actif sur la même unité est détecté comme conflit', false, $e->getMessage());
}

try {
    // L'historique est conservé : un bail terminé ne bloque pas la création
    // d\'un nouveau bail actif sur la même unité.
    $ended = new Lease();
    $ended->setOrganization($orgA);
    $ended->setTenant($tenantA);
    $ended->setUnit($unitA1);
    $ended->setReference('BAIL-A-0');
    $ended->setStartDate(new \DateTimeImmutable('2025-01-01'));
    $ended->setMonthlyRent('450.00');
    $ended->setCurrency(Currency::USD);
    $ended->setStatus(LeaseStatus::EXPIRED);
    $em->persist($ended);
    $em->flush();

    $history = $leaseRepo->findHistoryByUnit($unitA1);
    check(
        'L\'historique des baux d\'une unité est conservé',
        count($history) === 2,
        'count=' . count($history)
    );

    $em->remove($ended);
    $em->flush();
} catch (\Throwable $e) {
    check('L\'historique des baux d\'une unité est conservé', false, $e->getMessage());
}

// ---------------------------------------------------------------------
// Isolation au niveau service / sécurité
// ---------------------------------------------------------------------
section('Contrôles d\'accès (SecurityService)');

/** @var \App\Security\SecurityService $security */
$security = $container->get(\App\Security\SecurityService::class);

$tokenPatronA = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken(
    $patronA,
    'main',
    $patronA->getRoles()
);

$tokenAdminVille = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken(
    $adminVilleA,
    'main',
    $adminVilleA->getRoles()
);

$tokenSuperAdmin = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken(
    $superAdmin,
    'main',
    $superAdmin->getRoles()
);

$tokenStorage = $container->get('security.token_storage');
$tokenStorage->setToken($tokenPatronA);

try {
    check('Patron A voit uniquement l\'organization A', count($security->getCurrentUserOrganizations()) === 1);
} catch (\Throwable $e) {
    check('Patron A voit uniquement l\'organization A', false, $e->getMessage());
}

try {
    $security->checkLeaseAccess($leaseB, \App\Security\SecurityAction::VIEW_LEASE);
    check('Patron A ne peut PAS lire un bail de l\'organization B', false, 'aucune exception levée');
} catch (\App\Exception\AccessDeniedException $e) {
    check('Patron A ne peut PAS lire un bail de l\'organization B', true);
} catch (\Throwable $e) {
    check('Patron A ne peut PAS lire un bail de l\'organization B', false, get_class($e) . ': ' . $e->getMessage());
}

try {
    $security->checkLeaseAccess($leaseA, \App\Security\SecurityAction::VIEW_LEASE);
    check('Patron A peut lire un bail de son organization', true);
} catch (\Throwable $e) {
    check('Patron A peut lire un bail de son organization', false, $e->getMessage());
}

try {
    $cities = $security->getScopedCities();
    $codes = array_map(static fn (City $c): string => $c->getCode(), $cities);
    sort($codes);
    check(
        'Patron A : les villes de son organization sont résolues',
        $codes === ['A1', 'A2'],
        'codes=' . implode(',', $codes)
    );
} catch (\Throwable $e) {
    check('Patron A : les villes de son organization sont résolues', false, $e->getMessage());
}

$tokenStorage->setToken($tokenAdminVille);

try {
    $cities = $security->getScopedCities();
    $codes = array_map(static fn (City $c): string => $c->getCode(), $cities);
    check(
        'Admin de ville A : périmètre borné à sa ville attribuée',
        $codes === ['A1'],
        'codes=' . implode(',', $codes)
    );
} catch (\Throwable $e) {
    check('Admin de ville A : périmètre borné à sa ville attribuée', false, $e->getMessage());
}

try {
    $security->checkCityAccess($cityA2, \App\Security\SecurityAction::VIEW_CITY);
    check('Admin de ville A ne peut PAS accéder à une ville non attribuée', false, 'aucune exception levée');
} catch (\App\Exception\AccessDeniedException $e) {
    check('Admin de ville A ne peut PAS accéder à une ville non attribuée', true);
} catch (\Throwable $e) {
    check('Admin de ville A ne peut PAS accéder à une ville non attribuée', false, get_class($e) . ': ' . $e->getMessage());
}

try {
    // Le bail A est sur une unité de A1, sa ville : l'admin de ville y a accès.
    $security->checkLeaseAccess($leaseA, \App\Security\SecurityAction::VIEW_LEASE);
    check('Admin de ville A accède au bail situé dans sa ville', true);
} catch (\Throwable $e) {
    check('Admin de ville A accède au bail situé dans sa ville', false, $e->getMessage());
}

$tokenStorage->setToken($tokenSuperAdmin);

try {
    $cities = $security->getScopedCities();
    $codes = array_map(static fn (City $c): string => $c->getCode(), $cities);
    sort($codes);
    check(
        'SUPER_ADMIN : périmètre plateforme complet',
        $codes === ['A1', 'A2', 'B1'],
        'codes=' . implode(',', $codes)
    );
} catch (\Throwable $e) {
    check('SUPER_ADMIN : périmètre plateforme complet', false, $e->getMessage());
}

$tokenStorage->setToken(null);

// ---------------------------------------------------------------------
// P2-1 résiduel : le rôle se résout dans l'Organization de la ressource
// ---------------------------------------------------------------------
section('P2-1 : rôle résolu dans l\'Organization de la ressource (bail, personnel)');

$tokenMixte = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken(
    $patronEtAdminVille,
    'main',
    $patronEtAdminVille->getRoles()
);

$tokenStorage->setToken($tokenMixte);

try {
    $security->checkLeaseAccess($leaseA, \App\Security\SecurityAction::VIEW_LEASE);
    check(
        'PATRON de A + ADMIN_VILLE de B : accès au bail de A (non restreint par le rôle de B)',
        true
    );
} catch (\Throwable $e) {
    check(
        'PATRON de A + ADMIN_VILLE de B : accès au bail de A (non restreint par le rôle de B)',
        false,
        get_class($e) . ': ' . $e->getMessage()
    );
}

try {
    $security->checkWorkerAccess($workerA, \App\Security\SecurityAction::VIEW_WORKER);
    check(
        'PATRON de A + ADMIN_VILLE de B : accès au personnel de A (non restreint par le rôle de B)',
        true
    );
} catch (\Throwable $e) {
    check(
        'PATRON de A + ADMIN_VILLE de B : accès au personnel de A (non restreint par le rôle de B)',
        false,
        get_class($e) . ': ' . $e->getMessage()
    );
}

try {
    // En B il est bien ADMIN_VILLE, et B1 lui est attribuée : le bail de B
    // reste lisible. La correction ne doit pas inverser la matrice de rôles.
    $security->checkLeaseAccess($leaseB, \App\Security\SecurityAction::VIEW_LEASE);
    check('PATRON de A + ADMIN_VILLE de B : le bail de B reste lisible (B1 lui est attribuée)', true);
} catch (\Throwable $e) {
    check(
        'PATRON de A + ADMIN_VILLE de B : le bail de B reste lisible (B1 lui est attribuée)',
        false,
        get_class($e) . ': ' . $e->getMessage()
    );
}

try {
    $security->checkWorkerAccess($workerB, \App\Security\SecurityAction::VIEW_WORKER);
    check('PATRON de A + ADMIN_VILLE de B : le personnel de B reste refusé', false, 'aucune exception levée');
} catch (\App\Exception\AccessDeniedException) {
    check('PATRON de A + ADMIN_VILLE de B : le personnel de B reste refusé', true);
} catch (\Throwable $e) {
    check(
        'PATRON de A + ADMIN_VILLE de B : le personnel de B reste refusé',
        false,
        get_class($e) . ': ' . $e->getMessage()
    );
}

$tokenStorage->setToken(null);

// ---------------------------------------------------------------------
// Autorisation au niveau SERVICE
//
// `lint:container` ne verifie que l'injection des dependances : il
// confirme qu\'un service recoit bien un `SecurityService`, pas que les
// methodes appelees le declenchent reellement. Ces controles executent
// les methodes pour de vrai et verifient le refus (403) comme le
// perimetre effectivement renvoye.
// ---------------------------------------------------------------------
section("Autorisation au niveau Service");

$organizationService = $container->get(\App\Service\Identity\OrganizationService::class);
$organizationUserService = $container->get(\App\Service\Identity\OrganizationUserService::class);
$userService = $container->get(\App\Service\Identity\UserService::class);
$userCityService = $container->get(\App\Service\Identity\UserCityService::class);
$auditLogService = $container->get(\App\Service\System\AuditLogService::class);

$pagination = new \App\Dto\Request\PaginationQuery(page: 1, limit: 50);

/**
 * Verifie qu'un appel de service est refuse en 403.
 */
$expectDenied = static function (string $label, callable $call): void {
    try {
        $call();
        check($label, false, "aucune exception levee : l'acces aurait du etre refuse");
    } catch (\App\Exception\AccessDeniedException $e) {
        check($label, true);
    } catch (\App\Exception\UnauthenticatedException $e) {
        check($label, false, "401 recu alors que 403 etait attendu");
    } catch (\Throwable $e) {
        check($label, false, get_class($e) . ": " . $e->getMessage());
    }
};

$tokenStorage->setToken($tokenPatronA);

// --- OrganizationService -------------------------------------------------
try {
    $feedback = $organizationService->list($pagination);
    $total = $feedback->getData()["total"] ?? null;
    check(
        "Patron A : OrganizationService::list() ne renvoie que SA organization",
        $feedback->getStatus() === 200 && $total === 1,
        "status=" . $feedback->getStatus() . " total=" . var_export($total, true)
    );
} catch (\Throwable $e) {
    check("Patron A : OrganizationService::list() ne renvoie que SA organization", false, $e->getMessage());
}

$expectDenied(
    "Patron A : OrganizationService::getByUuid() sur l'organization B est refuse",
    static fn () => $organizationService->getByUuid($orgB->getUuid()->toRfc4122())
);

$expectDenied(
    "Patron A : OrganizationService::update() sur l'organization B est refuse",
    static fn () => $organizationService->update(
        $orgB->getUuid()->toRfc4122(),
        new \App\Dto\Request\Identity\OrganizationRequest(name: "Pirate", code: "ORGB")
    )
);

$expectDenied(
    "Patron A : la creation d'une Organization est refusee",
    static fn () => $organizationService->create(
        new \App\Dto\Request\Identity\OrganizationRequest(name: "Nouvelle", code: "NOUV")
    )
);

// --- OrganizationUserService : elevation de privileges --------------------
$expectDenied(
    "Patron A : s'attribuer PATRON de l'organization B est refuse",
    static fn () => $organizationUserService->assignUser(
        new \App\Dto\Request\Identity\OrganizationUserRequest(
            organizationUuid: $orgB->getUuid()->toRfc4122(),
            userUuid: $patronA->getUuid()->toRfc4122(),
            role: \App\Enum\OrganizationRole::PATRON
        )
    )
);

$expectDenied(
    "Patron A : lister les membres de l'organization B est refuse",
    static fn () => $organizationUserService->listByOrganization($orgB->getUuid()->toRfc4122(), $pagination)
);

// --- UserService ---------------------------------------------------------
try {
    $feedback = $userService->list($pagination);
    check(
        "Patron A : UserService::list() est limite a son organization",
        $feedback->getStatus() === 200,
        "status=" . $feedback->getStatus()
    );
} catch (\Throwable $e) {
    check("Patron A : UserService::list() est limite a son organization", false, $e->getMessage());
}

$expectDenied(
    "Patron A : UserService::getByUuid() sur un compte de l'organization B est refuse",
    static fn () => $userService->getByUuid($patronB->getUuid()->toRfc4122())
);

// --- UserCityService -----------------------------------------------------
$expectDenied(
    "Patron A : elargir le perimetre d'un compte de l'organization B est refuse",
    static fn () => $userCityService->assignCity(
        new \App\Dto\Request\Identity\UserCityRequest(
            userUuid: $patronB->getUuid()->toRfc4122(),
            cityUuid: $cityA1->getUuid()->toRfc4122()
        )
    )
);

// --- AuditLogService -----------------------------------------------------
$tokenStorage->setToken($tokenAdminVille);

$expectDenied(
    "Admin de ville : consulter l'audit log de la plateforme est refuse",
    static fn () => $auditLogService->getPaginatedLogs(new \App\Dto\Request\System\AuditLogFilterDto())
);

  // Un PATRON a legitimement acces aux journaux de sa propre Organization.
  // Sans filtre d'Organization, la requete ne doit surtout pas deborder
  // sur les autres tenants : le controle d'acces passe, la restriction
  // doit venir de la requete SQL elle-meme.
  $makeLog = static function (\App\Entity\Identity\Organization $org, string $action) use ($em): \App\Entity\System\AuditLog {
      $log = (new \App\Entity\System\AuditLog())
          ->setOrganization($org)
          ->setAction($action)
          ->setEntityType('probe')
          ->setEntityId(1);
      $em->persist($log);
      $em->flush();

      return $log;
  };

  $logA = $makeLog($orgA, 'probe.a');
  $logB = $makeLog($orgB, 'probe.b');

  $tokenStorage->setToken($tokenPatronA);
  $auditSansFiltre = $auditLogService->getPaginatedLogs(new \App\Dto\Request\System\AuditLogFilterDto());
  $organisationsVues = array_map(
      static fn (\App\Dto\Response\System\AuditLogResponse $log) => (string) $log->organizationId,
      $auditSansFiltre['items']
  );

  check(
      "PATRON A : le journal sans filtre ne contient que sa propre Organization",
      $organisationsVues !== [] && !in_array($orgB->getUuid()->toRfc4122(), $organisationsVues, true),
      'organisations vues : ' . implode(',', $organisationsVues)
  );

  $em->remove($logA);
  $em->remove($logB);
  $em->flush();

  // Les contrôles suivants repartent de l'admin de ville.
  $tokenStorage->setToken($tokenAdminVille);

$expectDenied(
    "Admin de ville : attribuer une ville est refuse",
    static fn () => $userCityService->assignCity(
        new \App\Dto\Request\Identity\UserCityRequest(
            userUuid: $adminVilleA->getUuid()->toRfc4122(),
            cityUuid: $cityA1->getUuid()->toRfc4122()
        )
    )
);

// --- SUPER_ADMIN : les operations de plateforme restent possibles --------
$tokenStorage->setToken($tokenSuperAdmin);

try {
    $feedback = $organizationService->list($pagination);
    $total = $feedback->getData()["total"] ?? null;
    check(
        "SUPER_ADMIN : OrganizationService::list() renvoie toutes les organizations",
        $feedback->getStatus() === 200 && $total >= 2,
        "status=" . $feedback->getStatus() . " total=" . var_export($total, true)
    );
} catch (\Throwable $e) {
    check("SUPER_ADMIN : OrganizationService::list() renvoie toutes les organizations", false, $e->getMessage());
}

$tokenStorage->setToken(null);

// ---------------------------------------------------------------------
section('Résumé');

echo "  Contrôles exécutés : {$checks}\n";
echo "  Échecs : " . count($failures) . "\n";

foreach ($failures as $failure) {
    echo "   - {$failure}\n";
}

// ---------------------------------------------------------------------
// Annulation : la base retrouve son état d'origine
// ---------------------------------------------------------------------
$rollback();

section('Annulation de la transaction');
echo "  Jeu de données de test annulé : la base est inchangée.\n";

$kernel->shutdown();

exit($failures === [] ? 0 : 1);
