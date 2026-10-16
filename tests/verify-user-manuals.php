<?php

declare(strict_types=1);

/**
 * Vérifie l’intégrité des guides publiés et des pages servies au lecteur.
 * Les contrôles d’accès HTTP par rôle sont effectués avec comptes de test dédiés.
 */
$root = dirname(__DIR__);
$catalogPath = $root.'/docs/user-manuals/catalog.json';
$catalog = json_decode((string) file_get_contents($catalogPath), true);
$expectedRoles = ['super_admin', 'patron', 'admin_immobilier', 'admin_ville'];
$failures = [];
$checks = 0;

$check = static function (bool $condition, string $label) use (&$checks, &$failures): void {
    ++$checks;
    if (!$condition) {
        $failures[] = $label;
        echo "[FAIL] {$label}\n";

        return;
    }

    echo "[OK] {$label}\n";
};

$check(is_array($catalog), 'Le catalogue JSON est valide');
if (!is_array($catalog)) {
    exit(1);
}
$check(array_keys($catalog['roles'] ?? []) === $expectedRoles, 'Le catalogue ne contient que les quatre rôles implémentés');
$check(isset($catalog['version'], $catalog['updatedAt']), 'La version et la date du catalogue sont renseignées');

foreach ($expectedRoles as $role) {
    $manual = $catalog['roles'][$role] ?? null;
    $check(is_array($manual), "Le rôle {$role} possède un manuel");
    if (!is_array($manual)) {
        continue;
    }

    $filename = basename((string) ($manual['filename'] ?? ''));
    $pdfPath = $root.'/output/pdf/'.$filename;
    $pdf = is_file($pdfPath) ? file_get_contents($pdfPath) : false;
    $check(is_string($pdf) && str_starts_with($pdf, '%PDF-'), "Le PDF {$role} existe et possède une signature valide");
    $pageCount = (int) ($manual['pageCount'] ?? 0);
    $check($pageCount > 0, "Le nombre de pages de {$role} est défini");

    $modules = $manual['modules'] ?? [];
    $check(is_array($modules) && count($modules) > 0, "Les modules {$role} sont documentés");
    $articleIds = [];
    foreach (is_array($modules) ? $modules : [] as $module) {
        $articleIds[] = $module['id'] ?? '';
        $check(
            isset($module['id'], $module['name'], $module['menu'], $module['goal'], $module['steps'], $module['image'])
            && is_array($module['steps']) && count($module['steps']) > 0,
            "La procédure du module ".($module['name'] ?? 'inconnu')." est complète",
        );
        $check(strlen(trim((string) ($module['goal'] ?? ''))) >= 20 && strlen(trim((string) ($module['steps'][0] ?? ''))) >= 12, "Le contenu du module ".($module['name'] ?? 'inconnu')." est exploitable dans le lecteur web");
        $check(is_file($root.'/output/pdf/screenshots/'.basename((string) ($module['image'] ?? ''))), "La capture du module ".($module['name'] ?? 'inconnu')." existe");
    }
    $check(count($articleIds) === count(array_unique($articleIds)) && !in_array('', $articleIds, true), "Les articles {$role} ont des identifiants stables et uniques pour leur navigation URL");

    for ($page = 1; $page <= $pageCount; ++$page) {
        $pagePath = sprintf('%s/output/pdf/manual-pages/%s/page-%03d.jpg', $root, $role, $page);
        $bytes = is_file($pagePath) ? file_get_contents($pagePath) : false;
        $check(is_string($bytes) && str_starts_with($bytes, "\xFF\xD8"), "La page lecteur {$role} {$page} est un JPEG valide");
    }
}

echo sprintf("%d vérifications, %d échec(s).\n", $checks, count($failures));
exit($failures === [] ? 0 : 1);
