<?php

namespace Tests\Unit;

use App\Support\InvoiceMath;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InvoiceMathTest extends TestCase
{
    #[DataProvider('prorations')]
    public function test_prorate_rounds_half_up_with_integers(int $rent, int $days, int $monthDays, int $expected): void
    {
        $this->assertSame($expected, InvoiceMath::prorate($rent, $days, $monthDays));
    }

    public static function prorations(): array
    {
        return [
            'bulan penuh' => [1_500_000, 31, 31, 1_500_000],
            'lebih dari satu bulan tetap penuh' => [1_500_000, 40, 31, 1_500_000],
            '16 dari 31 hari' => [1_500_000, 16, 31, 774_194],
            '15 dari 31 hari' => [1_500_000, 15, 31, 725_806],
            '15 dari 28 hari' => [1_500_000, 15, 28, 803_571],
            '15 dari 30 hari' => [1_500_000, 15, 30, 750_000],
            'setengah naik ke atas' => [15, 1, 30, 1],
            'di bawah setengah turun' => [14, 1, 30, 0],
            '1 dari 31 hari' => [1_000_000, 1, 31, 32_258],
            'sewa tidak habis dibagi' => [1_750_000, 10, 30, 583_333],
        ];
    }

    public function test_two_prorated_halves_of_a_month_add_up_to_the_full_rent(): void
    {
        $this->assertSame(1_500_000, InvoiceMath::prorate(1_500_000, 15, 31) + InvoiceMath::prorate(1_500_000, 16, 31));
        $this->assertSame(1_500_000, InvoiceMath::prorate(1_500_000, 14, 28) + InvoiceMath::prorate(1_500_000, 14, 28));
    }

    #[DataProvider('lines')]
    public function test_line_amount(string|int $quantity, int $unit, int $expected): void
    {
        $this->assertSame($expected, InvoiceMath::lineAmount($quantity, $unit));
    }

    public static function lines(): array
    {
        return [
            'satu' => ['1', 150_000, 150_000],
            'int' => [3, 50_000, 150_000],
            'satu setengah' => ['1.5', 150_000, 225_000],
            'dua desimal' => ['2.25', 100, 225],
            'dibulatkan ke atas' => ['0.01', 150, 2],
            'dibulatkan ke bawah' => ['0.01', 149, 1],
        ];
    }

    #[DataProvider('badQuantities')]
    public function test_bad_quantities_are_rejected(string $quantity): void
    {
        $this->expectException(InvalidArgumentException::class);

        InvoiceMath::lineAmount($quantity, 1000);
    }

    public static function badQuantities(): array
    {
        return [['1,5'], ['-1'], ['abc'], ['1.234'], [''], ['1e3']];
    }

    #[DataProvider('ranges')]
    public function test_billable_range(string $start, ?string $end, bool $first, bool $last, ?array $expected): void
    {
        $range = InvoiceMath::billableRange(
            CarbonImmutable::parse($start),
            $end ? CarbonImmutable::parse($end) : null,
            CarbonImmutable::parse('2026-10-01'),
            $first,
            $last,
        );

        if ($expected === null) {
            $this->assertNull($range);

            return;
        }

        $this->assertSame($expected['start'], $range['start']->toDateString());
        $this->assertSame($expected['end'], $range['end']->toDateString());
        $this->assertSame($expected['days'], $range['days']);
        $this->assertSame(31, $range['days_in_month']);
        $this->assertSame($expected['prorated'], $range['prorated']);
    }

    public static function ranges(): array
    {
        return [
            'kontrak berjalan, bulan penuh' => ['2026-01-01', '2026-12-31', true, true, ['start' => '2026-10-01', 'end' => '2026-10-31', 'days' => 31, 'prorated' => false]],
            'mulai tengah bulan, pro rata aktif' => ['2026-10-12', null, true, true, ['start' => '2026-10-12', 'end' => '2026-10-31', 'days' => 20, 'prorated' => true]],
            'mulai tengah bulan, pro rata mati' => ['2026-10-12', null, false, true, ['start' => '2026-10-01', 'end' => '2026-10-31', 'days' => 31, 'prorated' => false]],
            'berakhir tengah bulan, pro rata aktif' => ['2026-01-01', '2026-10-10', true, true, ['start' => '2026-10-01', 'end' => '2026-10-10', 'days' => 10, 'prorated' => true]],
            'berakhir tengah bulan, pro rata akhir mati' => ['2026-01-01', '2026-10-10', true, false, ['start' => '2026-10-01', 'end' => '2026-10-31', 'days' => 31, 'prorated' => false]],
            'mulai dan berakhir di bulan yang sama' => ['2026-10-05', '2026-10-20', true, true, ['start' => '2026-10-05', 'end' => '2026-10-20', 'days' => 16, 'prorated' => true]],
            'hanya pro rata awal aktif, akhir dipenuhkan' => ['2026-10-05', '2026-10-20', true, false, ['start' => '2026-10-05', 'end' => '2026-10-31', 'days' => 27, 'prorated' => true]],
            'berakhir tepat akhir bulan' => ['2026-01-01', '2026-10-31', true, true, ['start' => '2026-10-01', 'end' => '2026-10-31', 'days' => 31, 'prorated' => false]],
            'mulai tepat tanggal 1' => ['2026-10-01', null, true, true, ['start' => '2026-10-01', 'end' => '2026-10-31', 'days' => 31, 'prorated' => false]],
            'berakhir sebelum bulan (tidak ada hari)' => ['2026-01-01', '2026-09-30', true, true, null],
        ];
    }

    public function test_february_has_28_days_in_a_common_year_and_29_in_a_leap_year(): void
    {
        $common = InvoiceMath::billableRange(CarbonImmutable::parse('2027-02-15'), null, CarbonImmutable::parse('2027-02-01'), true, true);
        $leap = InvoiceMath::billableRange(CarbonImmutable::parse('2028-02-15'), null, CarbonImmutable::parse('2028-02-01'), true, true);

        $this->assertSame([14, 28], [$common['days'], $common['days_in_month']]);
        $this->assertSame([15, 29], [$leap['days'], $leap['days_in_month']]);
    }
}
