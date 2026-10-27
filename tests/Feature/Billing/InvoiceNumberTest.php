<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\GenerateInvoiceNumber;
use App\Models\NumberSequence;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\BillingFixtures;
use Tests\TestCase;

class InvoiceNumberTest extends TestCase
{
    use BillingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBilling();
    }

    private function next(string $key = 'invoice', string $date = '2026-10-15', string $prefix = 'INV', ?Tenant $tenant = null): string
    {
        return $this->asTenant($tenant ?? $this->tenant, fn () => DB::transaction(
            fn () => app(GenerateInvoiceNumber::class)($key, $prefix, CarbonImmutable::parse($date)),
        ));
    }

    public function test_format_and_sequence(): void
    {
        $this->assertSame('INV-2026-10-0001', $this->next());
        $this->assertSame('INV-2026-10-0002', $this->next());
        $this->assertSame('INV-2026-10-0003', $this->next());
    }

    public function test_sequence_restarts_each_month_and_is_independent_per_tenant_and_key(): void
    {
        $other = Tenant::factory()->create();

        $this->assertSame('INV-2026-10-0001', $this->next());
        $this->assertSame('INV-2026-11-0001', $this->next(date: '2026-11-02'));
        $this->assertSame('INV-2026-10-0002', $this->next());
        $this->assertSame('INV-2026-10-0001', $this->next(tenant: $other));
        $this->assertSame('KWT-2026-10-0001', $this->next('receipt', prefix: 'KWT'));
        $this->assertSame('INV-2026-10-0003', $this->next());
    }

    public function test_a_rolled_back_transaction_releases_its_number(): void
    {
        $this->assertSame('INV-2026-10-0001', $this->next());

        try {
            $this->asTenant($this->tenant, fn () => DB::transaction(function () {
                $number = app(GenerateInvoiceNumber::class)('invoice', 'INV', CarbonImmutable::parse('2026-10-15'));
                $this->assertSame('INV-2026-10-0002', $number);

                throw new RuntimeException('dokumen gagal dibuat');
            }));
        } catch (RuntimeException) {
            // diharapkan
        }

        // Nomor 0002 tidak hangus: dokumen berikutnya memakainya, tidak ada lubang.
        $this->assertSame('INV-2026-10-0002', $this->next());
        $this->assertSame(2, $this->asTenant($this->tenant, fn () => NumberSequence::firstOrFail()->last_number));
    }

    public function test_numbers_longer_than_four_digits_are_not_truncated(): void
    {
        $this->next();
        $this->asTenant($this->tenant, fn () => NumberSequence::query()->update(['last_number' => 9999]));

        $this->assertSame('INV-2026-10-10000', $this->next());
        $this->assertSame('INV-2026-10-10001', $this->next());
    }

    public function test_an_existing_sequence_row_is_reused_not_duplicated(): void
    {
        $this->next();
        $this->next();

        $this->assertSame(1, $this->asTenant($this->tenant, fn () => NumberSequence::count()));
    }

    public function test_the_database_forbids_two_rows_for_the_same_sequence(): void
    {
        $this->next();

        $this->expectException(UniqueConstraintViolationException::class);

        $this->asTenant($this->tenant, fn () => NumberSequence::create(['key' => 'invoice', 'period' => '2026-10', 'last_number' => 0]));
    }
}
