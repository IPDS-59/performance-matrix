<?php

namespace App\Services\Kinetik;

use RuntimeException;

/** The member has to sign in to Kinetik again: no stored login, a rejected password, or an OTP account. Retrying alone cannot help. */
class MemberLoginRequiredException extends RuntimeException {}
