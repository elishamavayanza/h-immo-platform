<?php

declare(strict_types=1);

namespace App\Service\Rental;

/** Une période facturée mensuelle et la portion de bail couverte par celle-ci. */
final readonly class RentalBillingPeriod
{
    public function __construct(
        public \DateTimeImmutable $periodStart,
        public \DateTimeImmutable $coveredStart,
        public \DateTimeImmutable $coveredEnd,
    ) {
        if ($periodStart->format('d') !== '01') {
            throw new \InvalidArgumentException('La période facturée doit commencer le premier jour du mois.');
        }

        $month = $periodStart->format('Y-m');
        if ($coveredStart->format('Y-m') !== $month || $coveredEnd->format('Y-m') !== $month) {
            throw new \InvalidArgumentException('La période couverte doit rester dans le mois facturé.');
        }

        if ($coveredStart->format('Y-m-d') > $coveredEnd->format('Y-m-d')) {
            throw new \InvalidArgumentException('La fin de la période couverte ne peut précéder son début.');
        }
    }
}
