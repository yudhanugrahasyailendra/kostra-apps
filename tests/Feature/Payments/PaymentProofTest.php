<?php

namespace Tests\Feature\Payments;

use App\Actions\Billing\VoidInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProofStatus;
use App\Events\PaymentRecorded;
use App\Events\ProofRejected;
use App\Models\Attachment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PaymentFixtures;
use Tests\TestCase;

class PaymentProofTest extends TestCase
{
    use PaymentFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->setUpBilling();
    }

    private function submit(Invoice $invoice, array $data = [], ?UploadedFile $file = null, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->owner)->post(route('owner.invoices.proofs.store', $invoice), array_merge([
            'amount' => '1.500.000',
            'paid_on' => '2026-10-14',
            'sender_note' => 'Transfer a.n. Budi',
            'file' => $file ?? UploadedFile::fake()->image('bukti.jpg'),
        ], $data));
    }

    private function proof(?Invoice $invoice = null): PaymentProof
    {
        return $this->asTenant($this->tenant, fn () => PaymentProof::when($invoice, fn ($q) => $q->where('invoice_id', $invoice->id))->latest('id')->firstOrFail());
    }

    private function proofOf(Invoice $invoice, int $amount = 1_500_000): PaymentProof
    {
        $this->submit($invoice, ['amount' => (string) $amount])->assertSessionHasNoErrors();

        return $this->proof($invoice);
    }

    private function approve(PaymentProof $proof, array $data = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->owner)->post(route('owner.proofs.approve', $proof), $data);
    }

    private function reject(PaymentProof $proof, string $reason = 'Nominal tidak sesuai', ?User $as = null)
    {
        return $this->actingAs($as ?? $this->owner)->post(route('owner.proofs.reject', $proof), ['reason' => $reason]);
    }

    // ---- Mencatat bukti ----

    public function test_a_proof_waits_for_review_and_does_not_touch_the_invoice(): void
    {
        $invoice = $this->openInvoice();

        $this->submit($invoice)->assertSessionHasNoErrors()->assertRedirect(route('owner.invoices.show', $invoice));

        $proof = $this->proof();
        $this->assertSame([ProofStatus::Pending, 1_500_000, $invoice->resident_id], [$proof->status, $proof->amount, $proof->resident_id]);
        $this->assertSame('2026-10-14', $proof->paid_on->toDateString());
        $this->assertSame('Transfer a.n. Budi', $proof->sender_note);
        $this->assertNull($proof->payment_id);

        $fresh = $this->fresh($invoice);
        $this->assertSame([InvoiceStatus::Issued, 0], [$fresh->status, $fresh->paid_total]);
        $this->assertSame(0, $this->asTenant($this->tenant, fn () => Payment::count()));

        $file = $this->asTenant($this->tenant, fn () => Attachment::firstOrFail());
        $this->assertSame(['payment_proof', 'transfer_proof', false], [$file->attachable_type, $file->category->value, $file->is_sensitive]);
        Storage::disk('private')->assertExists($file->path);
    }

    public function test_proof_submission_rules(): void
    {
        $invoice = $this->openInvoice();

        $this->submit($invoice, ['amount' => '1.500.001'])->assertSessionHasErrors('amount');
        $this->submit($invoice, ['amount' => '0'])->assertSessionHasErrors('amount');
        $this->submit($invoice, ['amount' => '1500.50'])->assertSessionHasErrors('amount');
        $this->submit($invoice, ['paid_on' => '2026-10-16'])->assertSessionHasErrors('paid_on');
        $this->submit($invoice, ['paid_on' => '2025-10-01'])->assertSessionHasErrors('paid_on');
        $this->submit($invoice, [], UploadedFile::fake()->create('bukti.docx', 10))->assertSessionHasErrors('file');
        $this->submit($invoice, [], UploadedFile::fake()->create('besar.jpg', 6000, 'image/jpeg'))->assertSessionHasErrors('file');
        $this->actingAs($this->owner)->post(route('owner.invoices.proofs.store', $invoice), ['amount' => '1.000'])->assertSessionHasErrors('file');

        $this->assertSame(0, $this->asTenant($this->tenant, fn () => PaymentProof::count()));
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_the_proof_amount_cannot_exceed_what_is_still_owed(): void
    {
        $invoice = $this->openInvoice();
        $this->record([[$invoice, 1_000_000]]);

        $this->submit($invoice, ['amount' => '600.000'])->assertSessionHasErrors('amount');
        $this->submit($invoice, ['amount' => '500.000'])->assertSessionHasNoErrors();
    }

    public function test_paid_and_voided_invoices_do_not_accept_proofs(): void
    {
        $paid = $this->openInvoice();
        $this->record([[$paid, 1_500_000]]);
        $voided = $this->openInvoice();
        $this->asTenant($this->tenant, fn () => app(VoidInvoice::class)($voided, 'Salah terbit'));

        $this->submit($paid, ['amount' => '1000'])->assertSessionHasErrors('general');
        $this->submit($voided, ['amount' => '1000'])->assertSessionHasErrors('general');
        $this->assertSame(0, $this->asTenant($this->tenant, fn () => PaymentProof::count()));
    }

    public function test_the_transfer_proof_attachment_cannot_be_deleted(): void
    {
        $invoice = $this->openInvoice();
        $this->proofOf($invoice);
        $file = $this->asTenant($this->tenant, fn () => Attachment::firstOrFail());

        $this->actingAs($this->owner)->delete(route('owner.attachments.destroy', $file))->assertSessionHasErrors('general');

        $this->assertSame(1, $this->asTenant($this->tenant, fn () => Attachment::count()));
        Storage::disk('private')->assertExists($file->path);
    }

    // ---- Persetujuan ----

    public function test_approval_creates_the_payment_closes_the_invoice_and_links_them(): void
    {
        Event::fake([PaymentRecorded::class]);
        $invoice = $this->openInvoice();
        $proof = $this->proofOf($invoice);

        $this->approve($proof)->assertSessionHasNoErrors()->assertRedirect(route('owner.proofs.index'));

        $approved = $this->proof($invoice);
        $payment = $this->asTenant($this->tenant, fn () => Payment::findOrFail($approved->payment_id));

        $this->assertSame([ProofStatus::Approved, $this->owner->id], [$approved->status, $approved->reviewed_by]);
        $this->assertNotNull($approved->reviewed_at);
        $this->assertSame([1_500_000, PaymentMethod::Transfer, 'KWT-2026-10-0001'], [$payment->amount, $payment->method, $payment->receipt_number]);
        $this->assertSame('2026-10-14', $payment->paid_on->toDateString());
        $this->assertSame($invoice->resident_id, $payment->resident_id);
        $this->assertSame([InvoiceStatus::Paid, 1_500_000], [$this->fresh($invoice)->status, $this->fresh($invoice)->paid_total]);
        Event::assertDispatchedTimes(PaymentRecorded::class, 1);
    }

    public function test_the_reviewer_may_approve_a_smaller_amount_but_never_a_larger_one(): void
    {
        $invoice = $this->openInvoice();
        $proof = $this->proofOf($invoice, 1_000_000);

        $this->approve($proof, ['amount' => '1.000.001'])->assertSessionHasErrors('amount');
        $this->approve($proof, ['amount' => '1.500.000'])->assertSessionHasErrors('amount');
        $this->assertSame(ProofStatus::Pending, $this->proof($invoice)->status);
        $this->assertSame(0, $this->fresh($invoice)->paid_total);

        $this->approve($proof, ['amount' => '600.000'])->assertSessionHasNoErrors();

        $fresh = $this->fresh($invoice);
        $this->assertSame([InvoiceStatus::Partial, 600_000], [$fresh->status, $fresh->paid_total]);
        $this->assertSame(600_000, $this->asTenant($this->tenant, fn () => Payment::sole()->amount));
    }

    public function test_a_proof_can_only_be_acted_on_once(): void
    {
        $invoice = $this->openInvoice();
        $proof = $this->proofOf($invoice, 500_000);

        $this->approve($proof)->assertSessionHasNoErrors();
        $this->approve($proof)->assertSessionHasErrors('general');
        $this->reject($proof)->assertSessionHasErrors('general');

        $this->assertSame(1, $this->asTenant($this->tenant, fn () => Payment::count()));
        $this->assertSame(500_000, $this->fresh($invoice)->paid_total);
    }

    public function test_approval_is_refused_when_the_invoice_changed_in_the_meantime(): void
    {
        $invoice = $this->openInvoice();
        $proof = $this->proofOf($invoice, 1_500_000);

        // Sementara menunggu, tagihan dilunasi lewat jalur lain.
        $this->record([[$invoice, 1_500_000]]);
        $this->approve($proof)->assertSessionHasErrors('general');
        $this->assertSame(ProofStatus::Pending, $this->proof($invoice)->status);
        $this->assertSame(1, $this->asTenant($this->tenant, fn () => Payment::count()));
    }

    public function test_approval_is_refused_when_the_remaining_balance_shrank(): void
    {
        $invoice = $this->openInvoice();
        $proof = $this->proofOf($invoice, 1_500_000);

        $this->record([[$invoice, 1_000_000]]);

        $this->approve($proof)->assertSessionHasErrors('general');
        $this->approve($proof, ['amount' => '500.000'])->assertSessionHasNoErrors();
        $this->assertSame(InvoiceStatus::Paid, $this->fresh($invoice)->status);
    }

    public function test_approval_is_refused_for_a_voided_invoice(): void
    {
        $invoice = $this->openInvoice();
        $proof = $this->proofOf($invoice, 500_000);
        $this->asTenant($this->tenant, fn () => app(VoidInvoice::class)($invoice, 'Tagihan dibatalkan'));

        $this->approve($proof)->assertSessionHasErrors('general');
        $this->assertSame(0, $this->asTenant($this->tenant, fn () => Payment::count()));
    }

    public function test_an_old_proof_with_a_transfer_date_beyond_a_year_surfaces_a_general_error(): void
    {
        $invoice = $this->openInvoice();
        $proof = $this->proofOf($invoice, 500_000);
        $this->asTenant($this->tenant, fn () => PaymentProof::whereKey($proof->id)->update(['paid_on' => '2024-01-01']));

        $this->approve($proof)->assertSessionHasErrors('general');
        $this->assertSame(ProofStatus::Pending, $this->proof($invoice)->status);
    }

    // ---- Penolakan ----

    public function test_rejection_requires_a_reason_and_does_not_touch_the_invoice(): void
    {
        Event::fake([ProofRejected::class]);
        $invoice = $this->openInvoice();
        $proof = $this->proofOf($invoice);

        $this->reject($proof, '')->assertSessionHasErrors('reason');
        $this->reject($proof, 'ab')->assertSessionHasErrors('reason');
        Event::assertNotDispatched(ProofRejected::class);

        $this->reject($proof, 'Nominal tidak sesuai')->assertSessionHasNoErrors();

        $rejected = $this->proof($invoice);
        $this->assertSame([ProofStatus::Rejected, 'Nominal tidak sesuai', $this->owner->id], [$rejected->status, $rejected->reject_reason, $rejected->reviewed_by]);
        $this->assertNull($rejected->payment_id);
        $this->assertSame([InvoiceStatus::Issued, 0], [$this->fresh($invoice)->status, $this->fresh($invoice)->paid_total]);
        Event::assertDispatchedTimes(ProofRejected::class, 1);
    }

    // ---- Hak akses dan isolasi ----

    public function test_staff_may_submit_approve_and_reject_and_suspended_tenants_may_not(): void
    {
        $staff = User::factory()->for($this->tenant)->staff()->create();
        $invoice = $this->openInvoice();

        $this->submit($invoice, ['amount' => '500.000'], null, $staff)->assertSessionHasNoErrors();
        $this->approve($this->proof($invoice), [], $staff)->assertSessionHasNoErrors();

        $second = $this->proofOf($invoice, 500_000);
        $this->reject($second, 'Buram, tidak terbaca', $staff)->assertSessionHasNoErrors();

        $this->asTenant($this->tenant, fn () => $this->tenant->update(['status' => 'suspended']));
        $this->submit($invoice, ['amount' => '100.000'])->assertForbidden();
        $this->approve($second)->assertForbidden();
        $this->reject($second)->assertForbidden();
    }

    public function test_other_tenants_cannot_see_or_act_on_proofs(): void
    {
        $invoice = $this->openInvoice();
        $proof = $this->proofOf($invoice, 500_000);

        $other = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id]);
        $stranger = User::factory()->for($other)->create();

        $this->approve($proof, [], $stranger)->assertNotFound();
        $this->reject($proof, 'Diretas', $stranger)->assertNotFound();
        $this->submit($invoice, ['amount' => '1.000'], null, $stranger)->assertNotFound();
        $this->actingAs($stranger)->get(route('owner.proofs.index'))->assertOk()->assertDontSee($invoice->number);

        $this->assertSame(ProofStatus::Pending, $this->proof($invoice)->status);
        $this->assertSame(0, $this->asTenant($other, fn () => PaymentProof::count()));
    }

    // ---- Pengingat ----

    public function test_pending_older_than_three_days_uses_a_strict_boundary(): void
    {
        $invoice = $this->openInvoice();
        $exactlyThree = $this->proofOf($invoice, 100_000);
        $overThree = $this->proofOf($invoice, 100_000);
        $fresh = $this->proofOf($invoice, 100_000);
        $approvedOld = $this->proofOf($invoice, 100_000);

        $now = now();
        $this->asTenant($this->tenant, function () use ($exactlyThree, $overThree, $approvedOld, $now) {
            PaymentProof::whereKey($exactlyThree->id)->update(['created_at' => $now->copy()->subDays(3)]);
            PaymentProof::whereKey($overThree->id)->update(['created_at' => $now->copy()->subDays(3)->subSecond()]);
            PaymentProof::whereKey($approvedOld->id)->update(['created_at' => $now->copy()->subDays(10), 'status' => ProofStatus::Approved]);
        });

        $stale = $this->asTenant($this->tenant, fn () => PaymentProof::pendingOlderThan(3, $now)->pluck('id')->all());

        $this->assertSame([$overThree->id], $stale);
        $this->assertNotContains($exactlyThree->id, $stale);
        $this->assertNotContains($fresh->id, $stale);
        $this->assertNotContains($approvedOld->id, $stale);
    }

    public function test_stale_proofs_are_flagged_with_text_in_the_queue(): void
    {
        $invoice = $this->openInvoice();
        $proof = $this->proofOf($invoice, 100_000);
        $this->asTenant($this->tenant, fn () => PaymentProof::whereKey($proof->id)->update(['created_at' => now()->subDays(4)]));

        $this->actingAs($this->owner)->get(route('owner.proofs.index'))->assertOk()->assertSee('Menunggu lebih dari 3 hari');
    }
}
