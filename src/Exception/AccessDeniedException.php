<?php

namespace App\Exception;

use Exception;

class AccessDeniedException extends Exception
{
    public static function create(string $message): self
    {
        return new self($message);
    }
}
