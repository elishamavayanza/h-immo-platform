<?php

declare(strict_types=1);

/**
 * Vérifie le flux « mot de passe oublié » de bout en bout :
 * `POST /api/auth/forgot-password` -> email -> `POST /api/auth/reset-password`
 * -> connexion avec le nouveau mot de passe.
 *
 * Ce flux est l'unique moyen pour un PATRON créé sans mot de passe
 * d'accéder à la plateforme. Il est donc critique, et il a déjà été livré
 * cassé : `PasswordResetService` appelait des setters inexistants sur
 * `PasswordResetToken` (entité volontairement sans setters), ce qui
 * produisait un 500 sur le premier appel. Ce script rejoue précisément
 * ce scénario.
 *
 * Le jeton en clair n'étant jamais stocké (seul son condensat SHA-256
 * l'est), les tests qui doivent fournir un jeton connu l'insèrent
 * directement, en reproduisant à l'identique le condensat produit par
 * `requestReset()`. Un contrôle vérifie par ailleurs que le format de
 * condensat produit par le service est bien le même (64 hex).
 *
 *   php tests/verify-password-reset.php
 */

use App\Entity\Identity\PasswordResetToken;
use App\Entity\Identity\User;
use App\Entity\System\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;

ob_start();

require dirname(__DIR__) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

// `.env.local` pointe en general vers un maildev local (smtp://127.0.0.1:1025)
// qui n'existe pas en CI ni sur un poste de dev sans conteneur : le transport
// est neutralise pour que le script valide la logique metier (jeton, delai,
// reponse generique) et non la disponibilite d'un serveur SMTP. On ecrase donc
// le DSN *apres* bootEnv(), qui vient de charger .env.local.
$_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = 'null://null';
putenv('MAILER_DSN=null://null');

final class PasswordResetKernel extends App\Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach (['security.token_storage', 'cache.rate_limiter', 'App\\Service\\System\\AuditLogService'] as $id) {
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

$kernel = new PasswordResetKernel('dev', false);
$kernel->boot();
$container = $kernel->getContainer();

/** @var EntityManagerInterface $em */
$em = $container->get('doctrine')->getManager();
$repository = $em->getRepository(User::class);
$tokenRepository = $em->getRepository(PasswordResetToken::class);

$container->get('cache.rate_limiter')->clear();

$email = 'reset.test@plateforme.test';
$originalPassword = 'MotDePasseInitial!2026';
$newPassword = 'NouveauMotDePasse!2026';

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
    $request = Request::create($uri, $method, [], $jar, [], $server, $content);

    if ($content !== null) {
        $request->headers->set('CONTENT_TYPE', 'application/json');
    }

    $response = $kernel->handle($request);

    foreach ($response->headers->getCookies() as $cookie) {
        if ($cookie->getValue() === null || $cookie->getValue() === '') {
            unset($jar[$cookie->getName()]);

            continue;
        }

        $jar[$cookie->getName()] = $cookie->getValue();
    }

    $status = $response->getStatusCode();
    $body = (string) $response->getContent();
    $kernel->terminate($request, $response);

    return [$status, $body];
};

/** Supprime le compte de test et tous ses jetons. */
$cleanup = static function () use ($em, $repository, $email): void {
    $em->clear();
    $user = $repository->findOneBy(['email' => $email]);

    if ($user instanceof User) {
        $em->createQuery('DELETE FROM ' . PasswordResetToken::class . ' t WHERE t.user = :user')
            ->setParameter('user', $user)
            ->execute();
        // Le journal d'audit référence l'utilisateur : sans ce DELETE, la
        // suppression du compte viole la contrainte de clé étrangère
        // audit_log.user_id -> user.id (1451) et le script s'arrête en 255.
        $em->createQuery('DELETE FROM ' . AuditLog::class . ' a WHERE a.user = :user')
            ->setParameter('user', $user)
            ->execute();
        $em->remove($user);
        $em->flush();
    }
};

$cleanup();

$createUser = static function (bool $active = true, bool $deleted = false) use ($em, $repository, $email, $originalPassword): User {
    $user = (new User())
        ->setEmail($email)
        ->setFullName('Reset Test')
        ->setPassword(password_hash($originalPassword, PASSWORD_BCRYPT))
        ->setIsActive($active);

    if ($deleted) {
        $user->softDelete();
    }

    $em->persist($user);
    $em->flush();

    return $user;
};

/** Insère un jeton dont le condensat est celui du jeton en clair fourni. */
$insertToken = static function (User $user, string $rawToken, string $expiresIn = '+1 hour') use ($em): PasswordResetToken {
    $token = new PasswordResetToken(hash('sha256', $rawToken), $user, new DateTimeImmutable($expiresIn));
    $em->persist($token);
    $em->flush();

    return $token;
};

$rawToken = static fn (): string => bin2hex(random_bytes(32));

// --- 1. Le cas qui plantait : 500 sur setTokenHash() ---------------------
echo "\n=== POST /api/auth/forgot-password ===\n";

$createUser();

[$status, $body] = $request('POST', '/api/auth/forgot-password', ['email' => $email]);

check('Le endpoint ne renvoie plus 500', $status !== 500, "obtenu {$status} : " . substr($body, 0, 200));
check('Le endpoint renvoie 200', $status === 200, "obtenu {$status} : " . substr($body, 0, 200));
check('Le message reste générique', str_contains($body, 'Si cette adresse existe'), $body);

$em->clear();
$tokens = $tokenRepository->findBy(['user' => $repository->findOneBy(['email' => $email])]);

check('Un jeton a bien été créé en base', count($tokens) === 1, count($tokens) . ' jeton(s)');
check(
    'Le condensat est un SHA-256 hexadécimal de 64 caractères',
    count($tokens) === 1 && preg_match('/^[0-9a-f]{64}$/', $tokens[0]->getTokenHash()) === 1,
    count($tokens) === 1 ? $tokens[0]->getTokenHash() : 'absent'
);
check(
    'Le jeton est utilisable (non consommé, non expiré)',
    count($tokens) === 1 && $tokens[0]->isUsable(),
    'inutilisable'
);

$expiry = count($tokens) === 1 ? $tokens[0]->getExpiresAt() : null;
$ttl = $expiry !== null ? $expiry->getTimestamp() - time() : 0;
check('Le jeton expire dans environ 1 heure', $ttl > 3300 && $ttl <= 3600, "TTL {$ttl}s");

// --- 2. Absence d'énumération de comptes ---------------------------------
echo "\n=== Anti-énumération ===\n";

$em->clear();
$tokenCountBefore = $tokenRepository->count([]);

[$statusUnknown, $bodyUnknown] = $request('POST', '/api/auth/forgot-password', ['email' => 'personne@plateforme.test']);

check('Un email inconnu renvoie 200', $statusUnknown === 200, "obtenu {$statusUnknown}");
check('La réponse est identique à celle d\'un email existant', $bodyUnknown === $body, 'réponses différentes');

$em->clear();
check(
    'Aucun jeton n\'est créé pour un email inconnu',
    $tokenRepository->count([]) === $tokenCountBefore,
    $tokenRepository->count([]) . ' jeton(s), attendu ' . $tokenCountBefore
);

// Compte désactivé : pas de jeton, pas de fuite.
$cleanup();
$createUser(active: false);

$em->clear();
$tokensBefore = $tokenRepository->count([]);
[$statusDisabled, $bodyDisabled] = $request('POST', '/api/auth/forgot-password', ['email' => $email]);
$em->clear();

check('Un compte désactivé renvoie 200', $statusDisabled === 200, "obtenu {$statusDisabled}");
check('La réponse est la même que pour un email inconnu', $bodyDisabled === $bodyUnknown, 'réponses différentes');
check('Aucun jeton n\'est créé pour un compte désactivé', $tokenRepository->count([]) === $tokensBefore, 'jeton créé');

// Validation d'entrée.
$cleanup();
$createUser();

[$status, $body] = $request('POST', '/api/auth/forgot-password', ['email' => 'pas-un-email']);
check('Un email invalide renvoie 422', $status === 422, "obtenu {$status}");

// --- 3. Une seule demande active à la fois -------------------------------
echo "\n=== Invalidation des demandes précédentes ===\n";

$first = $rawToken();
$insertToken($repository->findOneBy(['email' => $email]), $first);

[$status] = $request('POST', '/api/auth/forgot-password', ['email' => $email]);
check('Une nouvelle demande renvoie 200', $status === 200, "obtenu {$status}");

$em->clear();
$user = $repository->findOneBy(['email' => $email]);
$previous = $tokenRepository->findOneBy(['tokenHash' => hash('sha256', $first)]);

check('Le jeton précédent est marqué consommé', $previous instanceof PasswordResetToken && $previous->isConsumed(), 'non consommé');

// --- 4. POST /api/auth/reset-password ------------------------------------
echo "\n=== POST /api/auth/reset-password ===\n";

$token = $rawToken();
$insertToken($user, $token);

[$status, $body] = $request('POST', '/api/auth/reset-password', ['token' => $token, 'newPassword' => $newPassword]);

check('La réinitialisation renvoie 200', $status === 200, "obtenu {$status} : " . substr($body, 0, 200));
check(
    'Le message confirme la réinitialisation',
    str_contains((string) (json_decode($body, true)['flushDescription'] ?? ''), 'réinitialisé'),
    $body
);

$em->clear();
$user = $repository->findOneBy(['email' => $email]);
$hash = (string) $user->getPassword();

check('Le mot de passe a changé en base', $hash !== '' && password_verify($originalPassword, $hash) === false, 'mot de passe inchangé');
check('Le nouveau mot de passe est valide', password_verify($newPassword, $hash), 'nouveau mot de passe refusé');

$em->clear();
$consumed = $tokenRepository->findOneBy(['tokenHash' => hash('sha256', $token)]);
check('Le jeton est marqué consommé', $consumed instanceof PasswordResetToken && $consumed->isConsumed(), 'non consommé');

// Usage unique.
[$status, $body] = $request('POST', '/api/auth/reset-password', ['token' => $token, 'newPassword' => 'AutreMotDePasse!2026']);
check('Un jeton déjà consommé est refusé (422)', $status === 422, "obtenu {$status}");

$em->clear();
$user = $repository->findOneBy(['email' => $email]);
check('Le mot de passe n\'a pas été changé par la tentative répétée', password_verify($newPassword, (string) $user->getPassword()), 'mot de passe écrasé');

// Jeton inconnu.
[$status] = $request('POST', '/api/auth/reset-password', ['token' => $rawToken(), 'newPassword' => $newPassword]);
check('Un jeton inconnu est refusé (422)', $status === 422, "obtenu {$status}");

// Jeton expiré.
$em->clear();
$expired = $rawToken();
$insertToken($repository->findOneBy(['email' => $email]), $expired, '-1 hour');

[$status] = $request('POST', '/api/auth/reset-password', ['token' => $expired, 'newPassword' => $newPassword]);
check('Un jeton expiré est refusé (422)', $status === 422, "obtenu {$status}");

// Jeton mal formé (longueur invalide).
[$status] = $request('POST', '/api/auth/reset-password', ['token' => 'trop-court', 'newPassword' => $newPassword]);
check('Un jeton mal formé est refusé (422)', $status === 422, "obtenu {$status}");

// Mot de passe trop court.
$valid = $rawToken();
$insertToken($repository->findOneBy(['email' => $email]), $valid);

[$status] = $request('POST', '/api/auth/reset-password', ['token' => $valid, 'newPassword' => 'court']);
check('Un mot de passe trop court est refusé (422)', $status === 422, "obtenu {$status}");

$em->clear();
$user = $repository->findOneBy(['email' => $email]);
check('Le mot de passe n\'a pas été changé par une requête invalide', password_verify($newPassword, (string) $user->getPassword()), 'mot de passe écrasé');

// Compte supprimé logiquement.
$em->clear();
$deletedToken = $rawToken();
$deletedUser = $repository->findOneBy(['email' => $email]);
$deletedUser->softDelete();
$insertToken($deletedUser, $deletedToken);

[$status] = $request('POST', '/api/auth/reset-password', ['token' => $deletedToken, 'newPassword' => $newPassword]);
check('Un jeton d\'un compte supprimé est refusé (422)', $status === 422, "obtenu {$status}");

// --- 5. Connexion avec le nouveau mot de passe ---------------------------
echo "\n=== Connexion après réinitialisation ===\n";

$cleanup();
$createUser();

$finalToken = $rawToken();
$insertToken($repository->findOneBy(['email' => $email]), $finalToken);

[$status] = $request('POST', '/api/auth/reset-password', ['token' => $finalToken, 'newPassword' => $newPassword]);
check('La réinitialisation finale renvoie 200', $status === 200, "obtenu {$status}");

$container->get('cache.rate_limiter')->clear();
[$status, $body] = $request('POST', '/api/auth/login', ['email' => $email, 'password' => $originalPassword]);
check('L\'ancien mot de passe est refusé (401)', $status === 401, "obtenu {$status}");

$container->get('cache.rate_limiter')->clear();
[$status, $body] = $request('POST', '/api/auth/login', ['email' => $email, 'password' => $newPassword]);
check('Le nouveau mot de passe permet de se connecter (200)', $status === 200, "obtenu {$status} : " . substr($body, 0, 160));
check('Aucune fuite du mot de passe dans la réponse', !str_contains($body, $newPassword) && !str_contains($body, 'password'), $body);

$cleanup();

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
