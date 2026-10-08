<?php

namespace App\Services\Kinetik;

use RuntimeException;

class KipLoginException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $rejected = false)
    {
        parent::__construct($message);
    }

    /** BPS SSO refused the username or password. */
    public static function rejected(): self
    {
        return new self('SSO rejected the credentials', true);
    }

    /** SSO or kipApp did not behave as expected. The password is not at fault. */
    public static function unavailable(string $reason): self
    {
        return new self($reason);
    }
}
