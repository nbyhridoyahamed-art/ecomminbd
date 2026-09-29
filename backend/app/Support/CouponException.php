<?php

namespace App\Support;

use Exception;

/** A customer/staff-facing coupon validation failure — its message is always safe to return as-is. */
class CouponException extends Exception {}
