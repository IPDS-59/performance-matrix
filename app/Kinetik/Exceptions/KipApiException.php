<?php

namespace App\Kinetik\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class KipApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?Response $response = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromResponse(Response $response, string $context = ''): self
    {
        $prefix = $context ? "{$context}: " : '';
        $status = $response->status();

        return new self(
            "{$prefix}kipApp API returned HTTP {$status}",
            $response,
        );
    }
}
