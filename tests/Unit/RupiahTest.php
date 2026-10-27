<?php

namespace Tests\Unit;

use App\Support\Rupiah;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RupiahTest extends TestCase
{
    #[DataProvider('validInputs')]
    public function test_parses_whole_rupiah_amounts(mixed $input, int $expected): void
    {
        $this->assertSame($expected, Rupiah::parse($input));
    }

    public static function validInputs(): array
    {
        return [
            'titik ribuan' => ['1.850.000', 1_850_000],
            'dengan Rp' => ['Rp 1.850.000', 1_850_000],
            'rp huruf kecil' => ['rp1850000', 1_850_000],
            'polos' => ['1850000', 1_850_000],
            'integer' => [1_850_000, 1_850_000],
            'ribuan saja' => ['1.500', 1_500],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_rejects_fractions_and_garbage(mixed $input): void
    {
        $this->assertNull(Rupiah::parse($input));
    }

    public static function invalidInputs(): array
    {
        return [['1.500,50'], ['1500.50'], ['1.5'], ['1.85.000'], ['abc'], [''], [null], ['-1000'], ['1e6'], [12.5]];
    }

    public function test_formats_for_display(): void
    {
        $this->assertSame('Rp 1.850.000', Rupiah::format(1_850_000));
        $this->assertSame('Rp 0', Rupiah::format(0));
    }
}
