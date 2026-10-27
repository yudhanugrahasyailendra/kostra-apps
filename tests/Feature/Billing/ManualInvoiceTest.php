<?php

namespace Tests\Feature\Billing;

use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Events\InvoiceIssued;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\BillingFixtures;
use Tests\TestCase;

class ManualInvoiceTest extends TestCase
{
    use BillingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBilling();
    }

    private function payload(array $lease, array $overrides = []): array
    {
        return array_merge([
            'lease_id' => $lease['id'],
            'due_date' => '2026-10-25',
            'notes' => 'Tagihan listrik September',
            'items' => [
                ['type' => 'utility', 'description' => 'Listrik September', 'quantity' => '1.5', 'unit_price' => '150.000'],
                ['type' => 'service', 'description' => 'Kebersihan', 'quantity' => '1', 'unit_price' => 'Rp 50.000'],
                ['type' => '', 'description' => '', 'quantity' => '', 'unit_price' => ''],
            ],
        ], $overrides);
    }

    public function test_manual_invoice_is_issued_immediately_with_a_number_and_exact_totals(): void
    {
        Event::fake([InvoiceIssued::class]);
        $lease = $this->leaseFor();

        $response = $this->actingAs($this->owner)->post(route('owner.invoices.store'), $this->payload(['id' => $lease->id]));

        $invoice = $this->invoices()->sole();
        $response->assertSessionHasNoErrors()->assertRedirect(route('owner.invoices.show', $invoice));

        $this->assertSame('INV-2026-10-0001', $invoice->number);
        $this->assertSame(InvoiceType::Manual, $invoice->type);
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertNull($invoice->period);
        $this->assertNull($invoice->period_start);
        $this->assertSame('2026-10-15', $invoice->issue_date->toDateString());
        $this->assertSame('2026-10-25', $invoice->due_date->toDateString());
        $this->assertSame($this->owner->id, $invoice->created_by);
        $this->assertSame($lease->primary_resident_id, $invoice->resident_id);
        $this->assertSame('Tagihan listrik September', $invoice->notes);

        // 1,5 x 150.000 = 225.000 dan 1 x 50.000; baris kosong diabaikan.
        $this->assertCount(2, $invoice->items);
        $this->assertSame([225_000, 50_000], $invoice->items->pluck('amount')->all());
        $this->assertSame([InvoiceItemType::Utility, InvoiceItemType::Service], $invoice->items->pluck('type')->all());
        $this->assertSame('1.50', $invoice->items[0]->quantity);
        $this->assertSame([275_000, 0, 275_000], [$invoice->subtotal, $invoice->adjustment_total, $invoice->total]);

        Event::assertDispatchedTimes(InvoiceIssued::class, 1);
    }

    public function test_several_manual_invoices_for_one_lease_get_consecutive_numbers(): void
    {
        $lease = $this->leaseFor();

        foreach ([1, 2, 3] as $n) {
            $this->actingAs($this->owner)->post(route('owner.invoices.store'), $this->payload(['id' => $lease->id]))->assertSessionHasNoErrors();
        }

        $this->assertSame(['INV-2026-10-0001', 'INV-2026-10-0002', 'INV-2026-10-0003'], $this->invoices()->pluck('number')->all());
    }

    public function test_manual_invoice_shares_numbering_with_monthly_invoices(): void
    {
        $lease = $this->leaseFor();
        $this->generate();
        $this->actingAs($this->owner)->post(route('owner.invoices.store'), $this->payload(['id' => $lease->id]))->assertSessionHasNoErrors();

        $this->assertSame(['INV-2026-10-0001', 'INV-2026-10-0002'], $this->invoices()->pluck('number')->all());
    }

    public function test_validation(): void
    {
        $lease = $this->leaseFor();
        $post = fn (array $o) => $this->actingAs($this->owner)->post(route('owner.invoices.store'), $this->payload(['id' => $lease->id], $o));

        $post(['items' => []])->assertSessionHasErrors('items');
        $post(['items' => [['type' => 'other', 'description' => '', 'quantity' => '1', 'unit_price' => '1000']]])->assertSessionHasErrors('items.0.description');
        $post(['items' => [['type' => 'other', 'description' => 'X', 'quantity' => '1', 'unit_price' => '']]])->assertSessionHasErrors('items.0.unit_price');
        $post(['items' => [['type' => 'other', 'description' => 'X', 'quantity' => '1', 'unit_price' => '1500.50']]])->assertSessionHasErrors('items.0.unit_price');
        $post(['items' => [['type' => 'other', 'description' => 'X', 'quantity' => '1,5', 'unit_price' => '1000']]])->assertSessionHasErrors('items.0.quantity');
        $post(['items' => [['type' => 'other', 'description' => 'X', 'quantity' => '1.234', 'unit_price' => '1000']]])->assertSessionHasErrors('items.0.quantity');
        $post(['items' => [['type' => 'bukan', 'description' => 'X', 'quantity' => '1', 'unit_price' => '1000']]])->assertSessionHasErrors('items.0.type');
        $post(['due_date' => '2026-10-14'])->assertSessionHasErrors('due_date');
        $post(['due_date' => '2028-01-01'])->assertSessionHasErrors('due_date');
        $post(['due_date' => 'besok'])->assertSessionHasErrors('due_date');

        // Jumlah yang membulat jadi Rp 0 ditolak: kuantitas 0,01 x Rp 1 = Rp 0,01.
        $post(['items' => [['type' => 'other', 'description' => 'X', 'quantity' => '0.01', 'unit_price' => '1']]])->assertSessionHasErrors('items.0.unit_price');

        $this->assertSame(0, $this->invoices()->count());
    }

    public function test_cannot_bill_another_tenants_lease(): void
    {
        $other = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id]);
        $theirs = $this->leaseFor([], $other);

        $this->actingAs($this->owner)->post(route('owner.invoices.store'), $this->payload(['id' => $theirs->id]))->assertSessionHasErrors('lease_id');
        $this->actingAs($this->owner)->get(route('owner.invoices.create', ['lease_id' => $theirs->id]))->assertNotFound();

        $this->assertSame(0, $this->withoutTenant(fn () => Invoice::count()));
    }

    public function test_staff_may_create_manual_invoices_and_suspended_tenants_may_not(): void
    {
        $lease = $this->leaseFor();
        $staff = User::factory()->for($this->tenant)->staff()->create();

        $this->actingAs($staff)->post(route('owner.invoices.store'), $this->payload(['id' => $lease->id]))->assertSessionHasNoErrors();
        $this->assertSame($staff->id, $this->invoices()->sole()->created_by);

        $this->asTenant($this->tenant, fn () => $this->tenant->update(['status' => 'suspended']));
        $this->actingAs($this->owner)->post(route('owner.invoices.store'), $this->payload(['id' => $lease->id]))->assertForbidden();
        $this->assertSame(1, $this->invoices()->count());
    }

    public function test_a_manual_invoice_can_be_made_for_an_ended_lease(): void
    {
        $lease = $this->leaseFor();
        $this->actingAs($this->owner)->post(route('owner.leases.check-out', $lease), [
            'checkout_date' => '2026-10-15', 'ended_reason' => 'moved_out',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->post(route('owner.invoices.store'), $this->payload(['id' => $lease->id]))->assertSessionHasNoErrors();

        $this->assertSame(1, $this->invoices()->count());
    }
}
