<?php

declare(strict_types=1);

namespace App\Exception;

final class EmailAlreadyTakenException extends \DomainException
{
    public function __construct(string $email)
    {
        parent::__construct(sprintf('Email "%s" is already in use.', $email));
    }
}
