<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\GenerateInvoiceNumber;
use App\Models\Plan;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Bukti bahwa nomor dokumen diserialkan oleh penguncian baris, bukan sekadar berurutan kebetulan.
 *
 * Memakai penyapuan tabel (bukan transaksi pembungkus) supaya data benar-benar ter-commit dan
 * terlihat oleh koneksi database kedua yang bertindak sebagai "proses lain".
 */
class InvoiceNumberConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        // DatabaseTruncation hanya menyapu sebelum tes; tanpa ini data ter-commit tes terakhir bocor ke tes
        // RefreshDatabase berikutnya (mis. tes yang menghitung semua tenant).
        $this->truncateDatabaseTables();

        parent::tearDown();
    }

    public function test_a_second_connection_cannot_take_the_sequence_while_the_first_holds_it(): void
    {
        $this->seed(PlanSeeder::class);
        $tenant = Tenant::factory()->create(['plan_id' => Plan::where('key', 'free')->value('id')]);

        $second = DB::connectUsing('proses_lain', config('database.connections.'.config('database.default')), true);

        DB::beginTransaction();

        try {
            // Proses A mengambil nomor dalam transaksi yang belum selesai.
            $number = app(TenantContext::class)->run($tenant, fn () => app(GenerateInvoiceNumber::class)(
                'invoice', 'INV', CarbonImmutable::parse('2026-10-15'),
            ));
            $this->assertSame('INV-2026-10-0001', $number);

            // Proses B meminta baris yang sama dengan kunci yang sama: harus tertahan.
            $second->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $second->beginTransaction();

            try {
                $second->select(
                    'select last_number from number_sequences where tenant_id = ? and `key` = ? and period = ? for update',
                    [$tenant->id, 'invoice', '2026-10'],
                );
                $this->fail('Proses kedua seharusnya tertahan oleh kunci baris proses pertama.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('Lock wait timeout', $e->getMessage());
            }

            $second->rollBack();
        } finally {
            DB::commit();
        }

        // Setelah proses A selesai, proses B melihat nomor terakhir yang benar dan mengambil yang berikutnya.
        $second->beginTransaction();
        $last = $second->selectOne(
            'select last_number from number_sequences where tenant_id = ? and `key` = ? and period = ? for update',
            [$tenant->id, 'invoice', '2026-10'],
        );
        $this->assertSame(1, (int) $last->last_number);
        $second->update('update number_sequences set last_number = last_number + 1 where tenant_id = ?', [$tenant->id]);
        $second->commit();

        $next = app(TenantContext::class)->run($tenant, fn () => DB::transaction(
            fn () => app(GenerateInvoiceNumber::class)('invoice', 'INV', CarbonImmutable::parse('2026-10-15')),
        ));
        $this->assertSame('INV-2026-10-0003', $next);
    }

    /**
     * Menguji langkah penguncian itu sendiri. Tanpa FOR UPDATE, dua proses bisa membaca last_number yang sama
     * lalu menulis nomor kembar.
     *
     * Baris urutan dibuat dan di-commit lebih dulu, karena INSERT yang membuat baris (atau INSERT IGNORE pada
     * baris yang sudah ada) sendiri sudah menahan kunci dan akan menutupi ada-tidaknya FOR UPDATE. Koneksi kedua
     * meminta kunci BERSAMA (FOR SHARE): itu hanya tertahan bila koneksi pertama memegang kunci eksklusif.
     */
    public function test_the_select_for_update_itself_blocks_other_connections(): void
    {
        $this->seed(PlanSeeder::class);
        $tenant = Tenant::factory()->create(['plan_id' => Plan::where('key', 'free')->value('id')]);

        // Baris sudah ada dan ter-commit.
        app(TenantContext::class)->run($tenant, fn () => DB::transaction(
            fn () => app(GenerateInvoiceNumber::class)('invoice', 'INV', CarbonImmutable::parse('2026-10-15')),
        ));

        $second = DB::connectUsing('proses_lain', config('database.connections.'.config('database.default')), true);

        DB::beginTransaction();

        try {
            // Proses A hanya mengunci (belum membaca atau menaikkan nomor).
            app(TenantContext::class)->run($tenant, fn () => app(GenerateInvoiceNumber::class)
                ->lockSequence('invoice', CarbonImmutable::parse('2026-10-15')));

            $second->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $second->beginTransaction();

            try {
                $second->select(
                    'select last_number from number_sequences where tenant_id = ? and `key` = ? and period = ? for share',
                    [$tenant->id, 'invoice', '2026-10'],
                );
                $this->fail('lockSequence tidak mengunci baris secara eksklusif: proses lain bisa ikut membaca nilai yang sama.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('Lock wait timeout', $e->getMessage());
            }

            $second->rollBack();
        } finally {
            DB::rollBack();
        }
    }
}
