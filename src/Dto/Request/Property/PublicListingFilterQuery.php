<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** Filtres publics du catalogue, validés avant toute requête métier. */
#[OA\Schema(title: 'PublicListingFilterQuery')]
#[Assert\Callback('validatePriceRange')]
final readonly class PublicListingFilterQuery
{
    public function __construct(
        #[Assert\Length(min: 1, max: 32)]
        #[OA\Property(type: 'string', nullable: true, description: 'Code de ville dans cette organisation.')]
        public ?string $city = null,
        #[Assert\Choice(choices: ['apartment', 'house', 'shop', 'office', 'restaurant', 'room', 'warehouse', 'workshop', 'storage', 'parking', 'other'])]
        #[OA\Property(type: 'string', nullable: true)]
        public ?string $type = null,
        #[Assert\PositiveOrZero]
        #[OA\Property(type: 'integer', minimum: 0, nullable: true)]
        public ?int $bedrooms = null,
        #[Assert\Choice(choices: ['rent', 'sale'])]
        #[OA\Property(type: 'string', enum: ['rent', 'sale'], nullable: true)]
        public ?string $offerType = null,
        #[Assert\Regex(pattern: '/^(?:0|[1-9]\d{0,9})(?:\.\d{1,2})?$/')]
        #[OA\Property(type: 'string', format: 'decimal', nullable: true)]
        public ?string $minPrice = null,
        #[Assert\Regex(pattern: '/^(?:0|[1-9]\d{0,9})(?:\.\d{1,2})?$/')]
        #[OA\Property(type: 'string', format: 'decimal', nullable: true)]
        public ?string $maxPrice = null,
        // Alias conservés pour les clients antérieurs qui filtraient les loyers.
        #[Assert\Regex(pattern: '/^(?:0|[1-9]\d{0,9})(?:\.\d{1,2})?$/')]
        #[OA\Property(type: 'string', format: 'decimal', nullable: true, deprecated: true)]
        public ?string $minRent = null,
        #[Assert\Regex(pattern: '/^(?:0|[1-9]\d{0,9})(?:\.\d{1,2})?$/')]
        #[OA\Property(type: 'string', format: 'decimal', nullable: true, deprecated: true)]
        public ?string $maxRent = null,
        #[Assert\Choice(choices: ['USD', 'CDF'])]
        #[OA\Property(type: 'string', enum: ['USD', 'CDF'], nullable: true)]
        public ?string $currency = null,
        #[Assert\Positive]
        #[OA\Property(type: 'integer', minimum: 1, default: 1)]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        #[OA\Property(type: 'integer', minimum: 1, maximum: 100, default: 20)]
        public int $limit = 20,
    ) {
    }

    public function validatePriceRange(ExecutionContextInterface $context): void
    {
        $legacyRangeUsed = $this->minRent !== null || $this->maxRent !== null;
        $newRangeUsed = $this->minPrice !== null || $this->maxPrice !== null;

        if ($legacyRangeUsed && ($newRangeUsed || $this->offerType === 'sale')) {
            $context->buildViolation('Utilisez soit minRent/maxRent, soit les filtres minPrice/maxPrice avec le type d’offre.')
                ->atPath('minPrice')
                ->addViolation();
        }

        if (($legacyRangeUsed || $newRangeUsed) && $this->currency === null) {
            $context->buildViolation('Une devise est obligatoire pour filtrer un prix.')
                ->atPath('currency')
                ->addViolation();
        }

        $minimum = $this->minPrice ?? $this->minRent;
        $maximum = $this->maxPrice ?? $this->maxRent;
        if ($minimum !== null && $maximum !== null && self::compareDecimalStrings($minimum, $maximum) > 0) {
            $context->buildViolation('Le prix minimum doit être inférieur ou égal au prix maximum.')
                ->atPath('maxPrice')
                ->addViolation();
        }
    }

    private static function compareDecimalStrings(string $left, string $right): int
    {
        [$leftWhole, $leftFraction] = array_pad(explode('.', $left, 2), 2, '');
        [$rightWhole, $rightFraction] = array_pad(explode('.', $right, 2), 2, '');
        $leftWhole = ltrim($leftWhole, '0') ?: '0';
        $rightWhole = ltrim($rightWhole, '0') ?: '0';

        if (strlen($leftWhole) !== strlen($rightWhole)) {
            return strlen($leftWhole) <=> strlen($rightWhole);
        }

        $wholeComparison = strcmp($leftWhole, $rightWhole);
        if ($wholeComparison !== 0) {
            return $wholeComparison <=> 0;
        }

        return strcmp(str_pad($leftFraction, 2, '0'), str_pad($rightFraction, 2, '0')) <=> 0;
    }
}
