<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** Filtres du catalogue commun, sans code ville propre à une organisation. */
#[OA\Schema(title: 'PublicGlobalListingFilterQuery')]
#[Assert\Callback('validatePriceRange')]
final readonly class PublicGlobalListingFilterQuery
{
    public function __construct(
        #[Assert\Choice(choices: ['apartment', 'house', 'shop', 'office', 'restaurant', 'room', 'warehouse', 'workshop', 'storage', 'parking', 'other'])]
        #[OA\Property(type: 'string', nullable: true)]
        public ?string $type = null,
        #[Assert\Choice(choices: ['rent', 'sale'])]
        #[OA\Property(type: 'string', enum: ['rent', 'sale'], nullable: true)]
        public ?string $offerType = null,
        #[Assert\PositiveOrZero]
        #[OA\Property(type: 'integer', minimum: 0, nullable: true)]
        public ?int $bedrooms = null,
        #[Assert\Regex(pattern: '/^(?:0|[1-9]\d{0,9})(?:\.\d{1,2})?$/')]
        #[OA\Property(type: 'string', format: 'decimal', nullable: true)]
        public ?string $minPrice = null,
        #[Assert\Regex(pattern: '/^(?:0|[1-9]\d{0,9})(?:\.\d{1,2})?$/')]
        #[OA\Property(type: 'string', format: 'decimal', nullable: true)]
        public ?string $maxPrice = null,
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
        if (($this->minPrice !== null || $this->maxPrice !== null) && $this->currency === null) {
            $context->buildViolation('Une devise est obligatoire pour filtrer un prix.')
                ->atPath('currency')->addViolation();
        }
        if ($this->minPrice !== null && $this->maxPrice !== null && self::compareAmounts($this->minPrice, $this->maxPrice) > 0) {
            $context->buildViolation('Le prix minimum doit être inférieur ou égal au prix maximum.')
                ->atPath('maxPrice')->addViolation();
        }
    }

    private static function compareAmounts(string $left, string $right): int
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
