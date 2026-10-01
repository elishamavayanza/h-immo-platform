<?php

declare(strict_types=1);

/**
 * La purge des révocations ne doit supprimer que l'inutile, jamais l'efficace.
 *
 * `RevokedTokenRepository::purgeExpired()` existait, était documentée, et
 * n'était appelée nulle part : `revoked_token` s'alimentait à chaque
 * déconnexion sans jamais se vider. Cette commande comble l'oubli, mais le
 * risque n'est pas de trop purger une table — c'est de trop peu, c'est-à-dire
 * de supprimer la révocation d'un jeton encore valide et de le rendre
 * utilisable jusqu'à son échéance naturelle.
 *
 * La suite vérifie donc les deux moitiés : ce qui doit disparaître disparaît,
 * ce qui protège quelqu'un reste en place et continue de bloquer le jeton.
 *
 *   php tests/verify-revoked-token-purge.php
 */

use App\Entity\Identity\RevokedToken;
use App\Entity\Identity\User;
use App\Service\System\DateTimeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;

ob_start();

require dirname(__DIR__) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

$_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = 'null://null';
putenv('MAILER_DSN=null://null');

final class PurgeKernel extends App\Kernel
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

$kernel = new PurgeKernel('dev', false);
$kernel->boot();
$container = $kernel->getContainer();

/** @var EntityManagerInterface $em */
$em = $container->get('doctrine')->getManager();
// Le service est instancié directement comme le fait
// `verify-datetime-service.php` : il n'a pas de dépendance et le conteneur
// l'a inliné (il n'est donc pas récupérable via `$container->get()`).
$dateTime = new DateTimeService();
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
$connection->beginTransaction();
$connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

foreach (array_reverse($tables) as $table) {
    if (!in_array($table, ['doctrine_migration_versions', 'migration_versions'], true)) {
        $connection->executeStatement('DELETE FROM `' . $table . '`');
    }
}

$connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

$suffix = 'purge';
$plainPassword = 'MotDePasse!123';

$rollback = static function () use ($connection): void {
    if ($connection->isTransactionActive()) {
        $connection->rollBack();
    }

    echo "\n[info] Annulation de la transaction\n";
};

$application = new Application($kernel);
$application->setAutoExit(false);

$runCommand = static function (array $arguments) use ($application): array {
    $output = new BufferedOutput();
    $exitCode = $application->run(new ArrayInput($arguments), $output);

    return [$exitCode, $output->fetch()];
};

$revokedCount = static fn (): int => (int) $connection->fetchOne('SELECT COUNT(*) FROM revoked_token');
$revokedJtis = static fn (): array => $connection->fetchFirstColumn('SELECT jti FROM revoked_token');

// ---------------------------------------------------------------------
// Jeu de données : un compte, une révocation valide, deux révocations périmées
// ---------------------------------------------------------------------
section('Construction : révocations valides et périmées');

$user = (new User())
    ->setEmail('user.' . $suffix . '@test.local')
    ->setFullName('Utilisateur Purge')
    ->setPassword(password_hash($plainPassword, PASSWORD_BCRYPT))
    ->setIsActive(true);
$em->persist($user);
$em->flush();

[$status, $payload] = $request('POST', '/api/auth/login', [
    'email' => $user->getEmail(),
    'password' => $plainPassword,
]);
$token = $payload['accessToken'] ?? null;

check('connexion réussie', $status === 200 && is_string($token), "obtenu {$status}");

if (!is_string($token)) {
    $rollback();
    echo "\nECHECS : " . count($failures) . " sur {$checks}\n";
    exit(1);
}

// Déconnexion : crée une révocation avec une échéance encore dans le futur.
[$status] = $request('POST', '/api/auth/logout', null, $token);
check('la déconnexion est acceptée', $status === 200, "obtenu {$status}");
check('une révocation a été enregistrée', $revokedCount() === 1, 'count=' . $revokedCount());

// Le jeton révoqué est déjà refusé.
[$status] = $request('GET', '/api/v1/identity/users/me', null, $token);
check('le jeton révoqué est refusé avant purge', $status === 401 || $status === 403, "obtenu {$status}");

// Deux révocations déjà expirées : sans effet, `JWT::decode` les rejetterait.
foreach (['perime-1', 'perime-2'] as $jti) {
    $em->persist(new RevokedToken($jti, $dateTime->now()->modify('-2 hours')));
}

$em->flush();
check('la table contient 3 révocations', $revokedCount() === 3, 'count=' . $revokedCount());

// ---------------------------------------------------------------------
// 1. --dry-run ne purge rien
// ---------------------------------------------------------------------
section('Le mode --dry-run reste en lecture seule');

[$exitCode, $output] = $runCommand(['command' => 'app:tokens:purge-revoked', '--dry-run' => true]);

check('la commande se termine correctement', $exitCode === 0, "code={$exitCode} : " . trim($output));
check('le dry-run annonce 2 lignes candidates', str_contains($output, '2'), trim($output));
check('le dry-run ne supprime rien', $revokedCount() === 3, 'count=' . $revokedCount());

// ---------------------------------------------------------------------
// 2. La purge ne touche que les lignes périmées
// ---------------------------------------------------------------------
section('La purge ne supprime que les lignes périmées');

[$exitCode, $output] = $runCommand(['command' => 'app:tokens:purge-revoked']);

check('la commande se termine correctement', $exitCode === 0, "code={$exitCode} : " . trim($output));
check('les 2 lignes périmées ont été supprimées', $revokedCount() === 1, 'count=' . $revokedCount());
check('la ligne périmée perime-1 a disparu', !in_array('perime-1', $revokedJtis(), true));
check('la ligne périmée perime-2 a disparu', !in_array('perime-2', $revokedJtis(), true));

// ---------------------------------------------------------------------
// 3. Propriété de sécurité : la révocation utile a survécu
// ---------------------------------------------------------------------
section('La révocation d\'un jeton encore valide survit à la purge');

check('il reste exactement 1 révocation', $revokedCount() === 1, 'count=' . $revokedCount());

[$status] = $request('GET', '/api/v1/identity/users/me', null, $token);
check(
    'le jeton révoqué est toujours refusé après purge',
    $status === 401 || $status === 403,
    "obtenu {$status} : un jeton révoqué redevenu valide serait un 200"
);

// ---------------------------------------------------------------------
// 4. Idempotence
// ---------------------------------------------------------------------
section('Idempotence');

[$exitCode, $output] = $runCommand(['command' => 'app:tokens:purge-revoked']);
check('une seconde exécution réussit', $exitCode === 0, "code={$exitCode}");
check('elle ne supprime rien de plus', $revokedCount() === 1, 'count=' . $revokedCount());
check('le message signale l\'absence de candidates', str_contains($output, 'Aucune'), trim($output));

// ---------------------------------------------------------------------
// 5. Table vide
// ---------------------------------------------------------------------
section('Table vide');

$connection->executeStatement('DELETE FROM revoked_token');
[$exitCode, $output] = $runCommand(['command' => 'app:tokens:purge-revoked']);
check('la commande gère une table vide', $exitCode === 0, "code={$exitCode} : " . trim($output));
check('le message est explicite', str_contains($output, 'Aucune'), trim($output));

$rollback();

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