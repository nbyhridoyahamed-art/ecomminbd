<?php

namespace App\Support;

use RuntimeException;

/**
 * Thrown inside a stock-mutating DB transaction to force a rollback when
 * a decrease/transfer-out would take a warehouse's quantity below zero.
 */
class InsufficientStockException extends RuntimeException {}
