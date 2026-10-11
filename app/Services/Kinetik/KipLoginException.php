<?php

namespace App\Services\Kinetik;

use RuntimeException;

class KipLoginException extends RuntimeException
{
    /**
     * @param  array{action: string, cookies: array<int, array<string, mixed>>}|null  $pending  SSO state to resume at the OTP step
     */
    public function __construct(
        string $message,
        public readonly bool $rejected = false,
        public readonly ?array $pending = null,
        public readonly bool $otpInvalid = false,
    ) {
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

    /** The password is right, but the account must also send a one-time code. */
    public static function otpRequired(array $pending): self
    {
        return new self('SSO asks for a one-time code', false, $pending);
    }

    /** The one-time code was wrong. SSO shows the code form again, so the member may retry. */
    public static function otpInvalid(array $pending): self
    {
        return new self('SSO rejected the one-time code', false, $pending, true);
    }

    public function needsOtp(): bool
    {
        return $this->pending !== null && ! $this->otpInvalid;
    }
}
