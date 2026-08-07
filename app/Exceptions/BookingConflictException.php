<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown inside the booking transaction when the requested date range
 * is no longer available, so the transaction rolls back cleanly.
 */
class BookingConflictException extends RuntimeException {}
