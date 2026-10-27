<?php

namespace Tests\Feature\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceAdjustment;
use App\Models\Resident;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BillingFixtures;
use Tests\TestCase;

class InvoiceUiAndIsolationTest extends TestCase
{
    use BillingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBilling();
    }

    public function test_every_invoice_page_renders_for_owner_and_staff(): void
    {
        $lease = $this->leaseFor();
        $this->generate();
        $invoice = $this->invoices()->sole();
        $staff = User::factory()->for($this->tenant)->staff()->create();

        foreach ([$this->owner, $staff] as $user) {
            foreach (['all', 'issued', 'partial', 'paid', 'overdue', 'void'] as $tab) {
                $this->actingAs($user)->get(route('owner.invoices.index', ['tab' => $tab]))->assertOk();
            }
            $this->actingAs($user)->get(route('owner.invoices.index', ['period' => 'all']))->assertOk()->assertSee($invoice->number);
            $this->actingAs($user)->get(route('owner.invoices.create', ['lease_id' => $lease->id]))->assertOk()->assertSee('Item tagihan');
            $this->actingAs($user)->get(route('owner.invoices.show', $invoice))->assertOk()->assertSee($invoice->number)->assertSee('Sisa tagihan');
            $this->actingAs($user)->get(route('owner.leases.show', $lease))->assertOk()->assertSee($invoice->number)->assertSee('Buat tagihan manual');
        }

        $this->actingAs($this->owner)->get(route('owner.invoices.show', $invoice))->assertSee('Batalkan tagihan')->assertSee('Penyesuaian');
        $this->actingAs($staff)->get(route('owner.invoices.show', $invoice))->assertDontSee('Batalkan tagihan')->assertDontSee('Catat penyesuaian');
        $this->actingAs($this->owner)->get(route('owner.dashboard'))->assertSee('Tagihan');
    }

    public function test_summary_cards_are_exact_integer_sums(): void
    {
        $a = $this->leaseFor(['rent_amount' => 1_500_000]);
        $b = $this->leaseFor(['rent_amount' => 1_200_000]);
        $c = $this->leaseFor(['rent_amount' => 900_000]);
        $this->generate();

        $byLease = $this->invoices()->keyBy('lease_id');
        $this->asTenant($this->tenant, function () use ($byLease, $a, $b, $c) {
            $byLease[$a->id]->update(['status' => InvoiceStatus::Paid, 'paid_total' => 1_500_000]);
            $byLease[$b->id]->update(['status' => InvoiceStatus::Overdue, 'paid_total' => 200_000]);
            $byLease[$c->id]->update(['status' => InvoiceStatus::Void]);
        });

        $this->actingAs($this->owner)->get(route('owner.invoices.index'))
            ->assertOk()
            ->assertViewHas('summary', [
                'issued' => 2_700_000,   // 1.500.000 + 1.200.000 (batal tidak dihitung)
                'collected' => 1_700_000, // 1.500.000 + 200.000
                'overdue' => 1_000_000,  // 1.200.000 - 200.000
            ])
            ->assertViewHas('counts', fn ($c) => $c['all'] === 3 && $c['paid'] === 1 && $c['overdue'] === 1 && $c['void'] === 1);
    }

    public function test_filters(): void
    {
        $first = $this->leaseFor();
        $second = $this->leaseFor();
        $this->generate();

        $numbers = $this->invoices()->keyBy('lease_id');
        $resident = $this->asTenant($this->tenant, fn () => Resident::findOrFail($first->primary_resident_id));

        $get = fn (array $q) => $this->actingAs($this->owner)->get(route('owner.invoices.index', $q));

        $get(['q' => $numbers[$first->id]->number])->assertSee($numbers[$first->id]->number)->assertDontSee($numbers[$second->id]->number);
        $get(['q' => $resident->name])->assertSee($numbers[$first->id]->number)->assertDontSee($numbers[$second->id]->number);
        $get(['q' => '100'])->assertSee($numbers[$first->id]->number)->assertDontSee($numbers[$second->id]->number);

        // Bulan lain tidak memuat tagihan Oktober; "semua periode" memuatnya.
        $get(['period' => '2026-09'])->assertDontSee($numbers[$first->id]->number);
        $get(['period' => 'all'])->assertSee($numbers[$first->id]->number);

        $get(['tab' => 'paid'])->assertDontSee($numbers[$first->id]->number);
        $get(['tab' => 'bukan'])->assertSessionHasErrors('tab');
        $get(['period' => '2026-13'])->assertSessionHasErrors('period');
        $get(['q' => '%'])->assertOk()->assertDontSee($numbers[$first->id]->number);
    }

    public function test_invoice_list_has_no_n_plus_one(): void
    {
        $count = function (): int {
            DB::enableQueryLog();
            $this->actingAs($this->owner)->get(route('owner.invoices.index'))->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();
            DB::flushQueryLog();

            return $n;
        };

        foreach (range(1, 3) as $i) {
            $this->leaseFor();
        }
        $this->generate();
        $small = $count();

        foreach (range(1, 30) as $i) {
            $this->leaseFor();
        }
        $this->generate();

        $this->assertSame(33, $this->invoices()->count());
        $this->assertSame($small, $count(), 'Daftar tagihan terkena N+1.');
    }

    public function test_tenant_isolation(): void
    {
        $other = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id]);
        User::factory()->for($other)->create();
        $theirs = $this->leaseFor([], $other);
        $this->leaseFor();
        $this->generate();
        $this->generate(null, $other);

        $theirInvoice = $this->invoices($other)->sole();
        $myInvoice = $this->invoices()->sole();

        $this->assertSame($theirInvoice->number, $myInvoice->number, 'Dua tenant boleh memiliki nomor yang sama.');

        $this->asTenant($this->tenant, function () use ($theirInvoice) {
            $this->assertNull(Invoice::find($theirInvoice->id));
            $this->assertSame(1, Invoice::count());
        });

        $a = $this->actingAs($this->owner);
        $a->get(route('owner.invoices.show', $theirInvoice))->assertNotFound();
        $a->post(route('owner.invoices.adjust', $theirInvoice), ['direction' => 'decrease', 'amount' => 1000, 'reason' => 'Diretas'])->assertNotFound();
        $a->post(route('owner.invoices.void', $theirInvoice), ['reason' => 'Diretas'])->assertNotFound();
        $a->get(route('owner.invoices.index', ['period' => 'all']))->assertOk()->assertViewHas('invoices', fn ($p) => $p->total() === 1);
        $a->get(route('owner.invoices.index', ['property_id' => $theirs->property_id]))->assertOk()->assertViewHas('invoices', fn ($p) => $p->total() === 0);

        $this->assertSame(InvoiceStatus::Issued, $this->withoutTenant(fn () => Invoice::findOrFail($theirInvoice->id)->status));
        $this->assertSame(0, $this->withoutTenant(fn () => InvoiceAdjustment::count()));
    }
}
