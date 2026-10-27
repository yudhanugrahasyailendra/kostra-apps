<?php

namespace Tests\Feature\Payments;

use App\Actions\Payments\RecordPayment;
use App\Models\Invoice;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\PaymentFixtures;
use Tests\TestCase;

/**
 * Bukti bahwa tagihan dikunci sebelum sisa dihitung, sehingga dua pencatatan bersamaan tidak bisa
 * sama-sama melihat sisa lama dan bersama-sama melewatinya.
 *
 * Memakai penyapuan tabel (bukan transaksi pembungkus) supaya data ter-commit dan terlihat oleh
 * koneksi kedua yang bertindak sebagai "proses lain". Koneksi kedua meminta kunci BERSAMA (FOR SHARE):
 * itu hanya tertahan bila koneksi pertama memegang kunci eksklusif dari SELECT ... FOR UPDATE.
 */
class PaymentConcurrencyTest extends TestCase
{
    use DatabaseTruncation, PaymentFixtures;

    protected function tearDown(): void
    {
        // DatabaseTruncation hanya menyapu sebelum tes; tanpa ini data ter-commit tes terakhir bocor
        // ke tes RefreshDatabase berikutnya (jumlah baris User/Room meleset satu).
        $this->truncateDatabaseTables();

        parent::tearDown();
    }

    public function test_locking_the_invoice_blocks_other_connections_until_commit(): void
    {
        $this->setUpBilling('2026-10-15', 'free');
        $invoice = $this->openInvoice();

        $second = DB::connectUsing('proses_lain', config('database.connections.'.config('database.default')), true);

        DB::beginTransaction();

        try {
            // Proses A mengunci tagihan lewat jalur yang sama dengan RecordPayment.
            app(TenantContext::class)->run($this->tenant, fn () => app(RecordPayment::class)->lockInvoices([$invoice->id]));

            $second->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $second->beginTransaction();

            try {
                $second->select('select total from invoices where id = ? for share', [$invoice->id]);
                $this->fail('lockInvoices tidak mengunci baris tagihan secara eksklusif: proses lain bisa ikut membaca sisa yang sama.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('Lock wait timeout', $e->getMessage());
            }

            $second->rollBack();
        } finally {
            DB::rollBack();
        }

        // Setelah proses A selesai, proses lain bisa membaca dan memakai tagihan itu.
        $second->beginTransaction();
        $row = $second->selectOne('select total from invoices where id = ? for share', [$invoice->id]);
        $second->rollBack();
        $this->assertSame(1_500_000, (int) $row->total);
    }

    public function test_two_sequential_payments_can_never_exceed_the_invoice(): void
    {
        $this->setUpBilling('2026-10-15', 'free');
        $invoice = $this->openInvoice();

        $this->record([[$invoice, 1_000_000]]);

        try {
            $this->record([[$invoice, 600_000]]);
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException) {
            // diharapkan
        }

        $this->record([[$invoice, 500_000]]);

        $fresh = $this->asTenant($this->tenant, fn () => Invoice::findOrFail($invoice->id));
        $this->assertSame([1_500_000, 'paid'], [$fresh->paid_total, $fresh->status->value]);
    }
}
