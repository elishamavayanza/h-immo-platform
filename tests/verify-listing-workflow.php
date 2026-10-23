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
use App\Entity\Property\Listing;
use App\Entity\Property\Parcel;
use App\Entity\Property\Unit;
use App\Entity\Property\UnitPhoto;
use App\Entity\Property\CustomerInquiryNotification;
use App\Enum\BuildingType;
use App\Enum\CityStatus;
use App\Enum\Currency;
use App\Enum\ListingOfferType;
use App\Enum\ListingStatus;
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
$otherCityAdmin = (new User())->setEmail('ville-autre-' . $suffix . '@test.local')->setFullName('Admin Ville autre test')->setPassword(password_hash($password, PASSWORD_BCRYPT))->setIsActive(true);
$adminImmobilier = (new User())->setEmail('immo-' . $suffix . '@test.local')->setFullName('Admin Immobilier test')->setPassword(password_hash($password, PASSWORD_BCRYPT))->setIsActive(true);
$patron = (new User())->setEmail('patron-' . $suffix . '@test.local')->setFullName('Patron test')->setPassword(password_hash($password, PASSWORD_BCRYPT))->setIsActive(true);
$city = (new City())->setName('Ville test annonces')->setCode('V-' . strtoupper($suffix))->setCountry('CD')->setStatus(CityStatus::ACTIVE)->setOrganization($organization);
$parcel = (new Parcel())->setReference('P-' . $suffix)->setName('Parcelle test')->setAddress('Adresse test')->setArea('100.00')->setCity($city);
$building = (new Building())->setReference('B-' . $suffix)->setName('Bâtiment test')->setType(BuildingType::MIXED)->setParcel($parcel);
$unit = (new Unit())->setReference('U-' . $suffix)->setBuilding($building)->setType(UnitType::APARTMENT)->setOperationalStatus(UnitOperationalStatus::AVAILABLE)->setFloor(1)->setSurface('60.00')->setBedrooms(2)->setMonthlyRent('500.00')->setCurrency(Currency::USD);
$inactiveCity = (new City())->setName('Ville inactive test')->setCode('VI-' . strtoupper($suffix))->setCountry('CD')->setStatus(CityStatus::INACTIVE)->setOrganization($organization);
$otherOrganization = (new Organization())->setName('Autre organisation test ' . $suffix)->setCode('OTH-' . strtoupper($suffix))->setEmail('other-' . $suffix . '@test.local')->setPhone('+000')->setSlug('other-listing-' . $suffix)->setShowcaseEnabled(true)->setIsPubliclyListed(true)->setStatus(OrganizationStatus::ACTIVE);
$otherCity = (new City())->setName('Ville autre organisation')->setCode('O-' . strtoupper($suffix))->setCountry('CD')->setStatus(CityStatus::ACTIVE)->setOrganization($otherOrganization);
$otherParcel = (new Parcel())->setReference('OP-' . $suffix)->setName('Parcelle autre organisation')->setAddress('Adresse autre organisation')->setArea('70.00')->setCity($otherCity);
$otherBuilding = (new Building())->setReference('OB-' . $suffix)->setName('Bâtiment autre organisation')->setType(BuildingType::MIXED)->setParcel($otherParcel);
$otherUnit = (new Unit())->setReference('OU-' . $suffix)->setBuilding($otherBuilding)->setType(UnitType::HOUSE)->setOperationalStatus(UnitOperationalStatus::AVAILABLE)->setFloor(0)->setSurface('70.00')->setMonthlyRent('900.00')->setCurrency(Currency::USD);
$otherListing = (new Listing())->setUnit($otherUnit)->setCreatedBy($adminImmobilier)->setStatus(ListingStatus::PUBLISHED)->setTitle('Annonce autre organisation')->setDescription('Cette annonce ne doit pas traverser la frontière tenant.')->setOfferType(ListingOfferType::RENT)->setPrice('900.00')->setCurrency(Currency::USD)->setPublishedAt(new DateTimeImmutable())->setPublicSnapshot(['reference' => $otherUnit->getReference(), 'type' => 'house', 'floor' => 0, 'surface' => '70.00', 'city' => $otherCity->getName(), 'buildingName' => $otherBuilding->getName()]);
$otherUnit->setListing($otherListing);
$secondCity = (new City())->setName('Ville secondaire test')->setCode('V2-' . strtoupper($suffix))->setCountry('CD')->setStatus(CityStatus::ACTIVE)->setOrganization($organization);
$secondParcel = (new Parcel())->setReference('P2-' . $suffix)->setName('Parcelle secondaire')->setAddress('Adresse secondaire')->setArea('80.00')->setCity($secondCity);
$secondBuilding = (new Building())->setReference('B2-' . $suffix)->setName('Bâtiment secondaire')->setType(BuildingType::MIXED)->setParcel($secondParcel);
$secondUnit = (new Unit())->setReference('U2-' . $suffix)->setBuilding($secondBuilding)->setType(UnitType::APARTMENT)->setOperationalStatus(UnitOperationalStatus::AVAILABLE)->setFloor(1)->setSurface('45.00')->setMonthlyRent('400.00')->setCurrency(Currency::USD);
foreach ([$organization, $adminVille, $otherCityAdmin, $adminImmobilier, $patron, $city, $parcel, $building, $unit, $secondCity, $secondParcel, $secondBuilding, $secondUnit, $inactiveCity, $otherOrganization, $otherCity, $otherParcel, $otherBuilding, $otherUnit, $otherListing] as $entity) $em->persist($entity);
$em->persist((new OrganizationUser())->setUser($adminVille)->setOrganization($organization)->setRole(OrganizationRole::ADMIN_VILLE));
$em->persist((new OrganizationUser())->setUser($otherCityAdmin)->setOrganization($organization)->setRole(OrganizationRole::ADMIN_VILLE));
$em->persist((new OrganizationUser())->setUser($adminImmobilier)->setOrganization($organization)->setRole(OrganizationRole::ADMIN_IMMOBILIER));
$em->persist((new OrganizationUser())->setUser($patron)->setOrganization($organization)->setRole(OrganizationRole::PATRON));
$em->persist((new UserCity())->setUser($adminVille)->setCity($city));
$em->persist((new UserCity())->setUser($adminVille)->setCity($secondCity));
$em->persist((new UserCity())->setUser($otherCityAdmin)->setCity($secondCity));
$em->flush();

$request = static function (string $method, string $uri, ?array $body = null, ?string $token = null, ?string $idempotencyKey = null) use ($kernel): array {
    $server = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'HTTP_HOST' => 'localhost'];
    if ($token !== null) $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
    if ($idempotencyKey !== null) $server['HTTP_IDEMPOTENCY_KEY'] = $idempotencyKey;
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
$otherCityToken = $login($otherCityAdmin->getEmail());
$immoToken = $login($adminImmobilier->getEmail());
$patronToken = $login($patron->getEmail());
$check('connexion du compte ADMIN_VILLE de test', is_string($cityToken));
$check('connexion du compte ADMIN_IMMOBILIER de test', is_string($immoToken));
$check('connexion du compte PATRON de test', is_string($patronToken));

[$missingCityStatus] = $request('GET', '/api/v1/organizations/' . $organization->getUuid()->toRfc4122() . '/listing-candidates', null, $cityToken);
$check('ADMIN_VILLE doit sélectionner une ville active avant de charger les biens candidats', $missingCityStatus === 400, 'HTTP ' . $missingCityStatus);
[$candidateStatus, $candidatePayload] = $request('GET', '/api/v1/organizations/' . $organization->getUuid()->toRfc4122() . '/listing-candidates?cityUuid=' . $city->getUuid()->toRfc4122(), null, $cityToken);
$candidates = $candidatePayload['data']['items'] ?? [];
[$secondCandidateStatus, $secondCandidatePayload] = $request('GET', '/api/v1/organizations/' . $organization->getUuid()->toRfc4122() . '/listing-candidates?cityUuid=' . $secondCity->getUuid()->toRfc4122(), null, $cityToken);
$secondCandidates = $secondCandidatePayload['data']['items'] ?? [];
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
    $em = $container->get('doctrine')->getManager();
    $managedUnit = $em->getRepository(Unit::class)->findOneBy(['uuid' => $unit->getUuid()]);
    if (!$managedUnit instanceof Unit) throw new RuntimeException('Unité de test introuvable après la requête refusée.');
    $em->persist(new UnitPhoto($managedUnit, 'units/' . $unit->getUuid()->toRfc4122() . '/couverture-test.jpg'));
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

    $inquiryUri = '/api/public/organizations/' . $organization->getSlug() . '/listings/' . $unit->getUuid()->toRfc4122() . '/inquiries';
    $inquiryKey = \Symfony\Component\Uid\Uuid::v4()->toRfc4122();
    $inquiryBody = ['type' => 'information', 'name' => 'Visiteur test', 'phone' => '+243 000 000 000', 'email' => 'visiteur@test.local', 'message' => 'Je souhaite des informations.', 'privacyConsent' => true];
    [$inquiryStatus, $inquiryPayload] = $request('POST', $inquiryUri, $inquiryBody, null, $inquiryKey);
    $inquiryReference = $inquiryPayload['data']['reference'] ?? null;
    $check('une demande publique valide est enregistrée sans exposer les données de contact', $inquiryStatus === 201 && is_string($inquiryReference) && isset($inquiryPayload['data']['receivedAt']) && !isset($inquiryPayload['data']['name'], $inquiryPayload['data']['email']), 'HTTP ' . $inquiryStatus);
    [$repeatStatus, $repeatPayload] = $request('POST', $inquiryUri, $inquiryBody, null, $inquiryKey);
    $check('une reprise avec la même clé ne crée pas une seconde demande', $repeatStatus === 200 && ($repeatPayload['data']['reference'] ?? null) === $inquiryReference, 'HTTP ' . $repeatStatus);
    $inquiryRoot = '/api/v1/organizations/' . $organization->getUuid()->toRfc4122() . '/inquiries';
    [$cityAlertsStatus, $cityAlerts] = $request('GET', $inquiryRoot . '/notifications', null, $cityToken);
    [$otherCityAlertsStatus, $otherCityAlerts] = $request('GET', $inquiryRoot . '/notifications', null, $otherCityToken);
    [$patronAlertsStatus, $patronAlerts] = $request('GET', $inquiryRoot . '/notifications', null, $patronToken);
    $notificationUuid = $cityAlerts['data']['items'][0]['uuid'] ?? '';
    $check('la demande crée une alerte uniquement pour l’ADMIN_VILLE affecté à sa ville', $cityAlertsStatus === 200 && ($cityAlerts['data']['unread'] ?? 0) === 1 && count($cityAlerts['data']['items'] ?? []) === 1 && $otherCityAlertsStatus === 200 && ($otherCityAlerts['data']['items'] ?? []) === [] && $patronAlertsStatus === 403, 'ADMIN_VILLE concerné ' . $cityAlertsStatus . ', autre ville ' . $otherCityAlertsStatus . ', PATRON ' . $patronAlertsStatus);
    [$readNotificationStatus] = $request('POST', $inquiryRoot . '/notifications/' . $notificationUuid . '/read', [], $cityToken);
    [$readOtherNotificationStatus] = $request('POST', $inquiryRoot . '/notifications/' . $notificationUuid . '/read', [], $otherCityToken);
    [$readCityAlertsStatus, $readCityAlerts] = $request('GET', $inquiryRoot . '/notifications', null, $cityToken);
    $check('l’alerte reste personnelle, et son indicateur non lu disparaît après consultation', $readNotificationStatus === 200 && $readOtherNotificationStatus === 404 && $readCityAlertsStatus === 200 && ($readCityAlerts['data']['unread'] ?? -1) === 0 && ($readCityAlerts['data']['items'][0]['readAt'] ?? null) !== null, 'lecture destinataire ' . $readNotificationStatus . ', autre compte ' . $readOtherNotificationStatus);
    [$keyConflictStatus] = $request('POST', $inquiryUri, [...$inquiryBody, 'message' => 'Un autre contenu'], null, $inquiryKey);
    $check('une clé de soumission ne peut pas être réutilisée avec un autre contenu', $keyConflictStatus === 409, 'HTTP ' . $keyConflictStatus);
    [$invalidInquiryStatus] = $request('POST', $inquiryUri, [...$inquiryBody, 'email' => 'adresse invalide'], null, \Symfony\Component\Uid\Uuid::v4()->toRfc4122());
    $check('les coordonnées invalides sont refusées côté serveur', $invalidInquiryStatus === 422, 'HTTP ' . $invalidInquiryStatus);
    [$unknownListingInquiryStatus] = $request('POST', '/api/public/organizations/' . $organization->getSlug() . '/listings/' . \Symfony\Component\Uid\Uuid::v4()->toRfc4122() . '/inquiries', $inquiryBody, null, \Symfony\Component\Uid\Uuid::v4()->toRfc4122());
    $check('une demande pour une annonce inconnue est refusée', $unknownListingInquiryStatus === 404, 'HTTP ' . $unknownListingInquiryStatus);
    [$crossOrganizationInquiryStatus] = $request('POST', '/api/public/organizations/' . $organization->getSlug() . '/listings/' . $otherUnit->getUuid()->toRfc4122() . '/inquiries', $inquiryBody, null, \Symfony\Component\Uid\Uuid::v4()->toRfc4122());
    $check('une demande ne peut pas viser une annonce d’une autre organisation', $crossOrganizationInquiryStatus === 404, 'HTTP ' . $crossOrganizationInquiryStatus);

    [$unauthenticatedInquiryStatus] = $request('GET', $inquiryRoot);
    [$cityInquiryStatus, $cityInquiryPayload] = $request('GET', $inquiryRoot, null, $cityToken);
    [$patronInquiryStatus, $patronInquiryPayload] = $request('GET', $inquiryRoot, null, $patronToken);
    [$foreignInquiryStatus] = $request('GET', '/api/v1/organizations/' . $otherOrganization->getUuid()->toRfc4122() . '/inquiries', null, $immoToken);
    $check('ADMIN_VILLE reçoit les demandes des villes attribuées et PATRON les consulte', $unauthenticatedInquiryStatus === 401 && $cityInquiryStatus === 200 && count($cityInquiryPayload['data']['items'] ?? []) === 1 && $patronInquiryStatus === 200 && count($patronInquiryPayload['data']['items'] ?? []) === 1, 'sans jeton ' . $unauthenticatedInquiryStatus . ', ADMIN_VILLE ' . $cityInquiryStatus . ', PATRON ' . $patronInquiryStatus);
    [$cityInquiryDetailStatus, $cityInquiryDetail] = $request('GET', $inquiryRoot . '/' . $inquiryReference, null, $cityToken);
    [$cityInquiryHistoryStatus, $cityInquiryHistory] = $request('GET', $inquiryRoot . '/' . $inquiryReference . '/history', null, $cityToken);
    $check('ADMIN_VILLE voit les coordonnées consenties et l’historique de la demande de sa ville', $cityInquiryDetailStatus === 200 && ($cityInquiryDetail['data']['phone'] ?? null) === $inquiryBody['phone'] && $cityInquiryHistoryStatus === 200 && count($cityInquiryHistory['data'] ?? []) === 1, 'détail ' . $cityInquiryDetailStatus . ', historique ' . $cityInquiryHistoryStatus);

    $firstCityAssignment = $em->getRepository(UserCity::class)->findOneBy(['user' => $adminVille, 'city' => $city]);
    $em->remove($firstCityAssignment);
    $em->flush();
    [$outOfScopeListStatus, $outOfScopeList] = $request('GET', $inquiryRoot, null, $cityToken);
    [$outOfScopeDetailStatus] = $request('GET', $inquiryRoot . '/' . $inquiryReference, null, $cityToken);
    $check('la liste et le détail refusent une demande hors des villes attribuées', $outOfScopeListStatus === 200 && ($outOfScopeList['data']['total'] ?? -1) === 0 && $outOfScopeDetailStatus === 403, 'liste ' . $outOfScopeListStatus . ', détail ' . $outOfScopeDetailStatus);
    $em->persist((new UserCity())
        ->setUser($em->getReference(User::class, $adminVille->getId()))
        ->setCity($em->getReference(City::class, $city->getId())));
    $em->flush();
    $check('ADMIN_IMMOBILIER ne peut pas ouvrir la file d’une autre organisation', $foreignInquiryStatus === 403, 'HTTP ' . $foreignInquiryStatus);

    $inquiryUuid = $inquiryReference;
    [$invalidTransitionStatus] = $request('POST', $inquiryRoot . '/' . $inquiryUuid . '/transition', ['status' => 'contacted'], $immoToken);
    [$patronMutationStatus] = $request('POST', $inquiryRoot . '/' . $inquiryUuid . '/take-ownership', null, $patronToken);
    [$cityOwnershipStatus] = $request('POST', $inquiryRoot . '/' . $inquiryUuid . '/take-ownership', null, $cityToken);
    [$takeOwnershipStatus] = $request('POST', $inquiryRoot . '/' . $inquiryUuid . '/take-ownership', null, $immoToken);
    [$contactedStatus] = $request('POST', $inquiryRoot . '/' . $inquiryUuid . '/transition', ['status' => 'contacted'], $immoToken);
    [$cityTransitionStatus] = $request('POST', $inquiryRoot . '/' . $inquiryUuid . '/transition', ['status' => 'negotiation'], $cityToken);
    $visitDate = (new DateTimeImmutable('+3 days', new DateTimeZone('UTC')))->format(DATE_ATOM);
    [$proposeVisitStatus, $proposedVisit] = $request('POST', $inquiryRoot . '/' . $inquiryUuid . '/visits', ['scheduledAt' => $visitDate], $cityToken);
    $visitUuid = $proposedVisit['data']['uuid'] ?? null;
    [$visitListStatus, $visitList] = $request('GET', $inquiryRoot . '/' . $inquiryUuid . '/visits', null, $cityToken);
    [$rescheduleVisitStatus, $rescheduledVisit] = $request('PATCH', $inquiryRoot . '/' . $inquiryUuid . '/visits/' . $visitUuid . '/reschedule', ['scheduledAt' => (new DateTimeImmutable('+4 days', new DateTimeZone('UTC')))->format(DATE_ATOM)], $cityToken);
    [$confirmVisitStatus] = $request('PATCH', $inquiryRoot . '/' . $inquiryUuid . '/visits/' . $visitUuid . '/transition', ['status' => 'confirmed'], $cityToken);
    [$completeVisitStatus] = $request('PATCH', $inquiryRoot . '/' . $inquiryUuid . '/visits/' . $visitUuid . '/transition', ['status' => 'completed', 'reason' => 'Le client souhaite poursuivre la négociation.'], $cityToken);
    [$negotiationStatus] = $request('POST', $inquiryRoot . '/' . $inquiryUuid . '/transition', ['status' => 'negotiation'], $immoToken);
    [$noLeaseConversionStatus] = $request('POST', $inquiryRoot . '/' . $inquiryUuid . '/convert-to-lease', null, $immoToken);
    [$missingCloseReasonStatus] = $request('POST', $inquiryRoot . '/' . $inquiryUuid . '/transition', ['status' => 'closed'], $immoToken);
    [$closeStatus] = $request('POST', $inquiryRoot . '/' . $inquiryUuid . '/transition', ['status' => 'closed', 'reason' => 'Le visiteur a choisi un autre bien.'], $immoToken);
    [$repeatCloseStatus] = $request('POST', $inquiryRoot . '/' . $inquiryUuid . '/transition', ['status' => 'closed', 'reason' => 'Le visiteur a choisi un autre bien.'], $immoToken);
    [$historyStatus, $historyPayload] = $request('GET', $inquiryRoot . '/' . $inquiryUuid . '/history', null, $immoToken);
    $historyActions = array_column($historyPayload['data'] ?? [], 'action');
    $check('ADMIN_VILLE ne prend pas en charge le prospect et ne change pas son statut commercial', $cityOwnershipStatus === 403 && $cityTransitionStatus === 403, 'prise en charge ' . $cityOwnershipStatus . ', transition ' . $cityTransitionStatus);
    $check('les transitions impossibles et les mutations PATRON sont refusées', $invalidTransitionStatus === 409 && $patronMutationStatus === 403, 'transition ' . $invalidTransitionStatus . ', PATRON ' . $patronMutationStatus);
    $check('ADMIN_VILLE organise, reporte, confirme et consigne la visite avant le suivi commercial', $proposeVisitStatus === 201 && $visitListStatus === 200 && count($visitList['data'] ?? []) === 1 && $rescheduleVisitStatus === 200 && ($rescheduledVisit['data']['status'] ?? null) === 'rescheduled' && $confirmVisitStatus === 200 && $completeVisitStatus === 200 && $negotiationStatus === 200, 'création ' . $proposeVisitStatus . ', réalisation ' . $completeVisitStatus);
    $check('une demande ne crée jamais de bail automatiquement et exige le parcours locatif existant', $noLeaseConversionStatus === 409, 'HTTP ' . $noLeaseConversionStatus);
    $check('ADMIN_IMMOBILIER clôture la demande avec un motif obligatoire', $takeOwnershipStatus === 200 && $contactedStatus === 200 && $missingCloseReasonStatus === 400 && $closeStatus === 200 && $repeatCloseStatus === 200, 'prise en charge ' . $takeOwnershipStatus . ', clôture ' . $closeStatus);
    $visitCompletedEvent = array_values(array_filter($historyPayload['data'] ?? [], static fn (array $event): bool => ($event['action'] ?? null) === 'VISIT_COMPLETED'))[0] ?? null;
    $check('l’historique conserve les actions de visite et leur auteur ADMIN_VILLE', $historyStatus === 200 && $historyActions === ['CREATED_PUBLIC', 'TAKEN_OVER', 'STATUS_CHANGED', 'VISIT_PROPOSED', 'VISIT_RESCHEDULED', 'VISIT_CONFIRMED', 'VISIT_COMPLETED', 'STATUS_CHANGED', 'STATUS_CHANGED'] && ($visitCompletedEvent['actor'] ?? null) === $adminVille->getFullName(), 'HTTP ' . $historyStatus . ', actions ' . count($historyActions));

    $conversionKey = \Symfony\Component\Uid\Uuid::v4()->toRfc4122();
    [$conversionInquiryStatus, $conversionInquiry] = $request('POST', $inquiryUri, $inquiryBody, null, $conversionKey);
    $conversionInquiryUuid = $conversionInquiry['data']['reference'] ?? '';
    [$conversionTakeStatus] = $request('POST', $inquiryRoot . '/' . $conversionInquiryUuid . '/take-ownership', null, $immoToken);
    [$conversionContactStatus] = $request('POST', $inquiryRoot . '/' . $conversionInquiryUuid . '/transition', ['status' => 'contacted'], $immoToken);
    [$conversionNegotiationStatus] = $request('POST', $inquiryRoot . '/' . $conversionInquiryUuid . '/transition', ['status' => 'negotiation'], $immoToken);
    [$tenantStatus, $tenantPayload] = $request('POST', '/api/v1/tenants', [
        'organizationUuid' => $organization->getUuid()->toRfc4122(), 'cityUuid' => $city->getUuid()->toRfc4122(),
        'type' => 'individual', 'firstName' => 'Visiteur', 'lastName' => 'Converti',
        'phone' => $inquiryBody['phone'], 'email' => $inquiryBody['email'],
    ], $cityToken);
    $tenantUuid = $tenantPayload['data']['uuid'] ?? $tenantPayload['data']['id'] ?? '';
    [$leaseStatus, $leasePayload] = $request('POST', '/api/v1/leases', [
        'tenantUuid' => $tenantUuid, 'unitUuid' => $unit->getUuid()->toRfc4122(), 'reference' => 'INQ-' . strtoupper($suffix),
        'startDate' => (new DateTimeImmutable('today', new DateTimeZone('UTC')))->format('Y-m-d'),
        'monthlyRent' => '500.00', 'currency' => 'USD',
    ], $cityToken);
    $leaseUuid = $leasePayload['data']['uuid'] ?? $leasePayload['data']['id'] ?? '';
    [$activateLeaseStatus] = $request('PATCH', '/api/v1/leases/' . $leaseUuid . '/activate', null, $cityToken);
    [$withdrawnListingStatus, $withdrawnListing] = $request('GET', '/api/v1/listings/' . $listingUuid, null, $immoToken);
    [$withdrawnPublicDetailStatus] = $request('GET', '/api/public/organizations/' . $organization->getSlug() . '/listings/' . $unit->getUuid()->toRfc4122());
    [$convertedInquiryStatus, $convertedInquiry] = $request('POST', $inquiryRoot . '/' . $conversionInquiryUuid . '/convert-to-lease', null, $immoToken);
    [$terminateLeaseStatus] = $request('PATCH', '/api/v1/leases/' . $leaseUuid . '/terminate', ['reason' => 'Fin de validation du scénario de test.'], $cityToken);
    [$afterTerminationListingStatus, $afterTerminationListing] = $request('GET', '/api/v1/listings/' . $listingUuid, null, $immoToken);
    $check('la fin du bail ne republie pas automatiquement l’annonce retirée', $terminateLeaseStatus === 200 && $afterTerminationListingStatus === 200 && ($afterTerminationListing['data']['status'] ?? null) === 'withdrawn', 'fin bail HTTP ' . $terminateLeaseStatus . ', annonce ' . ($afterTerminationListing['data']['status'] ?? 'absente'));
    $check('une demande se rattache à un bail créé par le parcours locatif normal, jamais par le suivi client', $conversionInquiryStatus === 201 && $conversionTakeStatus === 200 && $conversionContactStatus === 200 && $conversionNegotiationStatus === 200 && $tenantStatus === 201 && $leaseStatus === 201 && $activateLeaseStatus === 200 && $convertedInquiryStatus === 200 && ($convertedInquiry['data']['convertedLeaseUuid'] ?? null) !== null && $terminateLeaseStatus === 200, 'locataire ' . $tenantStatus . ', bail ' . $leaseStatus . ', activation ' . $activateLeaseStatus . ', rattachement ' . $convertedInquiryStatus . ' ' . json_encode($convertedInquiry['message'] ?? $convertedInquiry['errors'] ?? [], JSON_UNESCAPED_UNICODE));
    $check('l’activation du bail retire l’annonce publique dans la même transition métier', $activateLeaseStatus === 200 && $withdrawnListingStatus === 200 && ($withdrawnListing['data']['status'] ?? null) === 'withdrawn' && $withdrawnPublicDetailStatus === 404, 'activation ' . $activateLeaseStatus . ', annonce ' . ($withdrawnListing['data']['status'] ?? 'absente') . ', détail public HTTP ' . $withdrawnPublicDetailStatus);
    [$reopenedRentStatus] = $request('POST', '/api/v1/units/' . $unit->getUuid()->toRfc4122() . '/listing', $draft, $cityToken);
    [$resubmitRentStatus] = $request('POST', '/api/v1/listings/' . $listingUuid . '/submit', null, $cityToken);
    [$requeueRentStatus] = $request('POST', '/api/v1/listings/' . $listingUuid . '/queue-publication', null, $immoToken);
    [$republishRentStatus] = $request('POST', '/api/v1/listings/' . $listingUuid . '/publish', null, $immoToken);
    $check('après la fin du bail, une annonce nécessite une nouvelle vérification avant republication', $reopenedRentStatus === 201 && $resubmitRentStatus === 200 && $requeueRentStatus === 200 && $republishRentStatus === 200, 'republication ' . $republishRentStatus);

    $publicBase = '/api/public/organizations/' . $organization->getSlug() . '/listings';
    [$filterStatus, $filtered] = $request('GET', $publicBase . '?' . http_build_query([
        'city' => $city->getCode(), 'type' => 'apartment', 'bedrooms' => 2,
        'offerType' => 'rent', 'minPrice' => '450.00', 'maxPrice' => '550.00', 'currency' => 'USD',
    ]));
    $check('les filtres publics ville/type/chambres/offre/prix/devise se combinent', $filterStatus === 200 && count($filtered['data']['items'] ?? []) === 1 && ($filtered['data']['total'] ?? 0) === 1, 'HTTP ' . $filterStatus);
    [$unknownCityStatus, $unknownCity] = $request('GET', $publicBase . '?city=CODE-INCONNU-' . $suffix);
    $check('un code de ville inconnu ne retire pas le filtre de ville', $unknownCityStatus === 200 && ($unknownCity['data']['items'] ?? null) === [] && ($unknownCity['data']['total'] ?? -1) === 0, 'HTTP ' . $unknownCityStatus);
    [$inactiveCityStatus, $inactiveCityPayload] = $request('GET', $publicBase . '?city=' . urlencode($inactiveCity->getCode()));
    $check('une ville inactive ne retourne aucune annonce publique', $inactiveCityStatus === 200 && ($inactiveCityPayload['data']['items'] ?? null) === [] && ($inactiveCityPayload['data']['total'] ?? -1) === 0, 'HTTP ' . $inactiveCityStatus);
    [$typeStatus, $typePayload] = $request('GET', $publicBase . '?type=house');
    $check('le filtre par type exclut les autres types de bien', $typeStatus === 200 && ($typePayload['data']['items'] ?? null) === [] && ($typePayload['data']['total'] ?? -1) === 0, 'HTTP ' . $typeStatus);
    [$bedroomStatus, $bedroomPayload] = $request('GET', $publicBase . '?bedrooms=3');
    $check('le nombre minimum de chambres est appliqué', $bedroomStatus === 200 && ($bedroomPayload['data']['items'] ?? null) === [], 'HTTP ' . $bedroomStatus);
    [$legacyRentStatus, $legacyRentPayload] = $request('GET', $publicBase . '?minRent=450&maxRent=550&currency=USD');
    $check('les anciens filtres minRent/maxRent restent compatibles', $legacyRentStatus === 200 && count($legacyRentPayload['data']['items'] ?? []) === 1, 'HTTP ' . $legacyRentStatus);
    [$invalidPriceStatus] = $request('GET', $publicBase . '?minPrice=-1&currency=USD');
    $check('un prix public négatif est refusé explicitement', $invalidPriceStatus === 400, 'HTTP ' . $invalidPriceStatus);
    [$invalidRangeStatus] = $request('GET', $publicBase . '?minPrice=600&maxPrice=500&currency=USD');
    $check('une fourchette publique inversée est refusée explicitement', $invalidRangeStatus === 400, 'HTTP ' . $invalidRangeStatus);
    [$pageStatus, $pagePayload] = $request('GET', $publicBase . '?type=apartment&page=2&limit=1');
    $check('la pagination conserve le filtre et son total', $pageStatus === 200 && ($pagePayload['data']['items'] ?? null) === [] && ($pagePayload['data']['total'] ?? 0) === 1, 'HTTP ' . $pageStatus);
    [$tenantListStatus, $tenantListPayload] = $request('GET', $publicBase);
    $tenantListingIds = array_column($tenantListPayload['data']['items'] ?? [], 'listingId');
    [$crossTenantDetailStatus] = $request('GET', $publicBase . '/' . $otherUnit->getUuid()->toRfc4122());
    $check('le catalogue et le détail publics ne révèlent pas les annonces d’une autre organisation', $tenantListStatus === 200 && !in_array($otherListing->getUuid()->toRfc4122(), $tenantListingIds, true) && $crossTenantDetailStatus === 404, 'liste HTTP ' . $tenantListStatus . ', détail HTTP ' . $crossTenantDetailStatus);

    [$status] = $request('POST', '/api/v1/listings/' . $listingUuid . '/withdraw', ['reason' => 'Le propriétaire change l’offre.'], $immoToken);
    $saleDraft = ['title' => 'Appartement à vendre ' . $suffix, 'description' => 'Annonce de vente utilisée pour valider le contrat public.', 'offerType' => 'sale', 'price' => '125000.00', 'currency' => 'CDF'];
    [$reopenStatus, $saleListing] = $request('POST', '/api/v1/units/' . $unit->getUuid()->toRfc4122() . '/listing', $saleDraft, $cityToken);
    $check('une annonce retirée peut être réouverte avec une offre de vente', $status === 200 && $reopenStatus === 201 && ($saleListing['data']['offerType'] ?? null) === 'sale', 'HTTP ' . $reopenStatus);
    [$saleSubmitStatus] = $request('POST', '/api/v1/listings/' . $listingUuid . '/submit', null, $cityToken);
    [$saleQueueStatus] = $request('POST', '/api/v1/listings/' . $listingUuid . '/queue-publication', null, $immoToken);
    [$salePublishStatus] = $request('POST', '/api/v1/listings/' . $listingUuid . '/publish', null, $immoToken);
    [$salePublicStatus, $salePublicPage] = $request('GET', $publicBase . '?offerType=sale&minPrice=100000&maxPrice=150000&currency=CDF');
    $salePublic = $salePublicPage['data']['items'][0] ?? [];
    $check('une vente publiée expose son vrai prix sans faux loyer', $saleSubmitStatus === 200 && $saleQueueStatus === 200 && $salePublishStatus === 200 && $salePublicStatus === 200 && ($salePublic['offerType'] ?? null) === 'sale' && ($salePublic['price'] ?? null) === '125000.00' && (!array_key_exists('monthlyRent', $salePublic) || $salePublic['monthlyRent'] === null), 'HTTP ' . $salePublicStatus . ', offre ' . ($salePublic['offerType'] ?? 'absente') . ', prix ' . ($salePublic['price'] ?? 'absent') . ', loyer ' . json_encode($salePublic['monthlyRent'] ?? 'absent'));
    [$legacySaleStatus, $legacySalePayload] = $request('GET', $publicBase . '?minRent=100000&currency=CDF');
    $check('un filtre historique de loyer ne retourne pas une vente', $legacySaleStatus === 200 && ($legacySalePayload['data']['items'] ?? null) === [], 'HTTP ' . $legacySaleStatus);
}

$rollback();
echo PHP_EOL . 'Base : ' . $database . ' — transaction annulée.' . PHP_EOL;
echo $failures === [] ? "SUCCÈS : {$checks} contrôles passés.\n" : 'ÉCHEC : ' . count($failures) . " contrôle(s) sur {$checks}.\n";
$kernel->shutdown();
exit($failures === [] ? 0 : 1);
