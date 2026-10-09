<?php

declare(strict_types=1);

namespace App\Support;

class ValidationException extends \RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(public readonly array $errors, string $message = 'Verifique os dados informados.')
    {
        parent::__construct($message);
    }
}

class ConflictException extends \RuntimeException
{
}

class AuthException extends \RuntimeException
{
}

class NotFoundException extends \RuntimeException
{
}
