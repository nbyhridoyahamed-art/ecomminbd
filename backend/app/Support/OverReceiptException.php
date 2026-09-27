<?php

namespace App\Support;

use RuntimeException;

/**
 * Thrown inside a receipt-recording DB transaction to force a rollback
 * when the requested quantity would exceed what remains on the order line.
 */
class OverReceiptException extends RuntimeException {}
