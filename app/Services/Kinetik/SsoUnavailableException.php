<?php

namespace App\Services\Kinetik;

use RuntimeException;

/** BPS SSO could not be reached. A later try can work. */
class SsoUnavailableException extends RuntimeException {}
