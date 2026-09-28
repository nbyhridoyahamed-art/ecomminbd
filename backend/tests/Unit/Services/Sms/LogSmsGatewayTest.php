<?php

namespace Tests\Unit\Services\Sms;

use App\Services\Sms\LogSmsGateway;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LogSmsGatewayTest extends TestCase
{
    public function test_it_logs_the_recipient_and_message_instead_of_sending_anywhere(): void
    {
        Log::spy();

        (new LogSmsGateway)->send('01712345678', 'Your order has been placed.');

        Log::shouldHaveReceived('info')
            ->once()
            ->with('[SMS] To: 01712345678 | Your order has been placed.');
    }
}
