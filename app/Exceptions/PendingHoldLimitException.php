<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown inside the booking transaction when the guest already holds the
 * maximum number of active pending reservations, so the transaction rolls
 * back cleanly. Membatasi penumpukan hold kalender (denial-of-inventory)
 * oleh satu akun.
 */
class PendingHoldLimitException extends RuntimeException {}
