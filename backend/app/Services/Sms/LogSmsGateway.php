<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

/**
 * Wave 1's only SmsGateway binding — no BD SMS provider credentials exist in
 * this environment (see PROJECT_AUDIT.md), so this logs the message that
 * would have been sent rather than pretending to deliver it. Real code path,
 * mock transport: exactly what spec section 179 asks for until real
 * credentials are supplied.
 */
class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): void
    {
        Log::info("[SMS] To: {$to} | {$message}");
    }
}
