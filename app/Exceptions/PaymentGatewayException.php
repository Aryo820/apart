<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown inside the booking transaction when the payment gateway
 * cannot be reached, so the transaction (and the booking row) rolls
 * back cleanly instead of leaving an orphaned pending booking.
 */
class PaymentGatewayException extends RuntimeException {}
