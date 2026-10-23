<?php

declare(strict_types=1);

/**
 * Parcours isolé ADMIN_VILLE → ADMIN_IMMOBILIER jusqu’à la publication.
 *
 * Usage : LISTING_TEST_DATABASE_URL=<URL de gestion_himmo_security_test> php tests/verify-listing-workflow.php
 * Toutes les écritures sont annulées à la fin ; aucune donnée existante n’est vidée.
 */

use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationUser;
use App\Entity\Identity\User;
use App\Entity\Identity\UserCity;
use App\Entity\Property\Building;
use App\Entity\Property\City;
use App\Entity\Property\Parcel;
use App\Entity\Property\Unit;
use App\Entity\Property\UnitPhoto;
use App\Enum\BuildingType;
use App\Enum\CityStatus;
use App\Enum\Currency;
use App\Enum\OrganizationRole;
use App\Enum\OrganizationStatus;
use App\Enum\UnitOperationalStatus;
use App\Enum\UnitType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

if (($testUrl = getenv('LISTING_TEST_DATABASE_URL')) !== false) {
    $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $testUrl;
    putenv('DATABASE_URL=' . $testUrl);
}

final class ListingWorkflowTestKernel extends App\Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach (['doctrine', 'security.token_storage'] as $id) {
                    if ($container->hasDefinition($id)) $container->getDefinition($id)->setPublic(true);
                }
            }
        });
    }
}

$kernel = new ListingWorkflowTestKernel('dev', false);
$kernel->boot();
$container = $kernel->getContainer();
/** @var EntityManagerInterface $em */
$em = $container->get('doctrine')->getManager();
$connection = $em->getConnection();
$database = (string) $connection->fetchOne('SELECT DATABASE()');
if ($database !== 'gestion_himmo_security_test') {
    fwrite(STDERR, "Refus : ce test est réservé à gestion_himmo_security_test (base détectée : {$database}).\n");
    exit(2);
}
$connection->beginTransaction();
$rollback = static fn () => $connection->isTransactionActive() ? $connection->rollBack() : null;
register_shutdown_function($rollback);

$checks = 0;
$failures = [];
$check = static function (string $label, bool $passed, string $detail = '') use (&$checks, &$failures): void {
    $checks++;
    echo sprintf("  [%s] %s%s\n", $passed ? 'OK' : 'FAIL', $label, $detail === '' ? '' : ' — ' . $detail);
    if (!$passed) $failures[] = $label;
};

$suffix = bin2hex(random_bytes(5));
$password = 'Listing-Test-' . $suffix . '!';
$organization = (new Organization())
    ->setName('Organisation test annonces ' . $suffix)
    ->setCode('LST-' . strtoupper($suffix))
    ->setEmail('listing-' . $suffix . '@test.local')
    ->setPhone('+000')
    ->setSlug('listing-' . $suffix)
    ->setShowcaseEnabled(true)
    ->setStatus(OrganizationStatus::ACTIVE);
$adminVille = (new User())->setEmail('ville-' . $suffix . '@test.local')->setFullName('Admin Ville test')->setPassword(password_hash($password, PASSWORD_BCRYPT))->setIsActive(true);
$adminImmobilier = (new User())->setEmail('immo-' . $suffix . '@test.local')->setFullName('Admin Immobilier test')->setPassword(password_hash($password, PASSWORD_BCRYPT))->setIsActive(true);
$city = (new City())->setName('Ville test annonces')->setCode('V-' . strtoupper($suffix))->setCountry('CD')->setStatus(CityStatus::ACTIVE)->setOrganization($organization);
$parcel = (new Parcel())->setReference('P-' . $suffix)->setName('Parcelle test')->setAddress('Adresse test')->setArea('100.00')->setCity($city);
$building = (new Building())->setReference('B-' . $suffix)->setName('Bâtiment test')->setType(BuildingType::MIXED)->setParcel($parcel);
$unit = (new Unit())->setReference('U-' . $suffix)->setBuilding($building)->setType(UnitType::APARTMENT)->setOperationalStatus(UnitOperationalStatus::AVAILABLE)->setFloor(1)->setSurface('60.00')->setMonthlyRent('500.00')->setCurrency(Currency::USD);
$secondCity = (new City())->setName('Ville secondaire test')->setCode('V2-' . strtoupper($suffix))->setCountry('CD')->setStatus(CityStatus::ACTIVE)->setOrganization($organization);
$secondParcel = (new Parcel())->setReference('P2-' . $suffix)->setName('Parcelle secondaire')->setAddress('Adresse secondaire')->setArea('80.00')->setCity($secondCity);
$secondBuilding = (new Building())->setReference('B2-' . $suffix)->setName('Bâtiment secondaire')->setType(BuildingType::MIXED)->setParcel($secondParcel);
$secondUnit = (new Unit())->setReference('U2-' . $suffix)->setBuilding($secondBuilding)->setType(UnitType::APARTMENT)->setOperationalStatus(UnitOperationalStatus::AVAILABLE)->setFloor(1)->setSurface('45.00')->setMonthlyRent('400.00')->setCurrency(Currency::USD);
foreach ([$organization, $adminVille, $adminImmobilier, $city, $parcel, $building, $unit, $secondCity, $secondParcel, $secondBuilding, $secondUnit] as $entity) $em->persist($entity);
$em->persist((new OrganizationUser())->setUser($adminVille)->setOrganization($organization)->setRole(OrganizationRole::ADMIN_VILLE));
$em->persist((new OrganizationUser())->setUser($adminImmobilier)->setOrganization($organization)->setRole(OrganizationRole::ADMIN_IMMOBILIER));
$em->persist((new UserCity())->setUser($adminVille)->setCity($city));
$em->persist((new UserCity())->setUser($adminVille)->setCity($secondCity));
$em->flush();

$request = static function (string $method, string $uri, ?array $body = null, ?string $token = null) use ($kernel): array {
    $server = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'HTTP_HOST' => 'localhost'];
    if ($token !== null) $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
    $content = $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR);
    $request = Request::create($uri, $method, [], [], [], $server, $content);
    if ($content !== null) $request->headers->set('CONTENT_TYPE', 'application/json');
    $response = $kernel->handle($request);
    $status = $response->getStatusCode();
    $payload = json_decode((string) $response->getContent(), true) ?: [];
    $kernel->terminate($request, $response);
    return [$status, $payload];
};
$login = static function (string $email) use ($request, $password): ?string {
    [$status, $payload] = $request('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);
    return $status === 200 && is_string($payload['accessToken'] ?? null) ? $payload['accessToken'] : null;
};
$cityToken = $login($adminVille->getEmail());
$immoToken = $login($adminImmobilier->getEmail());
$check('connexion du compte ADMIN_VILLE de test', is_string($cityToken));
$check('connexion du compte ADMIN_IMMOBILIER de test', is_string($immoToken));

[$missingCityStatus] = $request('GET', '/api/v1/organizations/' . $organization->getUuid()->toRfc4122() . '/listing-candidates', null, $cityToken);
$check('ADMIN_VILLE doit sélectionner une ville active avant de charger les biens candidats', $missingCityStatus === 400, 'HTTP ' . $missingCityStatus);
[$candidateStatus, $candidatePayload] = $request('GET', '/api/v1/organizations/' . $organization->getUuid()->toRfc4122() . '/listing-candidates?cityUuid=' . $city->getUuid()->toRfc4122(), null, $cityToken);
$candidates = $candidatePayload['data'] ?? [];
[$secondCandidateStatus, $secondCandidatePayload] = $request('GET', '/api/v1/organizations/' . $organization->getUuid()->toRfc4122() . '/listing-candidates?cityUuid=' . $secondCity->getUuid()->toRfc4122(), null, $cityToken);
$secondCandidates = $secondCandidatePayload['data'] ?? [];
$check('ADMIN_VILLE voit uniquement les unités candidates de la ville active', $candidateStatus === 200 && count($candidates) === 1 && ($candidates[0]['uuid'] ?? null) === $unit->getUuid()->toRfc4122(), 'HTTP ' . $candidateStatus);
$check('changer de ville active change la liste de biens candidats', $secondCandidateStatus === 200 && count($secondCandidates) === 1 && ($secondCandidates[0]['uuid'] ?? null) === $secondUnit->getUuid()->toRfc4122(), 'HTTP ' . $secondCandidateStatus);
[$immoCandidateStatus] = $request('GET', '/api/v1/organizations/' . $organization->getUuid()->toRfc4122() . '/listing-candidates', null, $immoToken);
$check('ADMIN_IMMOBILIER ne crée pas de brouillon via la liste de sélection', $immoCandidateStatus === 403, 'HTTP ' . $immoCandidateStatus);

$draft = ['title' => 'Appartement test ' . $suffix, 'description' => 'Annonce créée par le test du circuit de publication.', 'offerType' => 'rent', 'price' => '500.00', 'currency' => 'USD'];
[$status, $created] = $request('POST', '/api/v1/units/' . $unit->getUuid()->toRfc4122() . '/listing', $draft, $cityToken);
$listingUuid = $created['data']['uuid'] ?? null;
$check('ADMIN_VILLE crée le brouillon dans sa ville attribuée', $status === 201 && is_string($listingUuid), 'HTTP ' . $status);

if (is_string($listingUuid) && is_string($cityToken) && is_string($immoToken)) {
    [$status, $cityQueue] = $request('GET', '/api/v1/organizations/' . $organization->getUuid()->toRfc4122() . '/listings?cityUuid=' . $city->getUuid()->toRfc4122(), null, $cityToken);
    $check('ADMIN_VILLE consulte sa file d’annonces sans erreur 500', $status === 200 && count($cityQueue['data']['items'] ?? []) === 1, 'HTTP ' . $status);
    [$secondCityQueueStatus, $secondCityQueue] = $request('GET', '/api/v1/organizations/' . $organization->getUuid()->toRfc4122() . '/listings?cityUuid=' . $secondCity->getUuid()->toRfc4122(), null, $cityToken);
    $check('la file ADMIN_VILLE de la seconde ville ne reçoit pas les annonces de la première', $secondCityQueueStatus === 200 && ($secondCityQueue['data']['items'] ?? []) === [], 'HTTP ' . $secondCityQueueStatus);
    [$status] = $request('POST', '/api/v1/listings/' . $listingUuid . '/submit', null, $cityToken);
    $check('une annonce sans photo ne peut pas être soumise', $status === 409, 'HTTP ' . $status);
    $em->persist(new UnitPhoto($unit, 'units/' . $unit->getUuid()->toRfc4122() . '/couverture-test.jpg'));
    $em->flush();
    [$status] = $request('POST', '/api/v1/listings/' . $listingUuid . '/submit', null, $cityToken);
    $check('ADMIN_VILLE soumet l’annonce au contrôle', $status === 200, 'HTTP ' . $status);
    [$status, $immoQueue] = $request('GET', '/api/v1/organizations/' . $organization->getUuid()->toRfc4122() . '/listings', null, $immoToken);
    $check('ADMIN_IMMOBILIER voit la soumission dans sa file', $status === 200 && ($immoQueue['data']['items'][0]['status'] ?? null) === 'pending_review', 'HTTP ' . $status);
    [$status] = $request('POST', '/api/v1/listings/' . $listingUuid . '/queue-publication', null, $immoToken);
    $check('ADMIN_IMMOBILIER valide l’annonce pour publication', $status === 200, 'HTTP ' . $status);
    [$status] = $request('POST', '/api/v1/listings/' . $listingUuid . '/publish', null, $immoToken);
    $check('ADMIN_IMMOBILIER publie l’annonce validée', $status === 200, 'HTTP ' . $status);
    [$status, $published] = $request('GET', '/api/v1/listings/' . $listingUuid, null, $immoToken);
    $check('l’annonce publiée expose le statut et la date de publication', $status === 200 && ($published['data']['status'] ?? null) === 'published' && is_string($published['data']['publishedAt'] ?? null), 'HTTP ' . $status);
    [$publicStatus, $publicListings] = $request('GET', '/api/public/organizations/' . $organization->getSlug() . '/listings');
    $publicIds = array_column($publicListings['data']['items'] ?? [], 'listingId');
    $check('l’annonce publiée est visible dans la vitrine publique', $publicStatus === 200 && in_array($listingUuid, $publicIds, true), 'HTTP ' . $publicStatus);
}

$rollback();
echo PHP_EOL . 'Base : ' . $database . ' — transaction annulée.' . PHP_EOL;
echo $failures === [] ? "SUCCÈS : {$checks} contrôles passés.\n" : 'ÉCHEC : ' . count($failures) . " contrôle(s) sur {$checks}.\n";
$kernel->shutdown();
exit($failures === [] ? 0 : 1);
