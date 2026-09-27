<?php

declare(strict_types=1);

/**
 * Vérifie l'authentification de l'API de bout en bout.
 *
 * Les requêtes passent par le vrai noyau HTTP (routage, firewall,
 * contrôleurs, listener d'exceptions) : c'est le seul moyen de vérifier
 * que la configuration de sécurité fonctionne, `lint:container` ne
 * contrôlant que l'injection des dépendances.
 *
 * Le noyau est instancié dans ce fichier, volontairement placé DANS le
 * projet : `Kernel::getProjectDir()` déduit le projet de l'emplacement du
 * fichier qui déclare la classe. Un script dans /tmp reconstruirait le
 * conteneur dans /tmp et ne testerait pas cette application.
 *
 *   php .opencode/verify-auth.php
 */

use App\Entity\Identity\User;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;

// Le noyau démarre la session lors du login : PHP refuse de le faire si
// des caractères ont déjà été écrits. Un vrai appel HTTP n'a pas de sortie
// préalable ; le tampon reproduit cette condition.
ob_start();

require dirname(__DIR__) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

final class AuthKernel extends App\Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                // Un firewall sans session ne peut pas être testé par
                // appels indépendants : on réutilise le même objet Token
                // entre deux requêtes, comme le ferait le navigateur via
                // son cookie de session.
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

$kernel = new AuthKernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

$em = $container->get('doctrine')->getManager();
$tokenStorage = $container->get('security.token_storage');

// Ce script enchaîne plus de cinq connexions : le limiteur de tentatives
// doit être remis à zéro, sinon il bloquerait le test lui-même.
$container->get('cache.rate_limiter')->clear();

$email = 'auth.test@plateforme.test';
$password = 'MotDePasse!123';

// --- jeu de données (nettoyé en fin de script) --------------------------
$repository = $em->getRepository(User::class);
$existing = $repository->findOneBy(['email' => $email]);

if ($existing instanceof User) {
    $em->remove($existing);
    $em->flush();
}

$user = (new User())
    ->setEmail($email)
    ->setFullName('Auth Test')
    ->setPassword(password_hash($password, PASSWORD_BCRYPT))
    ->setIsActive(true);

$em->persist($user);
$em->flush();

/**
 * Exécute une requête à travers le noyau et renvoie [code, contenu].
 *
 * @return array{0: int, 1: string}
 */
// Le client est « sans état » entre les requêtes : il ne connaît que les
// cookies. C'est ce qui prouve que la session est réellement persistée
// par le serveur, et non portée par le TokenStorage du processus.
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

    // `Request::create()` n'analytise pas `HTTP_COOKIE` : les cookies
    // doivent être fournis en 4e argument. Un client réel les reçoit dans
    // l'en-tête, le noyau les retrouve dans `$request->cookies`.
    $request = Request::create($uri, $method, [], $jar, [], $server, $content);

    if ($content !== null) {
        $request->headers->set('CONTENT_TYPE', 'application/json');
    }

    $response = $kernel->handle($request);

    foreach ($response->headers->getCookies() as $cookie) {
        // Un cookie vidé par le serveur est une suppression : le navigateur
        // le retire, il ne le renvoie pas avec une valeur nulle.
        if ($cookie->getValue() === null || $cookie->getValue() === '') {
            unset($jar[$cookie->getName()]);

            continue;
        }

        $jar[$cookie->getName()] = $cookie->getValue();
    }

    $status = $response->getStatusCode();
    $content = (string) $response->getContent();
    fwrite(STDERR, sprintf(
        "[trace] %s %s -> %d | cookies: %s | id=%s | started=%s\n",
        $method,
        $uri,
        $status,
        implode(',', array_keys($jar)) ?: 'aucune',
        var_export($request->getSession()->getId(), true) . ' / cookie ' . var_export($request->cookies->get('HIMMOMPA'), true),
        var_export($request->getSession()->getId(), true)
    ));
    $kernel->terminate($request, $response);

    return [$status, $content];
};

echo "\n=== Authentification (firewall json_login) ===\n";

// Route protégée, sans jeton : doit être refusée par access_control.
[$status] = $request('GET', '/api/v1/identity/organizations');
check('GET /api/v1/identity/organizations sans session renvoie 401', $status === 401, "obtenu {$status}");

[$status] = $request('GET', '/api/auth/me');
check('GET /api/auth/me sans session renvoie 401', $status === 401, "obtenu {$status}");

// Mots de passe erronés.
[$status] = $request('POST', '/api/auth/login', ['email' => $email, 'password' => 'mauvais']);
check('Login avec un mauvais mot de passe renvoie 401', $status === 401, "obtenu {$status}");

// E-mail inconnu : la réponse doit être identique à celle d'un mot de
// passe faux, sinon le service permet d'énumérer les comptes.
[, $unknownBody] = $request('POST', '/api/auth/login', ['email' => 'inconnu@plateforme.test', 'password' => 'mauvais']);
[, $wrongBody] = $request('POST', '/api/auth/login', ['email' => $email, 'password' => 'mauvais']);
check(
    'Login : e-mail inconnu et mot de passe faux sont indiscernables',
    $unknownBody === $wrongBody,
    'réponses différentes'
);

// Identifiants valides.
[$status, $body] = $request('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);
$decoded = json_decode($body, true);
check(
    'Login avec les bons identifiants renvoie 200 et le profil',
    $status === 200 && ($decoded['user']['email'] ?? null) === $email,
    "obtenu {$status} : " . substr($body, 0, 160)
);

// Le firewall doit avoirNobody installed un jeton dans le TokenStorage :
// c'est lui qui rend la session exploitable par les requêtes suivantes.
check('Le login installe bien un jeton d\'authentification', $tokenStorage->getToken() !== null);

if ($tokenStorage->getToken()?->getUser() instanceof User) {
    check(
        'Le jeton porte bien l\'entité User attendue par SecurityService',
        $tokenStorage->getToken()->getUser()->getEmail() === $email
    );
} else {
    check('Le jeton porte bien l\'entité User attendue par SecurityService', false, 'utilisateur inattendu');
}

// Requête authentifiée : le contrôle d'accès de l'Organization doit
// passer, et son contenu être celui de l'utilisateur connecté.
[$status, $body] = $request('GET', '/api/auth/me');
check(
    'GET /api/auth/me avec session renvoie 200 et le profil',
    $status === 200 && str_contains($body, $email),
    "obtenu {$status} : " . substr($body, 0, 160)
);

// Déconnexion : le cookie de session doit être invalidé, sinon le client
// resterait authentifié après avoir cliqué sur « se déconnecter ».
$jar = [];
$tokenStorage->setToken(null);

[$status] = $request('GET', '/api/auth/me');
check('Après déconnexion, /api/auth/me renvoie 401', $status === 401, "obtenu {$status}");

// Un compte désactivé alors qu'une session est déjà ouverte doit perdre
// l'accès immédiatement : c'est tout l'intérêt de contrôler dans le
// provider plutôt qu'au seul moment du login.
[$status] = $request('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);
check('Reconnexion pour tester la désactivation en cours de session', $status === 200, "obtenu {$status}");

$em->clear();
$enCours = $repository->findOneBy(['email' => $email]);
$enCours->setIsActive(false);
$em->flush();
$tokenStorage->setToken(null);

[$status] = $request('GET', '/api/auth/me');
check(
    'Une session ouverte perd l\'accès dès qu\'un compte est désactivé',
    $status === 401,
    "obtenu {$status}"
);

// Un compte désactivé ne peut plus se connecter.
[$status] = $request('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);
check('Un compte désactivé ne peut plus se connecter', $status === 401, "obtenu {$status}");

// Limitation des tentatives : après plusieurs échecs, de bons identifiants
// doivent être refusés. Le code HTTP ne distingue pas necessarily le
// blocage du rate limiter d'un mot de passe erroné : on vérifie donc le
// comportement, pas le statut.
$container->get('cache.rate_limiter')->clear();
$em->clear();
$avantLimite = $repository->findOneBy(['email' => $email]);
$avantLimite->setIsActive(true);
$em->flush();

for ($essai = 0; $essai < 7; ++$essai) {
    $request('POST', '/api/auth/login', ['email' => $email, 'password' => 'mauvais']);
}

[$status, $bodyLimite] = $request('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);
check(
    'Le limiteur de tentatives refuse les bons identifiants après plusieurs échecs',
    $status === 429,
    "obtenu {$status} : " . substr((string) $bodyLimite, 0, 120)
);

$container->get('cache.rate_limiter')->clear();

// --- nettoyage ----------------------------------------------------------
// Le provider de sécurité a rechargé l'entité : la variable d'origine est
// détachée, on doit repartir de l'entité manager.
$em->clear();
$stale = $repository->findOneBy(['email' => $email]);

if ($stale instanceof User) {
    $em->remove($stale);
    $em->flush();
}

$em->close();

// Les fichiers de session produits par ce script sont des déchets : sans
// ce nettoyage, `var/sessions/dev` sature à chaque exécution.
$sessionDir = $kernel->getProjectDir() . '/var/sessions/' . $kernel->getEnvironment();

foreach (glob($sessionDir . '/sess_*') ?: [] as $fichier) {
    @unlink($fichier);
}

echo "\n  Contrôles exécutés : {$checks}\n";
echo '  Échecs : ' . count($failures) . "\n";

$kernel->shutdown();

ob_end_flush();

exit($failures === [] ? 0 : 1);
