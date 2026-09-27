<?php

declare(strict_types=1);

/**
 * Vérifie que la documentation OpenAPI se génère réellement.
 *
 * Un attribut `#[OA\...]` invalide ne fait pas échouer le lint PHP, ni
 * `lint:container`, ni les tests métier : il n'explose que le moment où
 * Nelmio parcourt les contrôleurs. Une seule référence vers une classe
 * supprimée (par exemple `Nelmio\ApiDocBundle\Annotation\Model`, renommée
 * en `...\Attribute\Model`) suffisait à rendre l'API entière — 31
 * routes — ingénérable, sans qu'aucun test ne le remarque.
 *
 * La requête passe par le vrai noyau HTTP, comme le ferait un visiteur de
 * `/api/doc.json`.
 *
 *   php .opencode/verify-api-doc.php
 */

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

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

    $failures[] = $label;
    echo "  [FAIL] {$label}" . ($detail !== '' ? " -> {$detail}" : '') . "\n";
}

final class DocKernel extends App\Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                if ($container->hasDefinition('nelmio_api_doc.generator.default')) {
                    $container->getDefinition('nelmio_api_doc.generator.default')->setPublic(true);
                }
            }
        });
    }
}

$kernel = new DocKernel('dev', true);
$kernel->boot();

// Génération par le service, comme le fait la route : c'est le chemin
// qui échouait, pas le parsing des fichiers.
// Une référence d'attribut invalide n'échoue qu'ici, au moment où Nelmio
// parcourt les contrôleurs. L'erreur est interceptée pour être rendue
// lisible, sinon le harnais meurt sur une erreur PHP brute.
try {
    $document = $kernel->getContainer()->get('nelmio_api_doc.generator.default')->generate();
    $json = json_encode($document, JSON_THROW_ON_ERROR);
    $erreur = null;
} catch (Throwable $e) {
    $json = '{}';
    $erreur = $e->getMessage();
}

check(
    'Le générateur Nelmio produit un document sérialisable',
    $erreur === null && $json !== '' && $json !== '{}',
    (string) $erreur
);

if ($erreur !== null) {
    echo "\n  Contrôles exécutés : {$checks}\n";
    echo '  Échecs : ' . count($failures) . "\n";
    $kernel->shutdown();

    exit(1);
}

$decoded = json_decode($json, true);

check(
    'Le document est un JSON valide (OpenAPI)',
    is_array($decoded) && isset($decoded['openapi'], $decoded['paths']),
    'clé openapi absente'
);

$paths = $decoded['paths'] ?? [];
check('Le document expose des routes', count($paths) > 0, 'aucune route documentée');

// Une référence de schéma non résolue produit un `#/components/schemas/`
// orphelin : le JSON reste valide, la documentation est simplement fausse.
$refs = [];
preg_match_all('~"\$ref"\s*:\s*"#/components/schemas/([^"]+)"~', $json, $refs);
$refs = array_unique($refs[1]);
$schemas = $decoded['components']['schemas'] ?? [];
$manquants = array_values(array_filter($refs, static fn ($r) => !isset($schemas[$r])));

check(
    'Toutes les références de schéma pointent une entrée existante',
    $manquants === [],
    'schémas manquants : ' . implode(', ', $manquants)
);

// Les trois routes d'authentification doivent rester documentées : ce sont
// celles qu'un client consomme en premier.
foreach (['/api/auth/login', '/api/auth/logout', '/api/auth/me'] as $route) {
    check("La route {$route} est documentée", isset($paths[$route]), 'route absente du document');
}

// Un groupe de sérialisation absent produirait un schéma de requête vide
// pour les DTO d'écriture.
$parcels = $paths['/api/v1/parcels'] ?? [];
$post = $parcels['post'] ?? [];
$ref = $post['requestBody']['content']['application/json']['schema']['$ref'] ?? null;
check(
    'La création de parc expose un schéma de requête résolu',
    is_string($ref) && isset($decoded['components']['schemas'][basename(str_replace('#/components/schemas/', '', $ref))]),
    'requestBody : ' . json_encode($post['requestBody'] ?? null)
);

echo "\n  Contrôles exécutés : {$checks}\n";
echo '  Échecs : ' . count($failures) . "\n";

$kernel->shutdown();

exit($failures === [] ? 0 : 1);
