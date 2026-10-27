<?php

namespace Tests\Feature\Payments;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use App\Models\Resident;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PaymentFixtures;
use Tests\TestCase;

class PaymentUiAndIsolationTest extends TestCase
{
    use PaymentFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->setUpBilling();
    }

    public function test_every_payment_page_renders_for_owner_and_staff(): void
    {
        $invoice = $this->openInvoice();
        $payment = $this->record([[$invoice, 500_000]]);
        $this->actingAs($this->owner)->post(route('owner.invoices.proofs.store', $invoice), [
            'amount' => '300.000', 'file' => UploadedFile::fake()->image('b.jpg'),
        ])->assertSessionHasNoErrors();
        $staff = User::factory()->for($this->tenant)->staff(['settings.manage'])->create();

        foreach ([$this->owner, $staff] as $user) {
            foreach ([
                route('owner.payments.index'), route('owner.payments.index', ['period' => 'all', 'method' => 'cash', 'status' => 'active']),
                route('owner.payments.create'), route('owner.payments.create', ['invoice_id' => $invoice->id]),
                route('owner.payments.create', ['resident_id' => $invoice->resident_id]),
                route('owner.payments.show', $payment), route('owner.payments.receipt', $payment),
                route('owner.proofs.index'), route('owner.proofs.index', ['tab' => 'all']),
                route('owner.invoices.show', $invoice), route('owner.payment-accounts.index'),
            ] as $url) {
                $this->actingAs($user)->get($url)->assertOk();
            }
        }

        $this->actingAs($this->owner)->get(route('owner.invoices.show', $invoice))
            ->assertSee('Catat pembayaran')->assertSee('Bukti transfer')->assertSee($payment->receipt_number);
        $this->actingAs($this->owner)->get(route('owner.dashboard'))->assertSee('Pembayaran')->assertSee('Verifikasi bukti')->assertSee('1 menunggu');
    }

    public function test_the_payment_form_lists_open_invoices_and_prefills_only_the_chosen_one(): void
    {
        $lease = $this->leaseFor(['start_date' => '2026-08-01']);
        $this->generate('2026-09');
        $september = $this->openInvoice([], '2026-09', $lease);
        $october = $this->openInvoice([], '2026-10', $lease);
        $paid = $this->openInvoice();
        $this->record([[$paid, 1_500_000]]);

        $response = $this->actingAs($this->owner)->get(route('owner.payments.create', ['invoice_id' => $october->id]))->assertOk();

        $response->assertSee($september->number)->assertSee($october->number)->assertDontSee($paid->number)
            ->assertSee('allocations['.$october->id.']', false)
            ->assertSee('value="1.500.000"', false);

        $this->assertSame(1, substr_count($response->getContent(), 'value="1.500.000"'), 'Hanya tagihan yang dipilih yang terisi otomatis.');

        $this->actingAs($this->owner)->get(route('owner.payments.create'))->assertOk()
            ->assertSee(Resident::withoutGlobalScopes()->find($lease->primary_resident_id)->name);
    }

    public function test_the_payment_form_hides_residents_without_open_invoices(): void
    {
        $paid = $this->openInvoice();
        $this->record([[$paid, 1_500_000]]);
        $resident = $this->withoutTenant(fn () => Resident::findOrFail($paid->resident_id));

        $this->actingAs($this->owner)->get(route('owner.payments.create'))
            ->assertOk()->assertDontSee($resident->name)->assertSee('Tidak ada tagihan yang menunggu pembayaran');
    }

    public function test_the_summary_counts_only_active_payments_in_the_period(): void
    {
        $a = $this->openInvoice();
        $b = $this->openInvoice();
        $c = $this->openInvoice();
        $this->record([[$a, 1_000_000]]);
        $voided = $this->record([[$b, 700_000]]);
        $this->actingAs($this->owner)->post(route('owner.payments.void', $voided), ['reason' => 'Salah catat']);
        $this->record([[$c, 200_000]], ['paid_on' => '2026-09-20']);

        $october = $this->actingAs($this->owner)->get(route('owner.payments.index'))->assertOk()
            ->assertViewHas('summary', ['received' => 1_000_000, 'active' => 1, 'voided' => 1]);

        $this->actingAs($this->owner)->get(route('owner.payments.index', ['period' => '2026-09']))
            ->assertViewHas('summary', ['received' => 200_000, 'active' => 1, 'voided' => 0]);
        $this->actingAs($this->owner)->get(route('owner.payments.index', ['period' => 'all']))
            ->assertViewHas('summary', ['received' => 1_200_000, 'active' => 2, 'voided' => 1]);
        $this->assertNotNull($october);
    }

    public function test_filters(): void
    {
        $a = $this->openInvoice();
        $b = $this->openInvoice();
        $cash = $this->record([[$a, 100_000]]);
        $transfer = $this->record([[$b, 100_000]], ['method' => 'transfer', 'reference' => 'TRX-777']);
        $resident = $this->withoutTenant(fn () => Resident::findOrFail($a->resident_id));

        $get = fn (array $q) => $this->actingAs($this->owner)->get(route('owner.payments.index', $q));

        $get(['method' => 'cash'])->assertSee($cash->receipt_number)->assertDontSee($transfer->receipt_number);
        $get(['q' => 'TRX-777'])->assertSee($transfer->receipt_number)->assertDontSee($cash->receipt_number);
        $get(['q' => $resident->name])->assertSee($cash->receipt_number)->assertDontSee($transfer->receipt_number);
        $get(['q' => $cash->receipt_number])->assertSee($cash->receipt_number)->assertDontSee($transfer->receipt_number);
        $get(['status' => 'voided'])->assertDontSee($cash->receipt_number);
        $get(['method' => 'barter'])->assertSessionHasErrors('method');
        $get(['period' => '2026-13'])->assertSessionHasErrors('period');
        $get(['q' => '%'])->assertOk()->assertDontSee($cash->receipt_number);
    }

    public function test_payment_list_and_proof_queue_have_no_n_plus_one(): void
    {
        $queries = function (): array {
            DB::enableQueryLog();
            $this->actingAs($this->owner)->get(route('owner.payments.index'))->assertOk();
            $this->actingAs($this->owner)->get(route('owner.proofs.index'))->assertOk();
            // Eager load memasukkan id langsung ke IN (1, 2, ...): setelah angka disamarkan, daftarnya dihitung sama berapa pun isinya.
            $sql = array_map(fn ($q) => preg_replace(['/\d+/', '/in \((\?, )*\?\)/'], ['?', 'in (...)'], $q['query']), DB::getQueryLog());
            DB::disableQueryLog();
            DB::flushQueryLog();

            return $sql;
        };

        $make = function () {
            $invoice = $this->openInvoice();
            $this->record([[$invoice, 100_000]]);
            $this->actingAs($this->owner)->post(route('owner.invoices.proofs.store', $invoice), [
                'amount' => '50.000', 'file' => UploadedFile::fake()->image('b.jpg'),
            ])->assertSessionHasNoErrors();
        };

        foreach (range(1, 3) as $i) {
            $make();
        }
        $small = $queries();

        foreach (range(1, 20) as $i) {
            $make();
        }
        $this->assertSame(23, $this->asTenant($this->tenant, fn () => Payment::count()));
        $large = $queries();

        // Query yang jumlahnya bertambah di data besar menandakan N+1; tampilkan yang berbeda.
        $extra = array_count_values($large);
        foreach (array_count_values($small) as $sql => $times) {
            $extra[$sql] = ($extra[$sql] ?? 0) - $times;
        }

        $grown = array_filter($extra, fn (int $delta) => $delta > 0);

        $this->assertSame([], $grown, 'Query yang bertambah banyak pada data besar (indikasi N+1): '.json_encode($grown, JSON_UNESCAPED_SLASHES));
    }

    public function test_tenant_isolation_for_every_payment_table(): void
    {
        $other = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id]);
        $stranger = User::factory()->for($other)->create();
        $theirLease = $this->leaseFor([], $other);
        $this->generate(null, $other);
        $theirInvoice = $this->asTenant($other, fn () => Invoice::where('lease_id', $theirLease->id)->firstOrFail());
        $theirPayment = $this->record([[$theirInvoice, 400_000]], [], $stranger, $other);
        $this->asTenant($other, fn () => PaymentAccount::create(['type' => 'bank', 'bank_name' => 'BRI', 'account_number' => '999888777', 'is_active' => true]));
        $this->actingAs($stranger)->post(route('owner.invoices.proofs.store', $theirInvoice), ['amount' => '100.000', 'file' => UploadedFile::fake()->image('b.jpg')])->assertSessionHasNoErrors();
        $theirProof = $this->asTenant($other, fn () => PaymentProof::firstOrFail());

        $mine = $this->openInvoice();
        $this->record([[$mine, 100_000]]);

        $this->asTenant($this->tenant, function () use ($theirPayment, $theirProof) {
            $this->assertNull(Payment::find($theirPayment->id));
            $this->assertNull(PaymentProof::find($theirProof->id));
            $this->assertSame(1, Payment::count());
            $this->assertSame(0, PaymentProof::count());
            $this->assertSame(0, PaymentAccount::count());
        });

        $a = $this->actingAs($this->owner);
        $a->get(route('owner.payments.show', $theirPayment))->assertNotFound();
        $a->get(route('owner.payments.receipt', $theirPayment))->assertNotFound();
        $a->post(route('owner.payments.void', $theirPayment), ['reason' => 'Diretas'])->assertNotFound();
        $a->post(route('owner.proofs.approve', $theirProof))->assertNotFound();
        $a->post(route('owner.proofs.reject', $theirProof), ['reason' => 'Diretas'])->assertNotFound();
        $a->post(route('owner.invoices.proofs.store', $theirInvoice), ['amount' => '1.000', 'file' => UploadedFile::fake()->image('b.jpg')])->assertNotFound();
        $a->get(route('owner.payments.create', ['invoice_id' => $theirInvoice->id]))->assertNotFound();
        $a->get(route('owner.payments.create', ['resident_id' => $theirPayment->resident_id]))->assertNotFound();
        $a->post(route('owner.payments.store'), [
            'resident_id' => $theirPayment->resident_id, 'paid_on' => '2026-10-15', 'method' => 'cash', 'allocations' => [$theirInvoice->id => '1.000'],
        ])->assertSessionHasErrors('resident_id');

        $a->get(route('owner.payments.index', ['period' => 'all']))->assertOk()->assertViewHas('payments', fn ($p) => $p->total() === 1);
        $a->get(route('owner.proofs.index', ['tab' => 'all']))->assertOk()->assertViewHas('proofs', fn ($p) => $p->total() === 0);

        $this->assertSame(400_000, $this->asTenant($other, fn () => Invoice::findOrFail($theirInvoice->id)->paid_total));
        $this->assertSame('active', $this->asTenant($other, fn () => Payment::findOrFail($theirPayment->id)->status->value));
    }
}
