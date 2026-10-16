<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AccessPointStatusRequest
{
    public function __construct(#[Assert\NotNull] public bool $enabled) {}
}
