<?php

declare(strict_types=1);

/*
 * Vérification exécutable des coordonnées GPS d'une Parcel.
 *
 * La mise en place des coordonnées doit répondre à trois exigences :
 *
 *  1. Optionnelles : une parcelle sans latitude/longitude reste créable.
 *  2. Indivisibles : une latitude sans longitude (ou l'inverse) est refusée
 *     en 422, une silhouette non exploitable ne doit pas être persistée.
 *  3. Bornées : latitude dans [-90, 90], longitude dans [-180, 180] (refusé
 *     en 422 sinon), et toute valeur non numérique rejetée.
 *
 * Le SCRUD complet est éprouvé par le noyau (`$kernel->handle()`) dans la
 * même transaction que le jeu de données : création avec coordonnées
 * (soumises en nombre JSON, comme le fera le front), lecture, mise à jour,
 * persistance en base (round-trip), puis rollback en fin de script.
 *
 * Usage : php tests/verify-parcel-coordinates.php
 */

use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationUser;
use App\Entity\Identity\User;
use App\Entity\Property\City;
use App\Entity\Property\Parcel;
use App\Enum\CityStatus;
use App\Enum\OrganizationRole;
use App\Enum\OrganizationStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

/**
 * Expose les repositories et services le temps du harnais : ils sont privés,
 * donc inlinés, en production.
 */
final class ParcelCoordsKernel extends App\Kernel
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

$kernel = new ParcelCoordsKernel('dev', true);
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
 * @return array{0: int, 1: array<string, mixed>|null}
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

    return [$status, json_decode($raw, true)];
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
    // Jamais purger l'historique des migrations.
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

$suffix = 'coord' . bin2hex(random_bytes(3));

// ---------------------------------------------------------------------
// Jeu de données : organisation, ville, patron habilité
// ---------------------------------------------------------------------
section('Construction du jeu de données');

$org = (new Organization())
    ->setName('Organisation Coordonnées')
    ->setCode('COORD-' . $suffix)
    ->setEmail('coord' . $suffix . '@orga.test')
    ->setPhone('+000')
    ->setStatus(OrganizationStatus::ACTIVE);
$em->persist($org);

$patron = (new User())
    ->setEmail('patron.coord.' . $suffix . '@test.local')
    ->setFullName('Patron Coordonnées')
    ->setPassword(password_hash('MotDePasse!123', PASSWORD_BCRYPT))
    ->setIsActive(true);
$em->persist($patron);

$em->persist(
    (new OrganizationUser())->setUser($patron)->setOrganization($org)->setRole(OrganizationRole::PATRON)
);

$city = (new City())
    ->setName('Ville Coordonnées')
    ->setCode('VC-' . $suffix)
    ->setCountry('CD')
    ->setStatus(CityStatus::ACTIVE)
    ->setOrganization($org);
$em->persist($city);

$em->flush();
$em->clear();

echo "  Jeu de données créé.\n";

[$status, $payload] = $request('POST', '/api/auth/login', [
    'email' => $patron->getEmail(),
    'password' => 'MotDePasse!123',
]);
$token = is_array($payload) ? ($payload['accessToken'] ?? null) : null;
if ($status !== 200 || !is_string($token)) {
    fwrite(STDERR, "[erreur] login patron -> {$status}\n");
    exit(1);
}

/**
 * Normalise un DECIMAL(10,7) restitué par MariaDB ('-0.6810000' -> '-0.681')
 * avant comparaison avec la valeur entrée.
 */
$normCoord = static function (string $v): string {
    return rtrim(rtrim($v, '0'), '.');
};

$cityUuid = $city->getUuid()->toRfc4122();

$basePayload = static function (string $reference) use ($cityUuid): array {
    return [
        'cityUuid' => $cityUuid,
        'reference' => $reference,
        'name' => 'Parcelle ' . $reference,
        'address' => 'Adresse ' . $reference,
        'area' => '1200.50',
    ];
};

// ---------------------------------------------------------------------
// 1. Optionnelles : création sans coordonnées
// ---------------------------------------------------------------------
section('Coordonnées optionnelles à la création');

$refNoCoords = 'REF-NC-' . $suffix;
[$status, $payload] = $request('POST', '/api/v1/parcels', $basePayload($refNoCoords), $token);

check('parcelle créée sans coordonnées (201)', $status === 201, "obtenu {$status}");
check(
    'latitude et longitude absentes du DTO de réponse',
    is_array($payload['data'] ?? null)
        && array_key_exists('latitude', $payload['data'])
        && $payload['data']['latitude'] === null
        && array_key_exists('longitude', $payload['data'])
        && $payload['data']['longitude'] === null,
    json_encode($payload['data'] ?? [], JSON_THROW_ON_ERROR)
);

// ---------------------------------------------------------------------
// 2. Création avec coordonnées soumises en nombre JSON
// ---------------------------------------------------------------------
section('Création avec coordonnées (nombre JSON)');

$refCoords = 'REF-C-' . $suffix;
[$status, $payload] = $request('POST', '/api/v1/parcels', $basePayload($refCoords) + [
    'latitude' => -0.681,
    'longitude' => 29.238,
], $token);

check('parcelle créée avec coordonnées (201)', $status === 201, "obtenu {$status}");

$data = is_array($payload) ? ($payload['data'] ?? []) : [];
check(
    'latitude persistée puis retournée (-0.681)',
    is_string($data['latitude'] ?? null) && $normCoord($data['latitude']) === '-0.681',
    json_encode($data['latitude'] ?? null, JSON_THROW_ON_ERROR)
);
check(
    'longitude persistée puis retournée (29.238)',
    is_string($data['longitude'] ?? null) && $normCoord($data['longitude']) === '29.238',
    json_encode($data['longitude'] ?? null, JSON_THROW_ON_ERROR)
);

$uuidCoords = is_array($data['id'] ?? null) ? null : ($data['id'] ?? null);
// ---------------------------------------------------------------------
// 3. Lecture : les coordonnées sont exposées par l'API
// ---------------------------------------------------------------------
section('Lecture des coordonnées');

[$status, $payload] = $request('GET', '/api/v1/parcels/' . $uuidCoords, null, $token);
$detail = is_array($payload) ? ($payload['data'] ?? []) : [];
check('GET parcelle avec coordonnées (200)', $status === 200, "obtenu {$status}");
check(
    'la lecture retourne les coordonnées enregistrées',
    is_string($detail['latitude'] ?? null)
        && $normCoord($detail['latitude']) === '-0.681'
        && is_string($detail['longitude'] ?? null)
        && $normCoord($detail['longitude']) === '29.238',
    json_encode($detail['latitude'] ?? null, JSON_THROW_ON_ERROR) . ' / '
        . json_encode($detail['longitude'] ?? null, JSON_THROW_ON_ERROR)
);

// ---------------------------------------------------------------------
// 4. Mise à jour : remplacement des coordonnées
// ---------------------------------------------------------------------
section('Mise à jour des coordonnées');

[$status, $payload] = $request('PUT', '/api/v1/parcels/' . $uuidCoords, $basePayload($refCoords) + [
    'latitude' => 1.5,
    'longitude' => 30.25,
], $token);
$updated = is_array($payload) ? ($payload['data'] ?? []) : [];
check('MISE À JOUR des coordonnées acceptée (200)', $status === 200, "obtenu {$status}");
check(
    'lat/long remplacées (1.5 / 30.25)',
    is_string($updated['latitude'] ?? null) && $normCoord($updated['latitude']) === '1.5'
        && is_string($updated['longitude'] ?? null) && $normCoord($updated['longitude']) === '30.25',
    json_encode($updated['latitude'] ?? null, JSON_THROW_ON_ERROR) . ' / '
        . json_encode($updated['longitude'] ?? null, JSON_THROW_ON_ERROR)
);

// ---------------------------------------------------------------------
// 5. Round-trip base de données (round-trip réel hors cache du noyau)
// ---------------------------------------------------------------------
section('Persistance réelle en base');

$row = $connection->fetchAssociative(
    'SELECT latitude, longitude FROM parcel WHERE uuid = ?',
    [\Symfony\Component\Uid\Uuid::fromString((string) $uuidCoords)->toBinary()]
);
$storedLat = is_array($row) ? (string) $row['latitude'] : '';
$storedLon = is_array($row) ? (string) $row['longitude'] : '';
check(
    'les coordonnées sont bien enregistrées en base',
    $normCoord($storedLat) === '1.5' && $normCoord($storedLon) === '30.25',
    "base = {$storedLat} / {$storedLon}"
);

// ---------------------------------------------------------------------
// 6. Indivisibilité : une coordonnée seule est refusée
// ---------------------------------------------------------------------
section('Rejet d\'une coordonnée isolée');

[$status] = $request('POST', '/api/v1/parcels', $basePayload('REF-LAT-' . $suffix) + [
    'latitude' => -0.681,
], $token);
check('latitude sans longitude refusée (422)', $status === 422, "obtenu {$status}");

[$status] = $request('POST', '/api/v1/parcels', $basePayload('REF-LON-' . $suffix) + [
    'longitude' => 29.238,
], $token);
check('longitude sans latitude refusée (422)', $status === 422, "obtenu {$status}");

// ---------------------------------------------------------------------
// 7. Bornes : hors plage ou non numérique
// ---------------------------------------------------------------------
section('Rejet hors plage et non numérique');

[$status] = $request('POST', '/api/v1/parcels', $basePayload('REF-LAT95-' . $suffix) + [
    'latitude' => 95,
    'longitude' => 29.238,
], $token);
check('latitude 95 (hors [-90, 90]) refusée (422)', $status === 422, "obtenu {$status}");

[$status] = $request('POST', '/api/v1/parcels', $basePayload('REF-LON181-' . $suffix) + [
    'latitude' => -0.681,
    'longitude' => 181,
], $token);
check('longitude 181 (hors [-180, 180]) refusée (422)', $status === 422, "obtenu {$status}");

[$status] = $request('POST', '/api/v1/parcels', $basePayload('REF-ABC-' . $suffix) + [
    'latitude' => 'abc',
    'longitude' => 29.238,
], $token);
check('latitude non numérique refusée (422)', $status === 422, "obtenu {$status}");

// ---------------------------------------------------------------------
// 8. Aucune des tentatives rejetées ne doit avoir persisté
// ---------------------------------------------------------------------
section('Intégrité : aucune écriture fantôme');

$count = (int) $connection->fetchOne(
    'SELECT COUNT(*) FROM parcel WHERE deleted_at IS NULL'
);
check(
    'seules les 2 parcelles valides existent en base',
    $count === 2,
    "compte = {$count}"
);

// ---------------------------------------------------------------------
echo "\nBilan : {$checks} contrôles, " . count($failures) . " échec(s).\n";

if (count($failures) > 0) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "  FAILED: {$failure}\n");
    }
    $kernel->shutdown();
    exit(1);
}

$kernel->shutdown();
exit(0);