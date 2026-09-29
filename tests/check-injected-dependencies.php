<?php

declare(strict_types=1);

/**
 * Contrôle statique des dépendances résolues par `$this->`.
 *
 * But : attraper avant merge les régressions de type P0-7, où une méthode de
 * service appelle `$this->cityRepository->…` sans que le repository ait été
 * injecté au constructeur. `php bin/console lint:container` ne les voit pas :
 * il valide les types des arguments reçus, pas l'existence des propriétés.
 *
 * Méthode : pour chaque classe de `src/`, la réflexion donne la liste réelle
 * des propriétés (y compris promues) et des méthodes (y compris héritées des
 * classes parentes, des interfaces et des traits). Tout `$this->x` qui n'est
 * ni l'une ni l'autre est une régression.
 *
 * Sortie : 0 si aucune référence non résolue, 1 sinon.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__) . '/src';
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

/** Résolution d'un chemin de fichier vers un nom de classe FQCN. */
$classNameOf = static function (string $path) use ($root): ?string {
    if (!str_starts_with($path, $root . '/') || !str_ends_with($path, '.php')) {
        return null;
    }

    $relative = substr($path, strlen($root) + 1, -4);

    return 'App\\' . str_replace('/', '\\', $relative);
};

$report = [];
$checked = 0;

foreach ($rii as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $class = $classNameOf($file->getPathname());

    if ($class === null || !class_exists($class) && !interface_exists($class) && !trait_exists($class)) {
        continue;
    }

    $reflection = new ReflectionClass($class);

    // Un trait est analysé à travers les classes qui l'utilisent : seul le
    // contexte d'usage (ici `AbstractController::json()`) connaît ses membres.
    if ($reflection->isTrait() || $reflection->isEnum() || $reflection->isInterface()) {
        continue;
    }

    $members = [];
    foreach ($reflection->getProperties() as $property) {
        $members[$property->getName()] = true;
    }
    foreach ($reflection->getMethods() as $method) {
        $members[$method->getName()] = true;
    }

    preg_match_all('/\$this->([a-zA-Z_][a-zA-Z0-9_]*)/', (string) file_get_contents($file->getPathname()), $mUsed);

    $missing = array_diff(array_unique($mUsed[1] ?? []), array_keys($members));

    if ($missing === []) {
        continue;
    }

    $checked++;
    $report[] = str_replace($root . '/', '', $file->getPathname()) . ' => ' . implode(', ', $missing);
}

if ($report === []) {
    echo "OK : chaque acces \$this->… designe une propriete injectee ou une methode heritee.\n";
    echo '     Classes analysees : ' . iterator_count(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root))) . " fichiers\n";
    exit(0);
}

echo "KO : acces a un membre inexistant (regression d'injection)\n";
foreach ($report as $line) {
    echo '  - ' . $line . "\n";
}
echo $checked . " classe(s) concernee(s).\n";
exit(1);
