<?php

declare(strict_types=1);

use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationUser;
use App\Entity\Identity\User;
use App\Enum\OrganizationRole;
use App\Enum\OrganizationStatus;
use App\Enum\PlatformRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
if (($testDatabaseUrl = getenv('PUBLICATION_TEST_DATABASE_URL')) !== false) {
    $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $testDatabaseUrl;
    putenv('DATABASE_URL='.$testDatabaseUrl);
}

final class PublicationTestKernel extends App\Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach ($container->getDefinitions() as $id => $definition) {
                    if (str_starts_with($id, 'App\\Repository\\') || str_starts_with($id, 'App\\Service\\') || in_array($id, ['doctrine', 'cache.rate_limiter'], true)) $definition->setPublic(true);
                }
            }
        });
    }
}

$kernel = new PublicationTestKernel('dev', false);
$kernel->boot();
$container = $kernel->getContainer();
/** @var EntityManagerInterface $em */
$em = $container->get('doctrine')->getManager();
$connection = $em->getConnection();
$database = (string) $connection->fetchOne('SELECT DATABASE()');
if ($database !== 'gestion_himmo_security_test') { fwrite(STDERR, "Refus : ce script n'accepte que gestion_himmo_security_test.\n"); exit(2); }
$connection->beginTransaction();
$rollback = static fn () => $connection->isTransactionActive() ? $connection->rollBack() : null;
register_shutdown_function($rollback);

$checks = 0; $failures = [];
$check = static function (string $name, bool $passed) use (&$checks, &$failures): void { $checks++; echo sprintf("[%s] %s\n", $passed ? 'OK' : 'FAIL', $name); if (!$passed) $failures[] = $name; };
$suffix = bin2hex(random_bytes(4));
$organization = (new Organization())->setName('Publication Test')->setCode('PUB-'.$suffix)->setEmail('pub-'.$suffix.'@test.local')->setPhone('+000')->setStatus(OrganizationStatus::ACTIVE);
$superAdmin = (new User())->setEmail('super-'.$suffix.'@test.local')->setFullName('Super Test')->setPassword(password_hash('Test-Publication-123!', PASSWORD_BCRYPT))->setPlatformRole(PlatformRole::SUPER_ADMIN)->setIsActive(true);
$patron = (new User())->setEmail('patron-'.$suffix.'@test.local')->setFullName('Patron Test')->setPassword(password_hash('Test-Publication-123!', PASSWORD_BCRYPT))->setIsActive(true);
$outsider = (new User())->setEmail('outside-'.$suffix.'@test.local')->setFullName('Outside Test')->setPassword(password_hash('Test-Publication-123!', PASSWORD_BCRYPT))->setIsActive(true);
foreach ([$organization, $superAdmin, $patron, $outsider] as $entity) $em->persist($entity);
$em->persist((new OrganizationUser())->setUser($patron)->setOrganization($organization)->setRole(OrganizationRole::PATRON));
$em->persist((new OrganizationUser())->setUser($superAdmin)->setOrganization($organization)->setRole(OrganizationRole::PATRON));
$em->flush();
$adminEmail = $superAdmin->getEmail(); $patronEmail = $patron->getEmail();
$em->clear();

/** @return array{int, array<string,mixed>} */
$request = static function (string $method, string $uri, ?array $json = null, ?string $bearer = null, array $parameters = [], array $files = []) use ($kernel): array {
    $server = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'HTTP_HOST' => 'localhost'];
    if ($bearer !== null) $server['HTTP_AUTHORIZATION'] = 'Bearer '.$bearer;
    $body = $json === null ? null : json_encode($json, JSON_THROW_ON_ERROR);
    $req = Request::create($uri, $method, $parameters, [], $files, $server, $body);
    if ($body !== null) $req->headers->set('CONTENT_TYPE', 'application/json');
    $response = $kernel->handle($req); $status = $response->getStatusCode(); $payload = json_decode((string) $response->getContent(), true) ?: [];
    $kernel->terminate($req, $response);
    return [$status, $payload];
};
$login = static function (string $email) use ($request): ?string { [$status, $payload] = $request('POST', '/api/auth/login', ['email' => $email, 'password' => 'Test-Publication-123!']); return $status === 200 ? ($payload['accessToken'] ?? null) : null; };
$adminToken = $login($adminEmail); $patronToken = $login($patronEmail);
$check('le SUPER_ADMIN test peut se connecter', is_string($adminToken));
$check('le PATRON test peut se connecter', is_string($patronToken));
if (!$adminToken || !$patronToken) { fwrite(STDERR, "Authentification des comptes éphémères impossible.\n"); exit(1); }

$base = ['title' => 'Annonce de test '.$suffix, 'content' => "Bienvenue\n- Première amélioration\n2. Suivi disponible", 'category' => 'improvement', 'audience' => 'roles', 'audienceRoles' => ['patron'], 'organizationUuid' => null, 'scheduledAt' => null, 'endsAt' => null];
$superAdminAudience = $base; $superAdminAudience['audienceRoles'] = ['super_admin'];
[$superAdminAudienceStatus] = $request('POST', '/api/v1/publications', $superAdminAudience, $adminToken);
$check('le rôle SUPER_ADMIN ne peut pas être ciblé par une publication', $superAdminAudienceStatus === 400);
[$status, $payload] = $request('POST', '/api/v1/publications', $base, $adminToken);
$draftUuid = $payload['data']['uuid'] ?? '';
$check('le SUPER_ADMIN crée un brouillon', $status === 201 && $draftUuid !== '');

// L'image carrée minuscule est un véritable PNG et reste supprimée après le test.
$invalidTmp = tempnam(sys_get_temp_dir(), 'publication-invalid-');
file_put_contents($invalidTmp, 'not an image');
$invalidUpload = new UploadedFile($invalidTmp, 'document.txt', 'text/plain', null, true);
[$invalidImageStatus] = $request('POST', '/api/v1/publications/'.$draftUuid.'/images', null, $adminToken, ['captions' => ['']], ['files' => [$invalidUpload]]);
$check('un fichier qui n’est pas une image est refusé en erreur client', $invalidImageStatus >= 400 && $invalidImageStatus < 500);
if (is_file($invalidTmp)) unlink($invalidTmp);

$tmp = tempnam(sys_get_temp_dir(), 'publication-');
file_put_contents($tmp, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/pWQAAAAASUVORK5CYII=', true));
$uploaded = new UploadedFile($tmp, 'pixel.png', 'image/png', null, true);
[$imageStatus, $imagePayload] = $request('POST', '/api/v1/publications/'.$draftUuid.'/images', null, $adminToken, ['captions' => ['Pixel de test']], ['files' => [$uploaded]]);
$imagePath = $imagePayload['data']['images'][0]['path'] ?? null;
$check('le PNG validé est associé, affichable et légendé', $imageStatus === 200 && is_string($imagePath) && str_starts_with($imagePayload['data']['images'][0]['url'] ?? '', '/uploads/publications/') && ($imagePayload['data']['images'][0]['caption'] ?? '') === 'Pixel de test');
if (is_string($imagePath)) { $stored = dirname(__DIR__).'/public/uploads/'.$imagePath; if (is_file($stored)) unlink($stored); }
if (is_file($tmp)) unlink($tmp);

[$publishStatus] = $request('POST', '/api/v1/publications/'.$draftUuid.'/publish', null, $adminToken);
$check('la publication est une action séparée du brouillon', $publishStatus === 200);
[$manageStatus, $managed] = $request('GET', '/api/v1/publications/management', null, $adminToken);
$managedBase = array_values(array_filter($managed['data'] ?? [], static fn (array $item): bool => ($item['uuid'] ?? null) === $draftUuid))[0] ?? [];
$check('le tableau de gestion expose les destinataires et consultations', $manageStatus === 200 && ($managedBase['recipientCount'] ?? 0) >= 1 && array_key_exists('viewCount', $managedBase) && array_key_exists('expiredCount', $managedBase));
[$activePatronStatus, $activePatron] = $request('GET', '/api/v1/publications/active', null, $patronToken);
$check('les destinataires ciblés voient la notification', $activePatronStatus === 200 && count($activePatron['data'] ?? []) === 1);
[$activeSuperAdminStatus, $activeSuperAdmin] = $request('GET', '/api/v1/publications/active', null, $adminToken);
$check('un compte SUPER_ADMIN ne reçoit jamais de publication', $activeSuperAdminStatus === 200 && count($activeSuperAdmin['data'] ?? []) === 0);
[$openSuperAdminStatus] = $request('GET', '/api/v1/publications/'.$draftUuid, null, $adminToken);
$check('un SUPER_ADMIN ne peut pas ouvrir directement une publication métier', $openSuperAdminStatus === 404);
$outsiderToken = $login('outside-'.$suffix.'@test.local');
[$activeOutsideStatus, $activeOutside] = $request('GET', '/api/v1/publications/active', null, $outsiderToken);
$check('un utilisateur non ciblé ne reçoit pas la publication', $activeOutsideStatus === 200 && count($activeOutside['data'] ?? []) === 0);

$organizationDraft = $base; $organizationDraft['title'] .= ' organisation'; $organizationDraft['audience'] = 'organization'; $organizationDraft['audienceRoles'] = []; $organizationDraft['organizationUuid'] = $organization->getUuid()->toRfc4122();
[$organizationDraftStatus, $organizationDraftPayload] = $request('POST', '/api/v1/publications', $organizationDraft, $adminToken);
$organizationDraftUuid = $organizationDraftPayload['data']['uuid'] ?? '';
[$organizationPublishStatus] = $request('POST', '/api/v1/publications/'.$organizationDraftUuid.'/publish', null, $adminToken);
[$organizationActiveStatus, $organizationActive] = $request('GET', '/api/v1/publications/active', null, $patronToken);
[$organizationOutsideStatus, $organizationOutside] = $request('GET', '/api/v1/publications/active', null, $outsiderToken);
[$organizationSuperAdminStatus, $organizationSuperAdmin] = $request('GET', '/api/v1/publications/active', null, $adminToken);
$check('l’audience organisation respecte l’appartenance sans inclure SUPER_ADMIN', $organizationDraftStatus === 201 && $organizationPublishStatus === 200 && count($organizationActive['data'] ?? []) === 2 && count($organizationOutside['data'] ?? []) === 0 && $organizationSuperAdminStatus === 200 && count($organizationSuperAdmin['data'] ?? []) === 0);

$allDraft = $base; $allDraft['title'] .= ' plateforme'; $allDraft['audience'] = 'all'; $allDraft['audienceRoles'] = [];
[$allDraftStatus, $allDraftPayload] = $request('POST', '/api/v1/publications', $allDraft, $adminToken);
$allUuid = $allDraftPayload['data']['uuid'] ?? '';
[$allPublishStatus] = $request('POST', '/api/v1/publications/'.$allUuid.'/publish', null, $adminToken);
[$allOutsideStatus, $allOutside] = $request('GET', '/api/v1/publications/active', null, $outsiderToken);
$allSuperAdmin = $request('GET', '/api/v1/publications/active', null, $adminToken);
$check('l’audience globale atteint les comptes métier sans le SUPER_ADMIN', $allDraftStatus === 201 && $allPublishStatus === 200 && count($allOutside['data'] ?? []) === 1 && $allSuperAdmin[0] === 200 && count($allSuperAdmin[1]['data'] ?? []) === 0);
[$archiveStatus] = $request('POST', '/api/v1/publications/'.$allUuid.'/archive', null, $adminToken);
[$archivedActiveStatus, $archivedActive] = $request('GET', '/api/v1/publications/active', null, $outsiderToken);
$check('une publication archivée cesse d’être diffusée', $archiveStatus === 200 && $archivedActiveStatus === 200 && count($archivedActive['data'] ?? []) === 0);

// Le détail est le seul chemin qui enregistre une première lecture; le second GET ne la réinitialise pas.
[$detail1Status, $detail1] = $request('GET', '/api/v1/publications/'.$draftUuid, null, $patronToken);
$firstRead = $detail1['data']['firstViewedAt'] ?? null;
[$detail2Status, $detail2] = $request('GET', '/api/v1/publications/'.$draftUuid, null, $patronToken);
$check('une première ouverture enregistre la consultation'.($detail1Status === 200 ? '' : ' (HTTP '.$detail1Status.' '.($detail1['message'] ?? $detail1['flushDescription'] ?? '').')'), $detail1Status === 200 && is_string($firstRead));
$check('une lecture répétée ne réinitialise pas le délai'.($detail2Status === 200 ? '' : ' (HTTP '.$detail2Status.' '.($detail2['message'] ?? $detail2['flushDescription'] ?? '').')'), $detail2Status === 200 && ($detail2['data']['firstViewedAt'] ?? null) === $firstRead);

$request('POST', '/api/auth/logout', null, $patronToken);
$patronAgain = $login($patronEmail);
[$reloginStatus, $reloginItems] = $request('GET', '/api/v1/publications/active', null, $patronAgain);
$persistedRead = array_values(array_filter($reloginItems['data'] ?? [], static fn (array $item): bool => ($item['uuid'] ?? null) === $draftUuid))[0]['firstViewedAt'] ?? null;
$check('l’état individuel persiste après déconnexion et reconnexion', $reloginStatus === 200 && $persistedRead === $firstRead);
$patronToken = $patronAgain;

[$forbiddenCreate] = $request('POST', '/api/v1/publications', $base, $patronToken);
[$forbiddenEdit] = $request('PUT', '/api/v1/publications/'.$draftUuid, $base, $patronToken);
[$forbiddenPublish] = $request('POST', '/api/v1/publications/'.$draftUuid.'/publish', null, $patronToken);
$check('les comptes métier ne peuvent créer, modifier ou publier', $forbiddenCreate === 403 && $forbiddenEdit === 403 && $forbiddenPublish === 403);

[$withdrawStatus] = $request('POST', '/api/v1/publications/'.$draftUuid.'/withdraw', null, $adminToken);
[$afterWithdrawStatus, $afterWithdraw] = $request('GET', '/api/v1/publications/active', null, $patronToken);
$withdrawnStillVisible = array_filter($afterWithdraw['data'] ?? [], static fn (array $item): bool => ($item['uuid'] ?? null) === $draftUuid) !== [];
$check('une publication retirée disparaît immédiatement', $withdrawStatus === 200 && $afterWithdrawStatus === 200 && !$withdrawnStillVisible);

$rollback();
echo sprintf("\n%d scénarios API vérifiés ; transaction annulée.\n", $checks);
exit($failures === [] ? 0 : 1);
