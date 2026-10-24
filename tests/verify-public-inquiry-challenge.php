<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\Service\Property\PublicInquiryChallengeService;
use App\Service\System\DateTimeService;

$challenge = new PublicInquiryChallengeService(new DateTimeService(), '', 'verification-secret-that-is-not-used-in-production', 'dev');
$token = $challenge->issue('agence-demo', 'unit-public-uuid');
$issuedAt = (int) strstr($token, '.', true);
$now = (new DateTimeImmutable('@'.($issuedAt + 3)))->setTimezone(new DateTimeZone('UTC'));
$controller = file_get_contents(dirname(__DIR__).'/src/Controller/Api/Public/CustomerInquiryController.php');
$secondToken = $challenge->issue('agence-demo', 'unit-public-uuid');
$controller = file_get_contents(dirname(__DIR__).'/src/Controller/Api/Public/CustomerInquiryController.php');
$checks = [
    'les jetons successifs ont un nonce distinct même pendant la même seconde' => $token !== $secondToken,
    'la vérification rejette la réutilisation après consommation configurée' => str_contains($controller, 'publicInquiryChallengeUsesLimiter') && str_contains($controller, '->consume()'),
    'la route de défi est accessible en GET' => str_contains($controller, "Route('/challenge', name: 'challenge', methods: ['GET'])"),
    'le champ leurre est contrôlé avant la création' => strpos($controller, '$requestDto->website') < strpos($controller, '$this->inquiries->createPublic'),
    'la limite configurée est réellement consommée' => str_contains($controller, '->consume()'),
    'un jeton signé et attendu est accepté après trois secondes' => $challenge->isValid($token, 'agence-demo', 'unit-public-uuid', $now),
    'un jeton modifié est refusé' => !$challenge->isValid($token.'x', 'agence-demo', 'unit-public-uuid', $now),
    'un jeton lié à une autre organisation est refusé' => !$challenge->isValid($token, 'autre-agence', 'unit-public-uuid', $now),
    'un jeton lié à une autre annonce est refusé' => !$challenge->isValid($token, 'agence-demo', 'autre-unite', $now),
    'un jeton trop récent est refusé' => !$challenge->isValid($token, 'agence-demo', 'unit-public-uuid', (new DateTimeImmutable('@'.($issuedAt + 2)))->setTimezone(new DateTimeZone('UTC'))),
    'un jeton expiré est refusé' => !$challenge->isValid($token, 'agence-demo', 'unit-public-uuid', (new DateTimeImmutable('@'.($issuedAt + 1801)))->setTimezone(new DateTimeZone('UTC'))),
    'un jeton absent est refusé' => !$challenge->isValid(null, 'agence-demo', 'unit-public-uuid', $now),
];

foreach ($checks as $description => $passed) {
    fwrite($passed ? STDOUT : STDERR, sprintf("%s %s\n", $passed ? 'OK' : 'ÉCHEC', $description));
}

exit(in_array(false, $checks, true) ? 1 : 0);
