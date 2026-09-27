<?php

namespace Tests\Unit\Support;

use App\Support\BdPhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BdPhoneNumberTest extends TestCase
{
    public static function equivalentFormats(): array
    {
        return [
            ['01712345678'],
            ['+8801712345678'],
            ['8801712345678'],
            ['+880 1712-345678'],
        ];
    }

    #[DataProvider('equivalentFormats')]
    public function test_it_normalizes_equivalent_formats_to_the_same_value(string $input): void
    {
        $this->assertSame('01712345678', BdPhoneNumber::normalize($input));
    }

    public function test_it_rejects_invalid_numbers(): void
    {
        $this->assertNull(BdPhoneNumber::normalize('123'));
        $this->assertNull(BdPhoneNumber::normalize('02123456789'));
        $this->assertNull(BdPhoneNumber::normalize('01212345678'));
    }
}
