<?php

namespace App\Exceptions;

use Exception;

/**
 * Raised when the StudioKristian Billing API returns an error or is unreachable.
 * The status code mirrors the upstream HTTP status where possible so controllers
 * can translate it into a safe, application-level message.
 */
class StudioKristianBillingException extends Exception
{
    public function __construct(string $message, protected int $status = 502)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
