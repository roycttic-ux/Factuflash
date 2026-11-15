<?php

namespace Tests\Unit;

use App\Services\CuitValidator;
use PHPUnit\Framework\TestCase;

class CuitValidatorTest extends TestCase
{
    public function test_valid_cuits_pass_validation(): void
    {
        // AFIP official CUIT
        $this->assertTrue(CuitValidator::isValid('33-69345023-9'));
        $this->assertTrue(CuitValidator::isValid('33693450239'));

        // Real person and company CUITs
        $this->assertTrue(CuitValidator::isValid('20-40937847-2'));
        $this->assertTrue(CuitValidator::isValid('20409378472'));
        $this->assertTrue(CuitValidator::isValid('20-45109702-5'));
        $this->assertTrue(CuitValidator::isValid('20451097025'));
        $this->assertTrue(CuitValidator::isValid('20-12345678-6'));
        $this->assertTrue(CuitValidator::isValid('30-54666655-3'));
    }

    public function test_invalid_check_digit_is_rejected(): void
    {
        // 33-69345023-9 with wrong check digits
        $this->assertFalse(CuitValidator::isValid('33-69345023-0'));
        $this->assertFalse(CuitValidator::isValid('33-69345023-8'));
        $this->assertFalse(CuitValidator::isValid('20-45109702-0'));
    }

    public function test_invalid_lengths_and_empty_are_rejected(): void
    {
        $this->assertFalse(CuitValidator::isValid(null));
        $this->assertFalse(CuitValidator::isValid(''));
        $this->assertFalse(CuitValidator::isValid('12345678')); // 8 digits (DNI)
        $this->assertFalse(CuitValidator::isValid('2012345678')); // 10 digits
        $this->assertFalse(CuitValidator::isValid('201234567890')); // 12 digits
    }

    public function test_invalid_prefixes_are_rejected(): void
    {
        // Prefix 15 or 99 are not valid CUIT prefixes
        $this->assertFalse(CuitValidator::isValid('15-12345678-0'));
        $this->assertFalse(CuitValidator::isValid('99-12345678-0'));
    }

    public function test_trivial_repetitions_are_rejected(): void
    {
        $this->assertFalse(CuitValidator::isValid('00000000000'));
        $this->assertFalse(CuitValidator::isValid('11111111111'));
        $this->assertFalse(CuitValidator::isValid('22222222222'));
    }

    public function test_formatting_works(): void
    {
        $this->assertSame('33-69345023-9', CuitValidator::format('33693450239'));
        $this->assertSame('20-45109702-5', CuitValidator::format('20451097025'));
        $this->assertSame('12345678', CuitValidator::format('12345678')); // Preserves non-11 lengths
    }
}
