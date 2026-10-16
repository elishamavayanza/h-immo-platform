<?php

declare(strict_types=1);

/**
 * Vérifie le chargement, l'idempotence et le dry-run sur une base dédiée.
 * Le jeu est créé dans une transaction externe puis intégralement annulé.
 * Exécuter uniquement avec DATABASE_URL pointant vers une base *_test/_dev/_demo.
 */

use App\Entity\System\DemoDataset;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

require dirname(__DIR__) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
$explicitTestDatabaseUrl = getenv('DEMO_TEST_DATABASE_URL');
if (is_string($explicitTestDatabaseUrl) && $explicitTestDatabaseUrl !== '') {
    putenv('DATABASE_URL=' . $explicitTestDatabaseUrl);
    $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $explicitTestDatabaseUrl;
}

$kernel = new App\Kernel('dev', true);
$kernel->boot();
$entityManager = $kernel->getContainer()->get('doctrine')->getManager();
$connection = $entityManager->getConnection();
$database = (string) $connection->fetchOne('SELECT DATABASE()');
if (!preg_match('/(?:_dev|_test|_demo)$/i', $database)) {
    fwrite(STDERR, sprintf("REFUS : la base « %s » doit se terminer par _dev, _test ou _demo.\n", $database));
    exit(2);
}

$seed = '20991017';
foreach ([['seed' => $seed, 'scenario' => 'small'], ['seed' => '20991019', 'scenario' => 'complete'], ['seed' => '20991018', 'scenario' => 'complete']] as $reservedSeed) {
    if ($entityManager->getRepository(DemoDataset::class)->findOneBy($reservedSeed) instanceof DemoDataset) {
        fwrite(STDERR, "REFUS : une seed réservée à ce test existe déjà ; aucune donnée n'a été modifiée.\n");
        exit(2);
    }
}

$checks = 0;
$failures = [];
$check = static function (string $label, bool $passed) use (&$checks, &$failures): void {
    ++$checks;
    echo sprintf("  [%s] %s\n", $passed ? 'OK' : 'FAIL', $label);
    if (!$passed) {
        $failures[] = $label;
    }
};

$connection->beginTransaction();
try {
    $application = new Application($kernel);
    $command = $application->find('app:demo:load');
    $run = static function (array $options) use ($command): array {
        $tester = new CommandTester($command);
        $status = $tester->execute($options, ['interactive' => false]);
        return [$status, $tester->getDisplay()];
    };

    [$status, $display] = $run(['--scenario' => 'small', '--seed' => $seed]);
    $counts = $connection->fetchAssociative(<<<'SQL'
SELECT COUNT(DISTINCT c.id) AS cities,
       COUNT(DISTINCT p.id) AS parcels,
       COUNT(DISTINCT b.id) AS buildings,
       COUNT(DISTINCT u.id) AS units,
       COUNT(DISTINCT t.id) AS tenants
FROM demo_dataset d
JOIN city c ON c.organization_id = d.organization_id
JOIN parcel p ON p.city_id = c.id
JOIN building b ON b.parcel_id = p.id
JOIN unit u ON u.building_id = b.id
JOIN tenant t ON t.organization_id = d.organization_id
WHERE d.seed = :seed AND d.scenario = 'small'
SQL, ['seed' => $seed]);
    $check('le scénario small est généré dans la transaction', $status === 0 && str_contains($display, 'Jeu small créé'));
    $check('les relations et volumes small sont cohérents', $counts === ['cities' => 2, 'parcels' => 2, 'buildings' => 2, 'units' => 4, 'tenants' => 2]);

    [$repeatStatus, $repeatDisplay] = $run(['--scenario' => 'small', '--seed' => $seed]);
    $repeatCount = (int) $connection->fetchOne("SELECT COUNT(*) FROM demo_dataset WHERE seed = :seed AND scenario = 'small'", ['seed' => $seed]);
    $check('la seconde exécution est idempotente', $repeatStatus === 0 && str_contains($repeatDisplay, 'déjà enregistré') && $repeatCount === 1);

    [$completeStatus, $completeDisplay] = $run(['--scenario' => 'complete', '--seed' => '20991019']);
    $completeCounts = $connection->fetchAssociative(<<<'SQL'
SELECT COUNT(DISTINCT c.id) AS cities,
       COUNT(DISTINCT p.id) AS parcels,
       COUNT(DISTINCT b.id) AS buildings,
       COUNT(DISTINCT u.id) AS units,
       COUNT(DISTINCT t.id) AS tenants
FROM demo_dataset d
JOIN city c ON c.organization_id = d.organization_id
JOIN parcel p ON p.city_id = c.id
JOIN building b ON b.parcel_id = p.id
JOIN unit u ON u.building_id = b.id
JOIN tenant t ON t.organization_id = d.organization_id
WHERE d.seed = '20991019' AND d.scenario = 'complete'
SQL);
    $check('le scénario complete est généré avec ses volumes attendus', $completeStatus === 0 && str_contains($completeDisplay, 'Jeu complete créé') && $completeCounts === ['cities' => 4, 'parcels' => 4, 'buildings' => 4, 'units' => 16, 'tenants' => 8]);

    [$dryStatus] = $run(['--scenario' => 'complete', '--seed' => '20991018', '--dry-run' => true]);
    $dryCount = (int) $connection->fetchOne("SELECT COUNT(*) FROM demo_dataset WHERE seed = '20991018'");
    $check('le dry-run ne persiste aucune donnée', $dryStatus === 0 && $dryCount === 0);
} finally {
    if ($connection->isTransactionActive()) {
        $connection->rollBack();
    }
    $entityManager->clear();
    $kernel->shutdown();
}

echo sprintf("\n%d contrôles ; %d échec(s). Les écritures de test ont été annulées.\n", $checks, count($failures));
exit($failures === [] ? 0 : 1);
