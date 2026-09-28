<?php

namespace App\Contracts;

/**
 * Adapter contract for sending an SMS (spec section 6/179: every third-party
 * integration is an interface with a mock implementation shipped first).
 * Wave 1 binds this to LogSmsGateway only; a real BD provider (SSL Wireless,
 * Alpha SMS, ...) is a Wave 2 concern — swapping it in is a one-line
 * container binding change in AppServiceProvider, never a change to any
 * caller of this interface.
 */
interface SmsGateway
{
    /** $to is a BD phone number already in the app's normalized format (see App\Support\BdPhoneNumber). */
    public function send(string $to, string $message): void;
}
