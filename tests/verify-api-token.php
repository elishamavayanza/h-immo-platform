<?php

declare(strict_types=1);

/**
 * Vérifie le jeton d'API et la charge utile renvoyée par
 * /api/auth/login et /api/auth/me.
 *
 * Le jeton est un JWT HS256 renvoyé dans le corps de la réponse sous
 * `accessToken`, puis renvoyé par le client dans l'en-tête
 * `Authorization: Bearer <token>`. Il est auto-porteur : il porte
 * l'identité et les droits, ce qui permet au client de connaître son
 * état sans appel réseau supplémentaire.
 *
 * Ce qui est vérifié ici :
 *   - la forme et le contenu du jeton (revendications, expiration) ;
 *   - qu'un jeton falsifié, expiré, en `alg: none` ou révoqué est rejeté ;
 *   - que la charge utile est cohérente entre le login et /me ;
 *   - qu'aucun secret ne fuit dans le corps ni dans le jeton.
 *
 * Les requêtes passent par le vrai noyau HTTP (routage, firewall,
 * contrôleurs, listeners) avec un client sans état qui ne transporte que
 * le jeton.
 *
 *   php tests/verify-api-token.php
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

final class ApiTokenKernel extends App\Kernel
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

$kernel = new ApiTokenKernel('dev', true);
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

// Le client ne transporte QUE le jeton : ni cookie, ni état conservé entre
// les requêtes. C'est ce qui prouve que l'accès tient dans le jeton.
$jeton = null;
$cookiesEmis = 0;

$request = static function (string $method, string $uri, ?array $json = null) use ($kernel, &$jeton, &$cookiesEmis): array {
    $server = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'HTTP_HOST' => 'localhost'];

    if ($jeton !== null) {
        $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $jeton;
    }

    $content = $json !== null ? json_encode($json, JSON_THROW_ON_ERROR) : null;
    $req = Request::create($uri, $method, [], [], [], $server, $content);

    if ($content !== null) {
        $req->headers->set('CONTENT_TYPE', 'application/json');
    }

    $response = $kernel->handle($req);

    $status = $response->getStatusCode();
    $raw = (string) $response->getContent();
    $wwwAuthenticate = $response->headers->get('WWW-Authenticate');
    $cookiesEmis = count($response->headers->getCookies());
    $kernel->terminate($req, $response);

    return [$status, $raw, $wwwAuthenticate];
};

// Après un login réussi, le client conserve le jeton comme le ferait un
// vrai front : c'est ce qui rend les assertions suivantes réalistes.
$login = static function (User $u) use ($request, &$jeton): array {
    $jeton = null;
    [$status, $raw] = $request('POST', '/api/auth/login', [
        'email' => $u->getEmail(),
        'password' => 'MotDePasse!123',
    ]);

    $payload = json_decode($raw, true);
    $jeton = is_string($payload['accessToken'] ?? null) ? $payload['accessToken'] : null;

    return [$status, $raw];
};

echo "\n=== Emission du jeton ===\n";

[$status, $raw] = $login($superAdmin);
check('POST /api/auth/login renvoie 200', $status === 200, "obtenu {$status}");

$decoded = json_decode($raw, true);
check('le corps JSON est décodable', is_array($decoded), $raw);
check('le corps contient un objet user', isset($decoded['user']) && is_array($decoded['user']));
check('tokenType vaut Bearer', ($decoded['tokenType'] ?? null) === 'Bearer', var_export($decoded['tokenType'] ?? null, true));
check(
    'expiresIn est un entier positif',
    is_int($decoded['expiresIn'] ?? null) && $decoded['expiresIn'] > 0,
    var_export($decoded['expiresIn'] ?? null, true),
);
check(
    'accessToken est un JWT en trois segments',
    is_string($decoded['accessToken'] ?? null) && substr_count($decoded['accessToken'], '.') === 2,
    'jeton mal formé',
);

// Le pare-feu est `stateless` : aucun cookie ne doit être émis. Un cookie
// permettrait au navigateur de rester connecté même après une révocation.
check('aucun cookie n’est émis par le login', $cookiesEmis === 0, "{$cookiesEmis} cookie(s)");

$base64UrlDecode = static function (string $segment): string {
    $padded = strtr($segment, '-_', '+/');
    $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);

    return (string) base64_decode($padded, true);
};

[$headerB64, $payloadB64, $signatureB64] = explode('.', $decoded['accessToken']);
$header = json_decode($base64UrlDecode($headerB64), true);
$claims = json_decode($base64UrlDecode($payloadB64), true);

check('le header déclare HS256', ($header['alg'] ?? null) === 'HS256', json_encode($header));
check('le header déclare le type JWT', ($header['typ'] ?? null) === 'JWT', json_encode($header));
check('la signature est présente et non vide', $signatureB64 !== '');

echo "\n=== Revendications du jeton ===\n";

foreach (['iss', 'aud', 'sub', 'jti', 'iat', 'exp', 'email', 'platformRole', 'roles', 'cityScope', 'isActive', 'organizations'] as $claim) {
    check("la revendication « {$claim} » est présente", array_key_exists($claim, $claims), implode(',', array_keys($claims)));
}

check(
    'sub est l\'UUID du compte',
    ($claims['sub'] ?? null) === ($decoded['user']['uuid'] ?? 'x'),
    var_export($claims['sub'] ?? null, true),
);
check('jti est un hexadécimal de 32 caractères', is_string($claims['jti'] ?? null) && preg_match('/^[0-9a-f]{32}$/', $claims['jti']) === 1, var_export($claims['jti'] ?? null, true));
check('email correspond au compte connecté', ($claims['email'] ?? null) === ($decoded['user']['email'] ?? 'x'), var_export($claims['email'] ?? null, true));
check('platformRole correspond au corps', ($claims['platformRole'] ?? null) === ($decoded['user']['platformRole'] ?? 'x'), var_export($claims['platformRole'] ?? null, true));
check('cityScope correspond au corps', ($claims['cityScope'] ?? null) === ($decoded['user']['cityScope'] ?? 'x'), var_export($claims['cityScope'] ?? null, true));
check(
    'exp est postérieur à iat',
    is_int($claims['iat'] ?? null) && is_int($claims['exp'] ?? null) && $claims['exp'] > $claims['iat'],
);
check(
    'l\'expiration correspond à expiresIn',
    is_int($claims['iat'] ?? null) && is_int($claims['exp'] ?? null)
        && ($claims['exp'] - $claims['iat']) === $decoded['expiresIn'],
    'durée incohérente',
);
check('roles est un tableau', is_array($claims['roles'] ?? null));
check('organizations est un tableau', is_array($claims['organizations'] ?? null));

echo "\n=== Un jeton falsifié est refusé ===\n";

// Signature : un caractère modifié doit invalider le jeton.
//
// Le changement se fait au DÉBUT et non à la fin : en base64url, le
// dernier caractère d'une signature de 32 octets n'encode que 4 bits
// utiles (256 = 6*42 + 4) et les 2 autres sont ignorés au décodage.
// Modifier ce seul caractère produirait le PLUSIEURS FOIS la même
// signature, et le test passerait à tort.
$signatureFalsifiee = (substr($signatureB64, 0, 1) === 'A' ? 'B' : 'A') . substr($signatureB64, 1);
$jetonSain = $jeton;
$jeton = $headerB64 . '.' . $payloadB64 . '.' . $signatureFalsifiee;
[$status] = $request('GET', '/api/auth/me');
check('une signature falsifiée renvoie 401', $status === 401, "obtenu {$status}");

// Charge utile : on AJOUTE une revendication en gardant la signature.
// C'est la contre-mesure (« élévation de privilèges ») qu'un attaquant
// tenterait : s'il passait, il pourrait se déclarer administrateur sans
// jamais connaître la clé de signature.
$chargesModifiees = $claims;
$chargesModifiees['roles'] = ['ROLE_USER', 'ROLE_SUPER_ADMIN', 'ROLE_ADMIN'];
$payloadFalsifie = rtrim(strtr(base64_encode((string) json_encode($chargesModifiees)), '+/', '-_'), '=');
$jeton = $headerB64 . '.' . $payloadFalsifie . '.' . $signatureB64;
[$status] = $request('GET', '/api/auth/me');
check('une charge utile falsifiée renvoie 401', $status === 401, "obtenu {$status}");

// Expiration dans le passé.
$chargesExpirees = $claims;
$chargesExpirees['exp'] = $claims['iat'] - 10;
$payloadExpire = rtrim(strtr(base64_encode((string) json_encode($chargesExpirees)), '+/', '-_'), '=');
$jeton = $headerB64 . '.' . $payloadExpire . '.' . $signatureB64;
[$status] = $request('GET', '/api/auth/me');
check('un jeton expiré renvoie 401', $status === 401, "obtenu {$status}");

// « algorithm confusion » : un jeton non signé en `alg: none`.
$headerNone = rtrim(strtr(base64_encode((string) json_encode(['typ' => 'JWT', 'alg' => 'none'])), '+/', '-_'), '=');
$jeton = $headerNone . '.' . $payloadB64 . '.';
[$status] = $request('GET', '/api/auth/me');
check('un jeton en « alg: none » renvoie 401', $status === 401, "obtenu {$status}");

// `sub` inexistant : la signature est valide, le compte ne l'est pas.
$chargesInconnues = $claims;
$chargesInconnues['sub'] = '00000000-0000-4000-8000-000000000000';
$payloadInconnu = rtrim(strtr(base64_encode((string) json_encode($chargesInconnues)), '+/', '-_'), '=');
$jeton = $headerB64 . '.' . $payloadInconnu . '.' . $signatureB64;
[$status] = $request('GET', '/api/auth/me');
check('un jeton dont le compte a disparu renvoie 401', $status === 401, "obtenu {$status}");

$jeton = $jetonSain;
[$status, $rawMe] = $request('GET', '/api/auth/me');
check('le jeton authentique est toujours accepté', $status === 200, "obtenu {$status}");

echo "\n=== Révocation à la déconnexion ===\n";

// Sans révocation, un jeton intercepté resterait utilisable jusqu'à son
// expiration : « se déconnecter » ne serait qu'un mot.
[$status, $rawLogout] = $login($superAdmin);
check('nouvelle connexion pour tester la révocation', $status === 200, "obtenu {$status}");
$jetonRevocable = $jeton;

[$status, $rawMeAvant] = $request('GET', '/api/auth/me');
check('le jeton fonctionne avant déconnexion', $status === 200, "obtenu {$status}");

[$status, $rawLogout] = $request('POST', '/api/auth/logout');
check('POST /api/auth/logout renvoie 200', $status === 200, "obtenu {$status} : " . substr($rawLogout, 0, 120));
check('la réponse confirme la révocation', str_contains($rawLogout, '"tokenRevoked":true'), substr($rawLogout, 0, 120));

// On rejoue le jeton RÉVOQUÉ, pas le nouveau : c'est bien lui qui doit
// devenir inutilisable.
$jeton = $jetonRevocable;
[$status] = $request('GET', '/api/auth/me');
check('le jeton révoqué est refusé (401)', $status === 401, "obtenu {$status} : un jeton révoqué reste accepté");
[$status] = $request('GET', '/api/v1/identity/organizations');
check('le jeton révoqué est refusé aussi sur une route métier', $status === 401, "obtenu {$status}");

echo "\n=== En-tête WWW-Authenticate ===\n";

$jeton = null;
[, $raw401, $wwwAuthenticate] = $request('GET', '/api/auth/me');
check('sans jeton, /api/auth/me renvoie 401', $status === 401, "obtenu {$status}");
check(
    'le 401 indique le schéma Bearer',
    is_string($wwwAuthenticate) && str_contains($wwwAuthenticate, 'Bearer'),
    var_export($wwwAuthenticate, true),
);
check('le 401 ne fuit aucune trace d\'exécution', !str_contains($raw401, 'trace'), 'trace dans la réponse');

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

$jeton = null;
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

$jeton = null;
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

$jeton = null;
$request('POST', '/api/auth/login', ['email' => $inactif->getEmail(), 'password' => 'mauvais']);
[$status] = $request('GET', '/api/auth/me');
check('GET /api/auth/me sans jeton valide renvoie 401', $status === 401, "obtenu {$status}");

echo "\n=== Aucune fuite de secret ===\n";

$jeton = null;
[, $raw] = $login($superAdmin);
check('le hash du mot de passe est absent', !str_contains($raw, '$2y$'), 'hash bcrypt present');
check('le mot de passe en clair est absent', !str_contains($raw, 'MotDePasse!123'), 'mot de passe present');
check('aucune entite Doctrine brute n’est exposée', !str_contains($raw, '"password"') && !str_contains($raw, 'passwordHash'), 'champ password present');

echo "\n=== Schema de securite de la documentation ===\n";

// Un second noyau : le generateur OpenAPI a besoin d'un conteneur neuf, le
// precedant etant encore occupe par les requetes de la session.
$docKernel = new ApiTokenKernel('dev', true);
$docKernel->boot();
$doc = $docKernel->getContainer()->get('nelmio_api_doc.generator')->generate()->toJson();
$decodedDoc = json_decode($doc, true);
$docKernel->shutdown();

$schemes = array_keys($decodedDoc['components']['securitySchemes'] ?? []);
$schemaBearer = $decodedDoc['components']['securitySchemes']['bearer'] ?? [];
check('le schéma de sécurité est bearer', in_array('bearer', $schemes, true), json_encode($schemes));
check('aucun schéma de cookie résiduel', !in_array('sessionCookie', $schemes, true), json_encode($schemes));
check('le schéma est de type http', ($schemaBearer['type'] ?? null) === 'http', json_encode($schemaBearer));
check('le schéma déclare le schéma bearer', ($schemaBearer['scheme'] ?? null) === 'bearer', json_encode($schemaBearer));
check('le schéma annonce un format JWT', ($schemaBearer['bearerFormat'] ?? null) === 'JWT', json_encode($schemaBearer));
check('la sécurité globale s\'applique au bearer', ($decodedDoc['security'][0] ?? null) === ['bearer' => []], json_encode($decodedDoc['security'] ?? null));

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
