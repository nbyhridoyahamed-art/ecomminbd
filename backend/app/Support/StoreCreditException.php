<?php

namespace App\Support;

use Exception;

/** A staff-facing store credit validation failure — its message is always safe to return as-is. */
class StoreCreditException extends Exception {}
