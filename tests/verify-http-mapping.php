<?php

declare(strict_types=1);

/**
 * Vérifie la traduction des exceptions en codes HTTP.
 *
 * Lance le mapping seul, sans base de données : `resolveError()` ne dépend
 * ni du kernel, ni du serializer, ni du logger.
 *
 *   php .opencode/verify-http-mapping.php
 */

use App\EventListener\ApiExceptionListener;
use App\Exception\AccessDeniedException;
use App\Exception\UnauthenticatedException;
use Doctrine\DBAL\Driver\PDO\Exception as DriverPdoException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Query;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

require dirname(__DIR__) . '/vendor/autoload.php';

$listener = (new ReflectionClass(ApiExceptionListener::class))->newInstanceWithoutConstructor();
$resolveError = new ReflectionMethod($listener, 'resolveError');
$resolveError->setAccessible(true);

$violations = new ConstraintViolationList([
    new ConstraintViolation('Ce champ ne peut pas être vide.', null, [], null, 'email', null),
    new ConstraintViolation('Doit être un email valide.', null, [], null, 'email', null),
]);

$cases = [
    'non authentifié      -> 401' => [UnauthenticatedException::create(), Response::HTTP_UNAUTHORIZED, null],
    'droits insuffisants -> 403' => [AccessDeniedException::create('nope'), Response::HTTP_FORBIDDEN, null],
    'route inconnue      -> 404' => [new NotFoundHttpException('Bail introuvable.'), Response::HTTP_NOT_FOUND, null],
    'uuid absent en base -> 404' => [new EntityNotFoundException('Lease'), Response::HTTP_NOT_FOUND, null],
    'unicité violée      -> 409' => [
        new UniqueConstraintViolationException(new DriverPdoException('Duplicate entry', '23000'), new Query('INSERT ...', [], [])),
        Response::HTTP_CONFLICT,
        null,
    ],
    'verrou optimiste    -> 409' => [
        OptimisticLockException::lockFailed('App\Entity\Rental\Lease'),
        Response::HTTP_CONFLICT,
        null,
    ],
    'validation          -> 422' => [
        new ValidationFailedException('payload', $violations),
        Response::HTTP_UNPROCESSABLE_ENTITY,
        // Deux violations sur le même champ sont conservées toutes les deux.
        ['email' => ['Ce champ ne peut pas être vide.', 'Doit être un email valide.']],
    ],
    'bug applicatif      -> 500' => [new RuntimeException('boom'), Response::HTTP_INTERNAL_SERVER_ERROR, null],
];

$failures = 0;

foreach ($cases as $label => [$exception, $expectedStatus, $expectedViolations]) {
    [$status, $statusText, $violationsDetail] = $resolveError->invoke($listener, $exception);

    $ok = $status === $expectedStatus && $violationsDetail === $expectedViolations;
    $failures += $ok ? 0 : 1;

    printf(
        "  [%s]   %s (obtenu %d %s)%s\n",
        $ok ? 'OK' : 'FAIL',
        $label,
        $status,
        $statusText,
        $violationsDetail !== null ? ' violations=' . json_encode($violationsDetail, JSON_UNESCAPED_UNICODE) : ''
    );
}

echo "\n  Contrôles exécutés : " . count($cases) . "\n";
echo '  Échecs : ' . $failures . "\n";

exit($failures === 0 ? 0 : 1);
