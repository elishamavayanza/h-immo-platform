<?php

declare(strict_types=1);

/*
 * Contre-écritures — annulation de paiement et de dépense.
 *
 * Le code historique créait, pour annuler une écriture, une NOUVELLE ligne de
 * montant POSITIF portant la référence `ANNUL-<réf>`. Comme tous les agrégats
 * font `SUM(amount)`, une annulation s'ADDITIONNAIT au lieu de se soustraire :
 * les totaux, les impayés et les statuts d'échéance étaient faux. Un loyer
 * soldé le restait même après annulation (« le statut ne descend jamais »).
 *
 * Ce harnais vérifie le modèle corrigé : la contre-écriture porte un montant
 * NÉGATIF et est reliée à l'originale par `reversal_of_id` (UNIQUE). Il exerce
 * les vraies routes HTTP (pare-feu, binding DTO, DI, transactions verrouillées)
 * sur une base `dev`, et annule sa transaction en fin de script.
 *
 * Prérequis : la migration Version20261012095821 doit être appliquée (colonnes
 * `reversal_of_id`, index UNIQUE, FK, contraintes CHECK). Le script vérifie ces
 * artefacts du schéma dans sa dernière section.
 *
 * Usage : php tests/verify-contre-ecritures.php
 */

use App\Entity\Expense\Expense;
use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationUser;
use App\Entity\Identity\User;
use App\Entity\Identity\UserCity;
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
use App\Enum\ExpenseCategory;
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

/** Expose `doctrine` (privé, donc inliné) le temps du harnais. */
final class ContreEcrituresKernel extends App\Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach ($container->getDefinitions() as $id => $definition) {
                    if (in_array($id, ['doctrine', 'security.token_storage'], true)) {
                        $definition->setPublic(true);
                    }
                }
            }
        });
    }
}

$kernel = new ContreEcrituresKernel('dev', true);
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

$suffix = 'ce' . bin2hex(random_bytes(3));
$password = 'MotDePasse!123';

// ---------------------------------------------------------------------
section('Jeu de données : org A (PATRON + ADMIN_VILLE), org B, patrimoine, baux');

$makeUser = static function (string $email, string $name) use ($password): User {
    return (new User())
        ->setEmail($email)
        ->setFullName($name)
        ->setPassword(password_hash($password, PASSWORD_BCRYPT))
        ->setIsActive(true);
};

$patronA = $makeUser('patron.a.' . $suffix . '@test.local', 'Patron A');
$adminVilleA = $makeUser('ville.a.' . $suffix . '@test.local', 'Admin Ville A');
$patronB = $makeUser('patron.b.' . $suffix . '@test.local', 'Patron B');
foreach ([$patronA, $adminVilleA, $patronB] as $u) {
    $em->persist($u);
}

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
$em->persist((new OrganizationUser())->setUser($adminVilleA)->setOrganization($orgA)->setRole(OrganizationRole::ADMIN_VILLE));
$em->persist((new OrganizationUser())->setUser($patronB)->setOrganization($orgB)->setRole(OrganizationRole::PATRON));

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

$em->persist((new UserCity())->setUser($adminVilleA)->setCity($cityA));

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

/** Un bail actif et une échéance due dans le passé (donc « en retard » si impayée). */
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

// Un paiement sur le bail de B, pour éprouver l'isolation multi-organisation.
$paymentB = (new Payment())
    ->setRent($rentB)
    ->setAmount('100.00')
    ->setCurrency(Currency::USD)
    ->setPaymentDate(new \DateTimeImmutable('2026-09-01'))
    ->setCreatedBy($patronB)
    ->setMethod(PaymentMethod::CASH)
    ->setReference('PAY-B');
$em->persist($paymentB);

// Deux dépenses dans la ville A : une en USD, une en CDF (devises séparées).
$makeExpense = static function (string $ref, string $amount, Currency $currency) use ($em, $orgA, $cityA, $patronA): Expense {
    $expense = (new Expense())
        ->setOrganization($orgA)
        ->setCity($cityA)
        ->setCreatedBy($patronA)
        ->setCategory(ExpenseCategory::MAINTENANCE)
        ->setAmount($amount)
        ->setCurrency($currency)
        ->setExpenseDate(new \DateTimeImmutable('2026-09-10'))
        ->setReference($ref);
    $em->persist($expense);

    return $expense;
};

$expenseUsd = $makeExpense('DEP-USD', '100.00', Currency::USD);
$expenseCdf = $makeExpense('DEP-CDF', '1000.00', Currency::CDF);

$em->flush();

// Captures avant détachement.
$rentAId = $rentA->getId();
$rentAUuid = $rentA->getUuid()->toRfc4122();
$rentBUuid = $rentB->getUuid()->toRfc4122();
$cityAId = $cityA->getId();
$expenseUsdUuid = $expenseUsd->getUuid()->toRfc4122();
$expenseCdfUuid = $expenseCdf->getUuid()->toRfc4122();
$paymentBUuid = $paymentB->getUuid()->toRfc4122();

$em->clear();

$login = static function (string $email) use ($request, $password): ?string {
    [$status, $payload] = $request('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);

    return $status === 200 && is_array($payload) && is_string($payload['accessToken'] ?? null)
        ? $payload['accessToken']
        : null;
};

$patronAToken = $login('patron.a.' . $suffix . '@test.local');
$villeAToken = $login('ville.a.' . $suffix . '@test.local');
check('Connexion PATRON A', is_string($patronAToken));
check('Connexion ADMIN_VILLE A', is_string($villeAToken));

if (!is_string($patronAToken) || !is_string($villeAToken)) {
    fwrite(STDERR, "[erreur] jetons absents, poursuite impossible\n");
    $rollback();
    exit(1);
}

// ---------------------------------------------------------------------
// Accès base pour vérifier les effets réels (et non la seule réponse HTTP).
// ---------------------------------------------------------------------
$rentStatus = static fn (int $id): string => (string) $connection->fetchOne('SELECT status FROM rent WHERE id = ?', [$id]);
$sumPayments = static fn (int $rentId): string => (string) $connection->fetchOne(
    'SELECT COALESCE(SUM(amount), 0) FROM payment WHERE rent_id = ?',
    [$rentId]
);
$paymentIdByRef = static fn (string $ref): int => (int) $connection->fetchOne(
    'SELECT id FROM payment WHERE reference = ?',
    [$ref]
);
$reversalCount = static fn (int $originalId): int => (int) $connection->fetchOne(
    'SELECT COUNT(*) FROM payment WHERE reversal_of_id = ?',
    [$originalId]
);
$sumExpenses = static fn (int $cityId, Currency $currency): string => (string) $connection->fetchOne(
    'SELECT COALESCE(SUM(amount), 0) FROM expense WHERE city_id = ? AND currency = ?',
    [$cityId, $currency->value]
);

$recordPayment = static function (string $ref, string $amount) use ($request, $rentAUuid, $patronAToken): array {
    return $request('POST', '/api/v1/payments', [
        'rentUuid' => $rentAUuid,
        'amount' => $amount,
        'currency' => 'USD',
        'paymentDate' => '2026-09-01',
        'method' => 'cash',
        'reference' => $ref,
    ], $patronAToken);
};

// ---------------------------------------------------------------------
section('1. Annulation simple : contre-écriture négative reliée à l\'origine');

[$status, $payload, $raw] = $recordPayment('PAY-1', '500.00');
$pay1 = is_array($payload) ? ($payload['data']['id'] ?? null) : null;
// D14 (connu, hors périmètre) : `Feedback::autoInitFlush()` écrase le 201 en
// 200 sur l'enregistrement, d'où l'acceptation des deux codes ici.
check('POST /api/v1/payments (500) -> 2xx', in_array($status, [200, 201], true), "obtenu {$status} : " . substr($raw, 0, 160));
check('la réponse expose l\'identifiant de l\'écriture', is_string($pay1), $raw);
check('échéance A soldée après enregistrement', $rentStatus($rentAId) === 'paid', 'statut=' . $rentStatus($rentAId));

$pay1Id = $paymentIdByRef('PAY-1');

[$status, $payload, $raw] = $request('POST', '/api/v1/payments/' . $pay1 . '/cancel', ['reason' => 'Erreur de saisie'], $patronAToken);
$cancellation = is_array($payload) ? ($payload['data'] ?? []) : [];
check('POST .../cancel -> 201', $status === 201, "obtenu {$status} : " . substr($raw, 0, 200));
check(
    'la contre-écriture est NÉGATIVE (-500.00)',
    is_array($cancellation) && bccomp((string) ($cancellation['amount'] ?? 'x'), '-500.00', 2) === 0,
    'amount=' . var_export($cancellation['amount'] ?? null, true)
);
check('isReversal = true', ($cancellation['isReversal'] ?? null) === true);
check('reversalOfUuid = écriture d\'origine', ($cancellation['reversalOfUuid'] ?? null) === $pay1);
check('une seule contre-écriture pour l\'origine', $reversalCount($pay1Id) === 1, 'n=' . $reversalCount($pay1Id));
check('somme payée retombe à 0 (soustraction, pas addition)', bccomp($sumPayments($rentAId), '0.00', 2) === 0, 'somme=' . $sumPayments($rentAId));
check('statut de l\'échéance redescend à pending', $rentStatus($rentAId) === 'pending', 'statut=' . $rentStatus($rentAId));

[$status, $payload] = $request('GET', '/api/v1/payments/' . $pay1, null, $patronAToken);
check(
    'l\'écriture d\'origine est marquée annulée (isReversed)',
    $status === 200 && (($payload['data']['isReversed'] ?? null) === true),
    'statut=' . $status
);

// ---------------------------------------------------------------------
section('2. Ressaisie après annulation : un nouveau paiement est accepté');

[$status, $payload, $raw] = $recordPayment('PAY-2', '500.00');
$pay2 = is_array($payload) ? ($payload['data']['id'] ?? null) : null;
check('nouveau paiement après annulation -> 2xx', in_array($status, [200, 201], true), "obtenu {$status} : " . substr($raw, 0, 160));
check('échéance A de nouveau soldée', $rentStatus($rentAId) === 'paid', 'statut=' . $rentStatus($rentAId));
check('somme payée = 500.00', bccomp($sumPayments($rentAId), '500.00', 2) === 0, 'somme=' . $sumPayments($rentAId));

// ---------------------------------------------------------------------
section('3. Double annulation refusée (409) et unicité garantie');

[$status, $payload] = $request('POST', '/api/v1/payments/' . $pay2 . '/cancel', ['reason' => 'Première annulation'], $patronAToken);
$pay2Reversal = is_array($payload) ? ($payload['data']['id'] ?? null) : null;
check('première annulation -> 201', $status === 201, "obtenu {$status}");

[$status, $payload, $raw] = $request('POST', '/api/v1/payments/' . $pay2 . '/cancel', ['reason' => 'Deuxième annulation'], $patronAToken);
check('seconde annulation -> 409', $status === 409, "obtenu {$status} : " . substr($raw, 0, 160));

$pay2Id = $paymentIdByRef('PAY-2');
check('toujours une seule contre-écriture', $reversalCount($pay2Id) === 1, 'n=' . $reversalCount($pay2Id));

// Annuler une contre-écriture est refusé.
[$status] = $request('POST', '/api/v1/payments/' . $pay2Reversal . '/cancel', ['reason' => 'Annuler la correction'], $patronAToken);
check('annuler une contre-écriture -> 409', $status === 409, "obtenu {$status}");

// ---------------------------------------------------------------------
section('4. Dépense : annulation par contre-écriture négative');

[$status, $payload, $raw] = $request('POST', '/api/v1/expenses/' . $expenseUsdUuid . '/cancel', ['reason' => 'Doublon'], $patronAToken);
$expenseCancellation = is_array($payload) ? ($payload['data'] ?? []) : [];
check('POST /api/v1/expenses/{uuid}/cancel -> 201', $status === 201, "obtenu {$status} : " . substr($raw, 0, 160));
check(
    'contre-écriture dépense NÉGATIVE (-100.00)',
    bccomp((string) ($expenseCancellation['amount'] ?? 'x'), '-100.00', 2) === 0,
    'amount=' . var_export($expenseCancellation['amount'] ?? null, true)
);
check('isReversal = true (dépense)', ($expenseCancellation['isReversal'] ?? null) === true);
check('reversalOfUuid = dépense d\'origine', ($expenseCancellation['reversalOfUuid'] ?? null) === $expenseUsdUuid);

// ---------------------------------------------------------------------
section('5. Devises séparées : annuler l\'USD n\'affecte pas le CDF');

check('total dépenses USD ville A = 0.00', bccomp($sumExpenses($cityAId, Currency::USD), '0.00', 2) === 0, 'somme=' . $sumExpenses($cityAId, Currency::USD));
check('total dépenses CDF ville A = 1000.00', bccomp($sumExpenses($cityAId, Currency::CDF), '1000.00', 2) === 0, 'somme=' . $sumExpenses($cityAId, Currency::CDF));

// ---------------------------------------------------------------------
section('6. Permissions : ADMIN_VILLE peut annuler un paiement, pas une dépense');

[$status, $payload] = $recordPayment('PAY-4', '500.00');
$pay4 = is_array($payload) ? ($payload['data']['id'] ?? null) : null;
check('paiement PAY-4 enregistré', in_array($status, [200, 201], true) && is_string($pay4));

[$status, $raw] = $request('POST', '/api/v1/payments/' . $pay4 . '/cancel', ['reason' => 'Annulation par admin ville'], $villeAToken);
check('ADMIN_VILLE annule un paiement de SA ville -> 201', $status === 201, "obtenu {$status}");

[$status, $raw] = $request('POST', '/api/v1/expenses/' . $expenseCdfUuid . '/cancel', ['reason' => 'Tentative'], $villeAToken);
check('ADMIN_VILLE ne peut PAS annuler une dépense (DELETE_EXPENSE) -> 403', $status === 403, "obtenu {$status}");

// ---------------------------------------------------------------------
section('7. Isolation multi-organisation : A ne peut pas annuler une écriture de B');

[$status, $raw] = $request('POST', '/api/v1/payments/' . $paymentBUuid . '/cancel', ['reason' => 'Tentative'], $patronAToken);
check('PATRON A annule un paiement de B -> 403 ou 404', in_array($status, [403, 404], true), "obtenu {$status}");

// ---------------------------------------------------------------------
section('8. Les impayés réapparaissent après annulation (statut dérivé)');

$inOverdue = static function (string $rentUuid) use ($request, $patronAToken): bool {
    [$status, $payload] = $request('GET', '/api/v1/rents/overdue', null, $patronAToken);
    $items = is_array($payload) ? ($payload['data']['items'] ?? []) : [];

    return $status === 200 && in_array($rentUuid, array_column($items, 'id'), true);
};

[$status, $payload] = $recordPayment('PAY-5', '500.00');
$pay5 = is_array($payload) ? ($payload['data']['id'] ?? null) : null;
check('PAY-5 enregistré (échéance soldée)', in_array($status, [200, 201], true));
check('échéance soldée absente des impayés', $inOverdue($rentAUuid) === false);

[$status] = $request('POST', '/api/v1/payments/' . $pay5 . '/cancel', ['reason' => 'Correction'], $patronAToken);
check('annulation de PAY-5 -> 201', $status === 201, "obtenu {$status}");
check('la somme payée retombe à 0', bccomp($sumPayments($rentAId), '0.00', 2) === 0, 'somme=' . $sumPayments($rentAId));
check('l\'échéance en retard réapparaît', $inOverdue($rentAUuid) === true);

// ---------------------------------------------------------------------
section('9. Schéma : colonnes, index UNIQUE, FK et contraintes CHECK');

$schemaProbe = static function (string $sql, array $params = []) use ($connection): int {
    return (int) $connection->fetchOne($sql, $params);
};

$uniqueIndex = $schemaProbe(
    "SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment'
        AND INDEX_NAME = 'UNIQ_6D28840D29A0BB4E' AND NON_UNIQUE = 0"
);
check('index UNIQUE sur payment.reversal_of_id', $uniqueIndex === 1, 'n=' . $uniqueIndex);

$uniqueIndexExpense = $schemaProbe(
    "SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expense'
        AND INDEX_NAME = 'UNIQ_2D3A8DA629A0BB4E' AND NON_UNIQUE = 0"
);
check('index UNIQUE sur expense.reversal_of_id', $uniqueIndexExpense === 1, 'n=' . $uniqueIndexExpense);

$fkPayment = $schemaProbe(
    "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment'
        AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = 'FK_6D28840D29A0BB4E'"
);
check('FK auto-référencée sur payment.reversal_of_id', $fkPayment === 1, 'n=' . $fkPayment);

$checkPayment = $schemaProbe(
    "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment'
        AND CONSTRAINT_TYPE = 'CHECK' AND CONSTRAINT_NAME = 'chk_payment_reversal_amount'"
);
check('contrainte CHECK de signe sur payment', $checkPayment === 1, 'n=' . $checkPayment);

$checkExpense = $schemaProbe(
    "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expense'
        AND CONSTRAINT_TYPE = 'CHECK' AND CONSTRAINT_NAME = 'chk_expense_reversal_amount'"
);
check('contrainte CHECK de signe sur expense', $checkExpense === 1, 'n=' . $checkExpense);

// ---------------------------------------------------------------------
section('Annulation de la transaction');

$rollback();

echo "\n" . str_repeat('-', 60) . "\n";

if ($failures === []) {
    echo "SUCCES : {$checks} contrôles passés\n";

    exit(0);
}

echo 'ECHEC : ' . count($failures) . " contrôle(s) en échec sur {$checks}\n";

foreach ($failures as $failure) {
    echo "  - {$failure}\n";
}

exit(1);
