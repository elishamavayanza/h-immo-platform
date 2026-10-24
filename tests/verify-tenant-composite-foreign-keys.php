<?php

declare(strict_types=1);

/**
 * Vérifie les clés composites tenant-scoped maintenues par les migrations
 * MariaDB, que Doctrine ORM ne représente pas comme associations composites.
 * Lecture seule; aucune écriture ni modification de schéma.
 */
$url = getenv('LISTING_TEST_DATABASE_URL');
if ($url === false || $url === '') {
    fwrite(STDERR, "Définissez LISTING_TEST_DATABASE_URL vers gestion_himmo_security_test.\n");
    exit(2);
}

$parts = parse_url($url);
$database = ltrim((string) ($parts['path'] ?? ''), '/');
$host = (string) ($parts['host'] ?? '');
$port = (int) ($parts['port'] ?? 3306);
if ($database !== 'gestion_himmo_security_test' || !in_array($host, ['127.0.0.1', 'localhost'], true)) {
    fwrite(STDERR, "Refus : ce contrôle accepte uniquement la base locale gestion_himmo_security_test.\n");
    exit(2);
}

printf("Cible en lecture seule : host=%s port=%d database=%s credentials=redacted\n", $host, $port, $database);
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database),
    rawurldecode((string) ($parts['user'] ?? '')),
    rawurldecode((string) ($parts['pass'] ?? '')),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'gestion_himmo_security_test') {
    fwrite(STDERR, "Refus : la connexion effective n’est pas la base de test autorisée.\n");
    exit(2);
}

$expected = [
    'FK_RULE_DRAFT_VERSION_ORG' => ['organization_rental_rule_draft', 'published_version_id,organization_id', 'organization_rental_rule_version', 'id,organization_id'],
    'FK_RENT_LEASE_ORG' => ['rent', 'lease_id,organization_id', 'lease', 'id,organization_id'],
    'FK_RENT_RULE_VERSION_ORG' => ['rent', 'rule_version_id,organization_id', 'organization_rental_rule_version', 'id,organization_id'],
    'FK_RULE_DELEGATION_RECIPIENT_MEMBERSHIP' => ['rental_rule_publication_delegation', 'organization_id,user_id,recipient_role', 'organization_user', 'organization_id,user_id,role'],
    'FK_RULE_DELEGATION_GRANTOR_MEMBERSHIP' => ['rental_rule_publication_delegation', 'organization_id,granted_by_id,grantor_role', 'organization_user', 'organization_id,user_id,role'],
    'FK_RENT_RULE_SNAPSHOT_RENT' => ['rent_rule_snapshot', 'rent_id,organization_id', 'rent', 'id,organization_id'],
    'FK_RENT_RULE_SNAPSHOT_RENT_VERSION' => ['rent_rule_snapshot', 'rent_id,organization_id,rule_version_id', 'rent', 'id,organization_id,rule_version_id'],
];

$rows = $pdo->query(
    "SELECT TABLE_NAME, CONSTRAINT_NAME,
            GROUP_CONCAT(COLUMN_NAME ORDER BY ORDINAL_POSITION SEPARATOR ',') AS source_columns,
            REFERENCED_TABLE_NAME,
            GROUP_CONCAT(REFERENCED_COLUMN_NAME ORDER BY ORDINAL_POSITION SEPARATOR ',') AS target_columns
     FROM information_schema.KEY_COLUMN_USAGE
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
     GROUP BY TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME"
)->fetchAll(PDO::FETCH_ASSOC);

$actual = [];
foreach ($rows as $row) {
    $actual[$row['CONSTRAINT_NAME']] = [
        $row['TABLE_NAME'],
        $row['source_columns'],
        $row['REFERENCED_TABLE_NAME'],
        $row['target_columns'],
    ];
}

$failures = [];
foreach ($expected as $name => $definition) {
    $passed = ($actual[$name] ?? null) === $definition;
    printf("[%s] %s%s\n", $passed ? 'OK' : 'ÉCHEC', $name, $passed ? '' : ' — clé absente ou définition différente');
    if (!$passed) {
        $failures[] = $name;
    }
}

printf("Contrôles exécutés : %d; échecs : %d.\n", count($expected), count($failures));
exit($failures === [] ? 0 : 1);
