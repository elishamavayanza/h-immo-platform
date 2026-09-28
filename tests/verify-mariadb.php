<?php

declare(strict_types=1);

use App\Repository\Expense\ExpenseRepository;
use App\Repository\Rental\PaymentRepository;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

/**
 * Vérifie que le projet tourne bien sur MariaDB, et qu'aucune trace de
 * PostgreSQL ne subsiste.
 *
 * Ce contrôle existe parce que la configuration versionnée a longtemps
 * déclaré PostgreSQL alors que les migrations écrivaient une syntaxe
 * MySQL/MariaDB. L'écart ne se voyait pas en local, où `.env.local`
 * pointait déjà sur MariaDB, et il a fait échouer la première migration
 * d'une base neuve.
 */
final class MariaDbKernel extends App\Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach ([ExpenseRepository::class, PaymentRepository::class] as $id) {
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

    $failures[] = $label.($detail !== '' ? " ({$detail})" : '');
    echo "  [FAIL] {$label}".($detail !== '' ? " -> {$detail}" : '')."\n";
}

$kernel = new MariaDbKernel('dev', false);
$kernel->boot();
$container = $kernel->getContainer();

$doctrine = $container->get('doctrine');
$connection = $doctrine->getConnection();
$platform = $connection->getDatabasePlatform();

echo "== Cible SGBD ==\n";

check(
    'la plateforme DBAL est MariaDB',
    str_contains($platform::class, 'MariaDB'),
    $platform::class,
);

$version = (string) $connection->fetchOne('SELECT VERSION()');
check('le serveur est bien MariaDB', str_contains($version, 'MariaDB'), $version);

$charset = (string) $connection->fetchOne(
    "SELECT @@character_set_database, @@collation_database",
);
check(
    'la base est en utf8mb4',
    str_starts_with($charset, 'utf8mb4'),
    $charset,
);

echo "\n== Configuration versionnée ==\n";

$files = [
    'compose.yaml',
    'compose.override.yaml',
    '.env',
    'config/packages/doctrine.yaml',
];

foreach ($files as $file) {
    $contents = (string) file_get_contents(dirname(__DIR__).'/'.$file);

    // Les commentaires expliquent pourquoi PostgreSQL a été retiré : on
    // ignore les lignes commentées, sinon le test échouerait sur sa
    // propre documentation.
    $code = implode("\n", array_filter(
        explode("\n", $contents),
        static fn (string $line): bool => !str_starts_with(ltrim($line), '#'),
    ));

    check(
        "{$file} ne déclare pas PostgreSQL",
        !preg_match('/postgres|PostgreSQL|POSTGRES|pgsql|5432/i', $code),
    );
}

echo "\n== Cohérence du schéma ==\n";

$sm = $connection->createSchemaManager();
$tables = $sm->listTableNames();

$expected = [
    'audit_log', 'building', 'city', 'expense', 'lease', 'organization',
    'organization_user', 'parcel', 'password_reset_token', 'payment', 'rent',
    'revoked_token', 'tenant', 'unit', 'user', 'user_city', 'worker',
    'worker_assignment',
];

$missing = array_values(array_diff($expected, $tables));
check('toutes les tables attendues existent', $missing === [], implode(', ', $missing));

check('la table revoked_token existe', in_array('revoked_token', $tables, true));

$migrations = $sm->listTableNames();
$metaTable = in_array('doctrine_migration_versions', $migrations, true);
check('la table des versions de migration existe', $metaTable);

if ($metaTable) {
    $applied = $connection->fetchFirstColumn(
        'SELECT version FROM doctrine_migration_versions ORDER BY version',
    );

    // Une version enregistrée sans fichier correspondant rend l'historique
    // ambigu : Doctrine la signale « migrated, not available » et ne
    // rejoue jamais le schéma correspondant sur une base neuve.
    $available = array_map(
        static fn (string $version): string => $version,
        $applied,
    );

    $known = [];
    foreach (glob(dirname(__DIR__).'/migrations/Version*.php') ?: [] as $file) {
        if (preg_match('/final class (Version\d+)/', (string) file_get_contents($file), $m) === 1) {
            $known[] = 'DoctrineMigrations\\'.$m[1];
        }
    }

    $orphans = array_values(array_diff($available, $known));
    check(
        'aucune version de migration orpheline',
        $orphans === [],
        implode(', ', $orphans),
    );
}

echo "\n== Requêtes de rapport ==\n";

$expenses = $container->get(ExpenseRepository::class);
$payments = $container->get(PaymentRepository::class);

// `DATE_FORMAT` n'appartient pas au DQL : sans fonction personnalisée, ces
// agrégats échouent au parsing avec « Expected known function ». Ils
// n'étaient couverts par aucun test, ce qui a laissé le bug passer.
foreach ([
    'ExpenseRepository::getFinancialSummary' => static fn (): array => $expenses->getFinancialSummary([1]),
    'PaymentRepository::getFinancialSummary' => static fn (): array => $payments->getFinancialSummary([1]),
] as $label => $query) {
    try {
        $rows = $query();
        check("{$label} s'exécute", true, count($rows).' lignes');
    } catch (Throwable $e) {
        check("{$label} s'exécute", false, $e->getMessage());
    }
}

echo "\n------------------------------------------------------------\n";

if ([] === $failures) {
    echo "SUCCES : {$checks} contrôles passés\n";
    exit(0);
}

echo 'ECHECS : '.count($failures)." sur {$checks}\n";
foreach ($failures as $failure) {
    echo "  - {$failure}\n";
}
exit(1);
