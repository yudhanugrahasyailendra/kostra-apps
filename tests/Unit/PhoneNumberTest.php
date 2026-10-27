<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    #[DataProvider('equivalentNumbers')]
    public function test_local_and_international_forms_normalize_to_the_same_value(string $raw): void
    {
        $this->assertSame('+6281234567890', PhoneNumber::normalize($raw));
    }

    public static function equivalentNumbers(): array
    {
        return [
            'lokal' => ['081234567890'],
            'lokal dengan spasi dan strip' => ['0812-3456 7890'],
            'plus 62' => ['+6281234567890'],
            '62 tanpa plus' => ['6281234567890'],
            '8 tanpa nol' => ['81234567890'],
            '0062' => ['006281234567890'],
        ];
    }

    public function test_invalid_numbers_return_null(): void
    {
        $this->assertNull(PhoneNumber::normalize(null));
        $this->assertNull(PhoneNumber::normalize(''));
        $this->assertNull(PhoneNumber::normalize('abc'));
        $this->assertNull(PhoneNumber::normalize('123'));
    }
}
