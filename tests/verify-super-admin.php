<?php

declare(strict_types=1);

/**
 * Vérifie l'amorçage de la plateforme : création du compte SUPER_ADMIN par
 * défaut et connexion réelle avec ce compte.
 *
 * Le compte created by `app:super-admin:create` est le point d'entrée
 * obligatoire de l'application : sans lui, `UserService::create()` et
 * `OrganizationService::create()` sont inaccessibles (ils exigent tous deux
 * un SUPER_ADMIN). Ce script exerce donc la commande via le vrai
 * `CommandTester`, puis traverse le pare-feu avec `POST /api/auth/login` :
 * c'est le seul moyen de prouver que le mot de passe produit par la
 * commande est réellement exploitable, et pas seulement écrit en base.
 *
 *   php tests/verify-super-admin.php
 */

use App\Command\CreateSuperAdminCommand;
use App\Entity\Identity\User;
use App\Enum\PlatformRole;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;

// Le noyau démarre la session lors du login : PHP refuse de le faire si
// des caractères ont déjà été écrits. Un vrai appel HTTP n'a pas de sortie
// préalable ; le tampon reproduit cette condition.
ob_start();

require dirname(__DIR__) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

final class SuperAdminKernel extends App\Kernel
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

$kernel = new SuperAdminKernel('dev', false);
$kernel->boot();
$container = $kernel->getContainer();

$em = $container->get('doctrine')->getManager();
$tokenStorage = $container->get('security.token_storage');
$repository = $em->getRepository(User::class);

// Ce script enchaîne plusieurs connexions : le limiteur de tentatives doit
// être remis à zéro, sinon il bloquerait le test lui-même.
$container->get('cache.rate_limiter')->clear();

$email = 'super.admin.test@plateforme.test';
$password = 'MotDePasseAdmin!2026';
$otherPassword = 'AutreMotDePasse!2026';

// --- jeu de données (nettoyé en fin de script) --------------------------
$existing = $repository->findOneBy(['email' => $email]);

if ($existing instanceof User) {
    $em->remove($existing);
    $em->flush();
}

$command = (new Application($kernel))->find('app:super-admin:create');

$run = static function (array $options) use ($command): array {
    $tester = new CommandTester($command);
    $status = $tester->execute($options, ['interactive' => false]);

    return [$status, $tester->getDisplay()];
};

echo "\n=== Création du compte administrateur par défaut ===\n";

// --- 1. Création avec un mot de passe explicite ---------------------------
[$status, $display] = $run([
    '--email' => $email,
    '--full-name' => 'Super Admin Test',
    '--password' => $password,
]);

check('La commande retourne un succès', $status === 0, "code {$status}, sortie: {$display}");
check('La commande annonce la création du compte', str_contains($display, 'SUPER_ADMIN créé'), $display);

$user = $repository->findOneBy(['email' => $email]);

check('Le compte est bien persisté', $user instanceof User);

if (!$user instanceof User) {
    echo "\nAbort : compte introuvable.\n";

    exit(1);
}

check('Le rôle plateforme est SUPER_ADMIN', $user->getPlatformRole() === PlatformRole::SUPER_ADMIN, $user->getPlatformRole()?->value ?? 'null');
check('Le compte est actif', $user->isActive() === true);
check('Le compte n\'est pas supprimé logiquement', $user->isDeleted() === false);
check('Le nom complet est enregistré', $user->getFullName() === 'Super Admin Test', $user->getFullName());
check('Le mot de passe n\'est PAS stocké en clair', $user->getPassword() !== $password, (string) $user->getPassword());
check(
    'Le mot de passe est haché (bcrypt/argon)',
    is_string($user->getPassword()) && preg_match('/^\$(2y|argon2id)\$/', $user->getPassword()) === 1,
    substr((string) $user->getPassword(), 0, 12)
);
check('Le rôle SUPER_ADMIN est bien exposé par getRoles()', in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true), implode(',', $user->getRoles()));

// --- 2. Connexion réelle via le pare-feu ----------------------------------
// Le client ne conserve que le jeton renvoyé par le login.
$jeton = null;

$request = static function (string $method, string $uri, ?array $json = null) use ($kernel, &$jeton): array {
    $server = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'HTTP_HOST' => 'localhost'];

    if ($jeton !== null) {
        $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $jeton;
    }

    $content = $json !== null ? json_encode($json, JSON_THROW_ON_ERROR) : null;
    $request = Request::create($uri, $method, [], [], [], $server, $content);

    if ($content !== null) {
        $request->headers->set('CONTENT_TYPE', 'application/json');
    }

    $response = $kernel->handle($request);

    $status = $response->getStatusCode();
    $body = (string) $response->getContent();
    $kernel->terminate($request, $response);

    return [$status, $body];
};

echo "\n=== Connexion avec le compte administrateur par défaut ===\n";

[$status, $body] = $request('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);

check('POST /api/auth/login renvoie 200', $status === 200, "obtenu {$status} : {$body}");

$decoded = json_decode($body, true);
$jeton = is_string($decoded['accessToken'] ?? null) ? $decoded['accessToken'] : null;

check('La réponse expose l\'email du super admin', ($decoded['user']['email'] ?? null) === $email, $body);
check(
    'La réponse expose le rôle de plateforme super_admin',
    ($decoded['user']['platformRole'] ?? null) === PlatformRole::SUPER_ADMIN->value,
    $body
);
check('La réponse ne contient jamais le mot de passe', !str_contains($body, 'password'), $body);
check('Un jeton d\'API est émis dans accessToken', $jeton !== null, 'accessToken absent');

// Le jeton doit être réutilisable : c'est lui qui porte le rôle.
[$status] = $request('GET', '/api/auth/me');
check('GET /api/auth/me renvoie 200 avec le jeton', $status === 200, "obtenu {$status}");

// --- 3. Mauvais mot de passe refusé ---------------------------------------
$container->get('cache.rate_limiter')->clear();
[$status] = $request('POST', '/api/auth/login', ['email' => $email, 'password' => 'mauvais']);
check('Un mot de passe erroné renvoie 401', $status === 401, "obtenu {$status}");

// --- 4. Idempotence : la commande réinitialise le mot de passe ------------
echo "\n=== Réinitialisation du mot de passe ===\n";

$container->get('cache.rate_limiter')->clear();

$em->clear();
$hashBefore = (string) ($repository->findOneBy(['email' => $email])?->getPassword());

[$status, $display] = $run([
    '--email' => $email,
    '--full-name' => 'Super Admin Test',
    '--password' => $otherPassword,
]);

check('La réinitialisation retourne un succès', $status === 0, "code {$status}, sortie: {$display}");
check('La commande annonce la réinitialisation', str_contains($display, 'réinitialisé'), $display);

$em->clear();
$user = $repository->findOneBy(['email' => $email]);

check('Le compte existe toujours (pas de doublon)', $user instanceof User);
check(
    'Le hachage a bien changé en base',
    $user instanceof User && (string) $user->getPassword() !== $hashBefore,
    'hachage inchangé'
);
check(
    'Le rôle reste SUPER_ADMIN après réinitialisation',
    $user instanceof User && $user->getPlatformRole() === PlatformRole::SUPER_ADMIN,
    $user instanceof User ? ($user->getPlatformRole()?->value ?? 'null') : 'absent'
);

[$status] = $request('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);
check('L\'ancien mot de passe est refusé après réinitialisation', $status === 401, "obtenu {$status}");

$container->get('cache.rate_limiter')->clear();
[$status, $body] = $request('POST', '/api/auth/login', ['email' => $email, 'password' => $otherPassword]);
check('Le nouveau mot de passe fonctionne', $status === 200, "obtenu {$status} : {$body}");

// --- 5. Garde-fous de la commande ----------------------------------------
echo "\n=== Garde-fous ===\n";

$container->get('cache.rate_limiter')->clear();

[$status, $display] = $run(['--password' => 'Court1!']);
check('Sans email, la commande échoue', $status !== 0, "code {$status}");

[$status, $display] = $run(['--email' => 'pas-un-email', '--password' => $password]);
check('Un email invalide est rejeté', $status !== 0 && str_contains($display, 'valide'), $display);

[$status, $display] = $run(['--email' => $email, '--password' => 'TropCourt1!']);
check('Un mot de passe trop court est rejeté', $status !== 0 && str_contains($display, '12 caractères'), $display);

$tokenStorage->setToken(null);

// Un compte métier ne doit pas être promu en SUPER_ADMIN sans --force :
// cela contournerait l'isolation multi-tenant.
$em->clear();
$promoted = (new User())
    ->setEmail('patron.test@plateforme.test')
    ->setFullName('Patron Test')
    ->setPassword($otherPassword)
    ->setIsActive(true);
$em->persist($promoted);
$em->flush();

[$status, $display] = $run(['--email' => $promoted->getEmail(), '--password' => $password]);
check('Un compte existant sans --force n\'est pas promu', $status !== 0 && str_contains($display, 'Refus'), $display);

$em->clear();
$stillPatron = $repository->findOneBy(['email' => $promoted->getEmail()]);
check(
    'Le rôle du compte métier est resté inchangé',
    $stillPatron instanceof User && $stillPatron->getPlatformRole() === null,
    $stillPatron instanceof User ? ($stillPatron->getPlatformRole()?->value ?? 'null') : 'absent'
);

// --- 6. Mot de passe généré aléatoirement ---------------------------------
echo "\n=== Mot de passe généré ===\n";

$em->clear();
$generated = (new User())->setEmail('generated.test@plateforme.test')->setFullName('Généré')->setIsActive(true);
$em->persist($generated);
$em->flush();
$em->remove($generated);
$em->flush();

[$status, $display] = $run(['--email' => 'generated.test@plateforme.test']);

check('La commande réussit sans --password', $status === 0, "code {$status}, sortie: {$display}");
check('La commande signale un mot de passe généré', str_contains($display, 'généré'), $display);
check('La commande rappelle de le conserver', str_contains($display, 'une fois'), $display);

preg_match('/Mot de passe\s+(\S+)\s+\(généré/', $display, $matches);
$generatedPassword = $matches[1] ?? '';
check('Un mot de passe est affiché', $generatedPassword !== '', $display);
check('Le mot de passe généré est assez long (>= 12)', strlen($generatedPassword) >= 12, 'longueur ' . strlen($generatedPassword));

$container->get('cache.rate_limiter')->clear();
[$status] = $request('POST', '/api/auth/login', ['email' => 'generated.test@plateforme.test', 'password' => $generatedPassword]);
check('Le mot de passe généré permet de se connecter', $status === 200, "obtenu {$status}");

// --- nettoyage ------------------------------------------------------------
$em->clear();

foreach ([$email, $promoted->getEmail(), 'generated.test@plateforme.test'] as $address) {
    $toRemove = $repository->findOneBy(['email' => $address]);

    if ($toRemove instanceof User) {
        $em->remove($toRemove);
    }
}

$em->flush();

echo "\n" . str_repeat('-', 60) . "\n";

if ($failures !== []) {
    echo "ECHEC : " . count($failures) . " / {$checks} contrôles en échec\n";

    foreach ($failures as $failure) {
        echo "  - {$failure}\n";
    }

    exit(1);
}

echo "SUCCES : {$checks} contrôles passés\n";
exit(0);
