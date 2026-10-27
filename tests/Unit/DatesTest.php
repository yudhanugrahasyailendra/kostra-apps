<?php

namespace Tests\Unit;

use App\Support\Dates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DatesTest extends TestCase
{
    public function test_parses_a_valid_date_as_the_start_of_the_day_in_the_given_timezone(): void
    {
        $date = Dates::date('2026-10-15', 'Asia/Makassar');

        $this->assertSame('2026-10-15 00:00:00', $date->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Makassar', $date->timezoneName);
        $this->assertSame('2026-10-14 16:00:00', $date->utc()->format('Y-m-d H:i:s'));
    }

    #[DataProvider('invalidDates')]
    public function test_invalid_dates_return_null_instead_of_throwing(mixed $value): void
    {
        $this->assertNull(Dates::date($value, 'UTC'));
    }

    public static function invalidDates(): array
    {
        return [
            'kata' => ['kemarin'],
            'kosong' => [''],
            'null' => [null],
            'angka' => [20261015],
            'format lain' => ['15-10-2026'],
            'meluap: 30 Februari' => ['2026-02-30'],
            'meluap: bulan 13' => ['2026-13-01'],
            'meluap: 31 April' => ['2026-04-31'],
            'tanpa nol' => ['2026-1-5'],
            'dengan jam' => ['2026-10-15 10:00'],
            'array' => [['2026-10-15']],
        ];
    }

    public function test_leap_day_is_valid_only_in_leap_years(): void
    {
        $this->assertNotNull(Dates::date('2028-02-29', 'UTC'));
        $this->assertNull(Dates::date('2027-02-29', 'UTC'));
    }

    public function test_month_parses_to_the_first_day_and_rejects_garbage(): void
    {
        $this->assertSame('2026-10-01', Dates::month('2026-10', 'UTC')->toDateString());
        $this->assertNull(Dates::month('2026-13', 'UTC'));
        $this->assertNull(Dates::month('2026-1', 'UTC'));
        $this->assertNull(Dates::month('oktober', 'UTC'));
    }
}
