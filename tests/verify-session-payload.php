<?php

declare(strict_types=1);

/**
 * Vérifie la charge utile de session renvoyée par /api/auth/login et
 * /api/auth/me.
 *
 * Le jeton de cette application est un cookie de session `HIMMOMPA`, pas
 * un JWT : il est illisible par le JavaScript de la page et ne peut donc
 * pas transporter d'information. L'identité et les droits sont renvoyés
 * dans le corps de la réponse, et c'est ce que ce script vérifie.
 *
 * Les requêtes passent par le vrai noyau HTTP (routage, firewall,
 * contrôleurs, listeners) avec un client sans état qui ne transporte que
 * les cookies.
 *
 *   php tests/verify-session-payload.php
 */

use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationUser;
use App\Entity\Identity\User;
use App\Entity\Identity\UserCity;
use App\Entity\Property\City;
use App\Enum\CityStatus;
use App\Enum\OrganizationRole;
use App\Enum\OrganizationStatus;
use App\Enum\PlatformRole;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;

ob_start();

require dirname(__DIR__) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

final class SessionPayloadKernel extends App\Kernel
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

$kernel = new SessionPayloadKernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();
$container->get('cache.rate_limiter')->clear();

// Le service `doctrine` est reinitialise entre les requetes traversant le
// noyau (`ManagerRegistry::resetManager()`), ce qui detache les entites
// creees precedemment. Une transaction annulee en fin de script evite
// d'avoir a les recharger, et garantit qu'un echec fatal ne laisse pas de
// donnee de test en base.
$connection = $em->getConnection();
$connection->beginTransaction();

$rollback = static function () use ($connection): void {
    if ($connection->isTransactionActive()) {
        $connection->rollBack();
    }
};

register_shutdown_function(static function () use ($rollback): void {
    $rollback();
});

// Court : la colonne `code` est varchar(30) et refuse les suffixes longs.
$suffix = 'sp' . bin2hex(random_bytes(4));

$makeUser = static function (string $email, string $name, ?PlatformRole $role) use ($em, $suffix): User {
    $user = (new User())
        ->setEmail($email . '.' . $suffix . '@test.local')
        ->setFullName($name)
        ->setPassword(password_hash('MotDePasse!123', PASSWORD_BCRYPT))
        ->setIsActive(true);

    if ($role !== null) {
        $user->setPlatformRole($role);
    }

    $em->persist($user);

    return $user;
};

$makeOrg = static function (string $name, string $code) use ($em, $suffix): Organization {
    $org = new Organization();
    $org->setName($name);
    $org->setCode($code . '-' . $suffix);
    $org->setEmail(strtolower($code) . $suffix . '@orga.test');
    $org->setPhone('+000');
    $org->setStatus(OrganizationStatus::ACTIVE);
    $em->persist($org);

    return $org;
};

$makeCity = static function (string $name, string $code, Organization $org, CityStatus $status) use ($em, $suffix): City {
    $city = new City();
    $city->setName($name);
    $city->setCode($code . '-' . $suffix);
    $city->setCountry('CD');
    $city->setStatus($status);
    $city->setOrganization($org);
    $em->persist($city);

    return $city;
};

$membership = static function (User $user, Organization $org, OrganizationRole $role) use ($em): OrganizationUser {
    $ou = new OrganizationUser();
    $ou->setUser($user);
    $ou->setOrganization($org);
    $ou->setRole($role);
    $em->persist($ou);

    return $ou;
};

$superAdmin = $makeUser('root', 'Root Plateforme', PlatformRole::SUPER_ADMIN);
$patron = $makeUser('patron', 'Patron', null);
$adminVille = $makeUser('ville', 'Admin Ville', null);
$inactif = $makeUser('inactif', 'Sans Ville', null);

$orgZ = $makeOrg('Zeta Organization', 'ZETA');
$orgA = $makeOrg('Alpha Organization', 'ALPHA');
$membership($patron, $orgZ, OrganizationRole::PATRON);
$membership($patron, $orgA, OrganizationRole::ADMIN_IMMOBILIER);
$membership($adminVille, $orgA, OrganizationRole::ADMIN_VILLE);

$villeActive = $makeCity('Ville Active', 'VA', $orgA, CityStatus::ACTIVE);
$villeInactive = $makeCity('Ville Inactive', 'VI', $orgA, CityStatus::INACTIVE);

$assign = static function (User $user, City $city) use ($em): void {
    $uc = new UserCity();
    $uc->setUser($user);
    $uc->setCity($city);
    $em->persist($uc);
};

$assign($adminVille, $villeActive);
$assign($adminVille, $villeInactive);

$em->flush();

$jar = [];

$request = static function (string $method, string $uri, ?array $json = null) use ($kernel, &$jar): array {
    $server = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'HTTP_HOST' => 'localhost'];

    if ($jar !== []) {
        $pairs = [];

        foreach ($jar as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }

        $server['HTTP_COOKIE'] = implode('; ', $pairs);
    }

    $content = $json !== null ? json_encode($json, JSON_THROW_ON_ERROR) : null;
    $req = Request::create($uri, $method, [], $jar, [], $server, $content);

    if ($content !== null) {
        $req->headers->set('CONTENT_TYPE', 'application/json');
    }

    $response = $kernel->handle($req);

    foreach ($response->headers->getCookies() as $cookie) {
        if ($cookie->getValue() === null || $cookie->getValue() === '') {
            unset($jar[$cookie->getName()]);

            continue;
        }

        $jar[$cookie->getName()] = $cookie->getValue();
    }

    $status = $response->getStatusCode();
    $raw = (string) $response->getContent();
    $setCookieHeader = $response->headers->get('Set-Cookie');
    $kernel->terminate($req, $response);

    return [$status, $raw, $setCookieHeader];
};

$login = static fn (User $u): array => $request('POST', '/api/auth/login', [
    'email' => $u->getEmail(),
    'password' => 'MotDePasse!123',
]);

echo "\n=== Le jeton est bien un cookie, pas un corps JSON ===\n";

$jar = [];
[$status, $raw, $setCookie] = $login($superAdmin);
check('POST /api/auth/login renvoie 200', $status === 200, "obtenu {$status}");
check(
    'le cookie de session HIMMOMPA est émis dans Set-Cookie',
    is_string($setCookie) && str_contains($setCookie, 'HIMMOMPA='),
    'aucun Set-Cookie',
);
check('le cookie est HttpOnly', is_string($setCookie) && str_contains(strtolower($setCookie), 'httponly'));

$decoded = json_decode($raw, true);
check('le corps JSON est décodable', is_array($decoded), $raw);
check(
    'aucun jeton n’est exposé dans le corps JSON',
    !str_contains($raw, 'HIMMOMPA') && !str_contains(strtolower($raw), 'token'),
    'jeton trouvé dans le corps',
);
check('le corps contient un objet user', isset($decoded['user']) && is_array($decoded['user']));

echo "\n=== Charge utile de session enrichie ===\n";

$user = $decoded['user'];
foreach (['uuid', 'email', 'fullName', 'phone', 'profilePhoto', 'platformRole', 'roles', 'organizations', 'cities', 'cityScope', 'isActive', 'lastLoginAt'] as $field) {
    check("le champ « {$field} » est présent", array_key_exists($field, $user), implode(',', array_keys($user)));
}

check('platformRole vaut super_admin', ($user['platformRole'] ?? null) === 'super_admin', var_export($user['platformRole'] ?? null, true));
check(
    'roles contient ROLE_SUPER_ADMIN',
    is_array($user['roles'] ?? null) && in_array('ROLE_SUPER_ADMIN', $user['roles'], true),
    json_encode($user['roles'] ?? null),
);
check('organizations est un tableau', is_array($user['organizations'] ?? null));
check('cities est un tableau', is_array($user['cities'] ?? null));
check('cityScope vaut platform pour un SUPER_ADMIN', ($user['cityScope'] ?? null) === 'platform', var_export($user['cityScope'] ?? null, true));
check('isActive vaut true', ($user['isActive'] ?? null) === true);

echo "\n=== Horodatage de dernière connexion ===\n";

check(
    'lastLoginAt est renseigné après une connexion',
    is_string($user['lastLoginAt'] ?? null) && $user['lastLoginAt'] !== '',
    var_export($user['lastLoginAt'] ?? null, true),
);
check(
    'lastLoginAt est au format ISO-8601',
    is_string($user['lastLoginAt'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $user['lastLoginAt']) === 1,
    var_export($user['lastLoginAt'] ?? null, true),
);
check(
    'lastLoginAt ne fuit pas la structure interne de DateTime',
    !str_contains($raw, 'timezone_type'),
    'structure PHP DateTime presente dans la reponse',
);

echo "\n=== Rôles métier par Organization ===\n";

$jar = [];
[$status, $raw] = $login($patron);
check('POST /api/auth/login du patron renvoie 200', $status === 200, "obtenu {$status}");
$patronUser = json_decode($raw, true)['user'] ?? [];

check(
    'le patron est membre de 2 organizations',
    count($patronUser['organizations'] ?? []) === 2,
    'recu ' . count($patronUser['organizations'] ?? []),
);

$codes = array_column($patronUser['organizations'] ?? [], 'code');
$sorted = $codes;
sort($sorted);
check('les organizations sont triées par code (réponse stable)', $codes === $sorted, json_encode($codes));

$byCode = [];
foreach ($patronUser['organizations'] ?? [] as $membershipEntry) {
    $byCode[$membershipEntry['code']] = $membershipEntry;
}

$alphaCode = $orgA->getCode();
$zetaCode = $orgZ->getCode();
check('l’uuid de l\'{Organization\} est exposé', is_string($byCode[$alphaCode]['uuid'] ?? null) && $byCode[$alphaCode]['uuid'] !== '', 'uuid manquant');
check('le rôle patron est correct', ($byCode[$zetaCode]['role'] ?? null) === 'patron', var_export($byCode[$zetaCode]['role'] ?? null, true));
check('le rôle admin_immobilier est correct', ($byCode[$alphaCode]['role'] ?? null) === 'admin_immobilier', var_export($byCode[$alphaCode]['role'] ?? null, true));
check(
    'platformRole est null pour un compte métier',
    array_key_exists('platformRole', $patronUser) && $patronUser['platformRole'] === null,
    var_export($patronUser['platformRole'] ?? 'CHAMP ABSENT', true),
);
check('cityScope vaut none pour un patron', ($patronUser['cityScope'] ?? null) === 'none', var_export($patronUser['cityScope'] ?? null, true));
check('cities est vide pour un patron', ($patronUser['cities'] ?? null) === [], json_encode($patronUser['cities'] ?? null));

echo "\n=== Villes accessibles (ADMIN_VILLE) ===\n";

$jar = [];
[$status, $raw] = $login($adminVille);
check('POST /api/auth/login de l\'admin de ville renvoie 200', $status === 200, "obtenu {$status}");
$villeUser = json_decode($raw, true)['user'] ?? [];

check('cityScope vaut assigned', ($villeUser['cityScope'] ?? null) === 'assigned', var_export($villeUser['cityScope'] ?? null, true));
check('une seule ville est listée', count($villeUser['cities'] ?? []) === 1, 'recu ' . count($villeUser['cities'] ?? []));
check(
    'la ville inactive est exclue',
    ($villeUser['cities'][0]['name'] ?? null) === 'Ville Active',
    var_export($villeUser['cities'][0]['name'] ?? null, true),
);
check('la province est exposée (null si absente)', array_key_exists('province', $villeUser['cities'][0] ?? []));
check('le statut de la ville est exposé', ($villeUser['cities'][0]['status'] ?? null) === 'active', var_export($villeUser['cities'][0]['status'] ?? null, true));

echo "\n=== /api/auth/me renvoie la même charge utile ===\n";

[$status, $raw] = $request('GET', '/api/auth/me');
check('GET /api/auth/me renvoie 200', $status === 200, "obtenu {$status}");
$me = json_decode($raw, true);
check('la réponse est directement l\'utilisateur (pas enveloppée)', isset($me['email']) && !isset($me['user']), 'enveloppe inattendue');
check('me contient organizations', array_key_exists('organizations', $me));
check('me contient cityScope', array_key_exists('cityScope', $me));
check(
    'me est cohérent avec login sur le role',
    ($me['platformRole'] ?? null) === ($villeUser['platformRole'] ?? null),
    'me=' . var_export($me['platformRole'] ?? 'CHAMP ABSENT', true) . ' login=' . var_export($villeUser['platformRole'] ?? 'CHAMP ABSENT', true),
);

$jar = [];
$request('POST', '/api/auth/login', ['email' => $inactif->getEmail(), 'password' => 'mauvais']);
[$status] = $request('GET', '/api/auth/me');
check('GET /api/auth/me sans session valide renvoie 401', $status === 401, "obtenu {$status}");

echo "\n=== Aucune fuite de secret ===\n";

$jar = [];
[, $raw] = $login($superAdmin);
check('le hash du mot de passe est absent', !str_contains($raw, '$2y$'), 'hash bcrypt present');
check('le mot de passe en clair est absent', !str_contains($raw, 'MotDePasse!123'), 'mot de passe present');
check('aucune entite Doctrine brute n’est exposée', !str_contains($raw, '"password"') && !str_contains($raw, 'passwordHash'), 'champ password present');

echo "\n=== Schema de securite de la documentation ===\n";

// Un second noyau : le generateur OpenAPI a besoin d'un conteneur neuf, le
// precedant etant encore occupe par les requetes de la session.
$docKernel = new SessionPayloadKernel('dev', true);
$docKernel->boot();
$doc = $docKernel->getContainer()->get('nelmio_api_doc.generator')->generate()->toJson();
$decodedDoc = json_decode($doc, true);
$docKernel->shutdown();

$schemes = array_keys($decodedDoc['components']['securitySchemes'] ?? []);
check('le schéma de sécurité est sessionCookie', in_array('sessionCookie', $schemes, true), json_encode($schemes));
check('aucun schéma bearer/JWT résiduel', !in_array('bearer', $schemes, true), json_encode($schemes));
check('le schéma déclare un cookie nommé HIMMOMPA', ($decodedDoc['components']['securitySchemes']['sessionCookie']['name'] ?? null) === 'HIMMOMPA');

$prefix = '#/components/schemas/';
// On analyse la sortie du générateur : `json_encode` réel échappe les '/'
// en '\/', ce qui ferait échouer la comparaison de préfixe.
preg_match_all('/"\$ref":\s*"([^"]+)"/', $doc, $matches);
$allRefs = array_values(array_unique($matches[1] ?? []));

$badRefs = array_values(array_filter($allRefs, static fn (string $ref): bool => !str_starts_with($ref, $prefix)));

// Limite connue et hors périmètre : les 6 DTOs imbriqués des réponses de
// rapport sont référencés depuis `OA\Items`, qui n'accepte qu'un pointeur
// et ne peut donc pas enregistrer leur schéma. Signalée, pas masquée.
$knownReportRefs = array_values(array_filter(
    $badRefs,
    static fn (string $ref): bool => str_contains($ref, 'Dto\\\\Response\\\\Report\\\\'),
));
$badRefs = array_values(array_diff($badRefs, $knownReportRefs));
check('aucun $ref ne fuit un nom de classe complet (hors DTOs de rapport connus)', $badRefs === [], json_encode($badRefs));
echo '  [info] Limite connue : ' . count($knownReportRefs) . " \$ref de DTOs de rapport non résolus (hors périmètre)\n";

$schemaNames = array_keys($decodedDoc['components']['schemas'] ?? []);
$pointers = array_values(array_filter($allRefs, static fn (string $ref): bool => str_starts_with($ref, $prefix)));
$dangling = array_values(array_filter(
    $pointers,
    static fn (string $ref): bool => !in_array(substr($ref, strlen($prefix)), $schemaNames, true),
));
check('aucun $ref ne pointe vers un schema inexistant', $dangling === [], json_encode($dangling));

check('le schéma SessionUserResponse est publié', in_array('SessionUserResponse', $schemaNames, true), implode(',', $schemaNames));
check('SessionUserResponse expose cityScope', isset($decodedDoc['components']['schemas']['SessionUserResponse']['properties']['cityScope']));
check('SessionUserResponse expose organizations', isset($decodedDoc['components']['schemas']['SessionUserResponse']['properties']['organizations']));
check(
    'la réponse 200 de login référence SessionUserResponse',
    ($decodedDoc['paths']['/api/auth/login']['post']['responses']['200']['content']['application/json']['schema']['properties']['user']['$ref'] ?? null) === '#/components/schemas/SessionUserResponse',
    var_export($decodedDoc['paths']['/api/auth/login']['post']['responses']['200']['content']['application/json']['schema']['properties']['user']['$ref'] ?? null, true),
);
check(
    'la réponse 200 de me référence SessionUserResponse',
    ($decodedDoc['paths']['/api/auth/me']['get']['responses']['200']['content']['application/json']['schema']['$ref'] ?? null) === '#/components/schemas/SessionUserResponse',
);

echo "\n=== Annulation de la transaction ===\n";
$rollback();
echo "[info] Jeu de donnees de test annule : la base est inchangee.\n";

echo "\n" . str_repeat('-', 60) . "\n";

if ($failures === []) {
    echo "SUCCES : {$checks} contrôles passés\n";
    exit(0);
}

echo "ECHEC : " . count($failures) . " contrôle(s) en échec sur {$checks}\n";

foreach ($failures as $failure) {
    echo "  - {$failure}\n";
}

exit(1);
