<?php

namespace Tests\Feature\Portal;

use App\Actions\Attachments\StoreAttachment;
use App\Enums\AttachmentCategory;
use App\Enums\ProofStatus;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintComment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use App\Models\Resident;
use App\Services\Messaging\MessageSubjects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PortalFixtures;
use Tests\TestCase;

class PortalContentTest extends TestCase
{
    use PortalFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->setUpPortal();
    }

    private function account(array $attributes = []): PaymentAccount
    {
        return $this->asTenant($this->tenant, fn () => PaymentAccount::create($attributes + [
            'type' => 'bank', 'bank_name' => 'BCA', 'account_number' => '8820-1928-30', 'account_name' => 'Hendra Wijaya',
            'is_default' => true, 'is_active' => true, 'instructions' => 'Cantumkan nomor kamar',
        ]));
    }

    /**
     * Unggah bukti. Menggeser waktu semenit tiap panggilan supaya pembatas laju unggahan (5 per menit) tidak ikut
     * menghadang tes yang memang bukan tentang pembatas laju; $throttled = true membiarkannya bekerja.
     */
    private function proof(string $token, Invoice $invoice, array $data = [], ?UploadedFile $file = null, bool $throttled = false)
    {
        if (! $throttled) {
            $this->travel(61)->seconds();
        }

        return $this->post($this->portalUrl($token, '/tagihan/'.$invoice->id.'/bukti'), $data + [
            'amount' => '1.500.000', 'file' => $file ?? UploadedFile::fake()->image('bukti.jpg'),
        ]);
    }

    // ---- Tagihan dan rekening ----

    public function test_the_home_lists_only_the_residents_open_invoices_with_the_amount_due(): void
    {
        [$token, $invoice, $resident] = $this->residentWithInvoice();
        $this->record([[$invoice, 500_000]]);
        [, , $other] = $this->residentWithInvoice();

        $this->get($this->portalUrl($token))->assertOk()
            ->assertSee('data-stat="outstanding">Rp 1.000.000</div>', false)
            ->assertSee($invoice->number)
            ->assertDontSee($other->name);
    }

    public function test_paid_and_void_invoices_are_not_on_the_home_and_void_ones_are_not_openable(): void
    {
        [$token, $invoice] = $this->residentWithInvoice();
        $this->record([[$invoice, 1_500_000]]);

        $this->get($this->portalUrl($token))->assertOk()->assertSee('data-stat="outstanding">Rp 0</div>', false)->assertSee('Tidak ada tagihan yang menunggu pembayaran');

        $this->asTenant($this->tenant, fn () => Invoice::query()->whereKey($invoice->id)->update(['status' => 'void']));
        $this->get($this->portalUrl($token, '/tagihan/'.$invoice->id))->assertNotFound();
        $this->get($this->portalUrl($token, '/tagihan'))->assertOk()->assertDontSee($invoice->number);
    }

    public function test_the_invoice_page_shows_the_accounts_with_a_copy_button_and_instructions(): void
    {
        $this->account();
        [$token, $invoice] = $this->residentWithInvoice();

        $this->get($this->portalUrl($token, '/tagihan/'.$invoice->id))->assertOk()
            ->assertSee('BCA')->assertSee('8820-1928-30')->assertSee('a.n. Hendra Wijaya')->assertSee('Cantumkan nomor kamar')
            ->assertSee('data-copy="8820-1928-30"', false)->assertSee('Salin nomor rekening')
            ->assertSee('Unggah bukti transfer');
    }

    public function test_inactive_and_other_property_accounts_are_not_shown(): void
    {
        $this->account(['bank_name' => 'Bank Nonaktif', 'account_number' => '1111111', 'is_active' => false, 'is_default' => false]);
        $otherProperty = $this->propertyFor($this->tenant, ['name' => 'Kos Lain']);
        $this->account(['bank_name' => 'Bank Properti Lain', 'account_number' => '2222222', 'property_id' => $otherProperty->id, 'is_default' => false]);
        $this->account(['bank_name' => 'Bank Umum', 'account_number' => '3333333', 'is_default' => false]);
        [$token, $invoice] = $this->residentWithInvoice();

        $this->get($this->portalUrl($token, '/tagihan/'.$invoice->id))->assertSee('Bank Umum')
            ->assertDontSee('Bank Nonaktif')->assertDontSee('Bank Properti Lain');
    }

    public function test_a_paid_invoice_offers_no_payment_instructions(): void
    {
        $this->account();
        [$token, $invoice] = $this->residentWithInvoice();
        $this->record([[$invoice, 1_500_000]]);

        $this->get($this->portalUrl($token, '/tagihan/'.$invoice->id))->assertOk()
            ->assertSee('Lunas')->assertDontSee('Cara membayar')->assertDontSee('Unggah bukti transfer');
    }

    public function test_the_qris_image_is_served_only_for_an_active_account_of_this_tenant(): void
    {
        $account = $this->account(['type' => 'qris', 'bank_name' => null, 'account_number' => null]);
        $this->asTenant($this->tenant, fn () => app(StoreAttachment::class)($account, UploadedFile::fake()->image('qris.png'), AttachmentCategory::QrisImage));
        [$token] = $this->residentWithInvoice();

        $this->get($this->portalUrl($token, '/rekening/'.$account->id.'/qris'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->asTenant($this->tenant, fn () => $account->update(['is_active' => false]));
        $this->get($this->portalUrl($token, '/rekening/'.$account->id.'/qris'))->assertNotFound();
        $this->get($this->portalUrl($token, '/rekening/999999/qris'))->assertNotFound();
    }

    public function test_another_tenants_qris_cannot_be_fetched(): void
    {
        [$other] = $this->tenantWithOwner('business');
        $theirs = $this->asTenant($other, fn () => PaymentAccount::create(['type' => 'qris', 'account_name' => 'Tetangga', 'is_default' => true, 'is_active' => true]));
        $this->asTenant($other, fn () => app(StoreAttachment::class)($theirs, UploadedFile::fake()->image('q.png'), AttachmentCategory::QrisImage));
        [$token] = $this->residentWithInvoice();

        $this->get($this->portalUrl($token, '/rekening/'.$theirs->id.'/qris'))->assertNotFound();
    }

    // ---- IDOR ----

    public function test_a_resident_cannot_open_another_residents_invoice_even_knowing_its_id(): void
    {
        [$a, $invoiceA] = $this->residentWithInvoice();
        [$b, $invoiceB] = $this->residentWithInvoice();

        $this->get($this->portalUrl($a, '/tagihan/'.$invoiceB->id))->assertNotFound();
        $this->get($this->portalUrl($b, '/tagihan/'.$invoiceA->id))->assertNotFound();
        $this->get($this->portalUrl($a, '/tagihan/'.$invoiceA->id))->assertOk();
        $this->get($this->portalUrl($a, '/tagihan/abc'))->assertNotFound();
    }

    public function test_a_resident_cannot_open_or_download_another_residents_receipt(): void
    {
        [$a, $invoiceA] = $this->residentWithInvoice();
        [$b, $invoiceB] = $this->residentWithInvoice();
        $paymentA = $this->record([[$invoiceA, 500_000]]);
        $paymentB = $this->record([[$invoiceB, 700_000]]);

        $this->get($this->portalUrl($a, '/kuitansi/'.$paymentB->id))->assertNotFound();
        $this->get($this->portalUrl($a, '/kuitansi/'.$paymentB->id.'/pdf'))->assertNotFound();
        $this->get($this->portalUrl($a, '/kuitansi/'.$paymentA->id))->assertOk()->assertSee($paymentA->receipt_number)->assertDontSee($paymentB->receipt_number);
    }

    public function test_a_resident_cannot_see_or_comment_on_another_residents_complaint(): void
    {
        [$a] = $this->residentWithInvoice();
        [$b, , $residentB] = $this->residentWithInvoice();
        $complaintB = $this->createComplaintFor($b, 'Keluhan rahasia B');

        $this->get($this->portalUrl($a, '/komplain/'.$complaintB))->assertNotFound();
        $this->post($this->portalUrl($a, '/komplain/'.$complaintB.'/komentar'), ['body' => 'Halo'])->assertNotFound();
        $this->get($this->portalUrl($a, '/komplain'))->assertOk()->assertDontSee('Keluhan rahasia B');
        $this->assertSame(0, $this->asTenant($this->tenant, fn () => ComplaintComment::count()));
        $this->assertNotNull($residentB);
    }

    public function test_a_resident_cannot_upload_a_proof_for_another_residents_invoice(): void
    {
        [$a] = $this->residentWithInvoice();
        [, $invoiceB] = $this->residentWithInvoice();

        $this->proof($a, $invoiceB)->assertNotFound();

        $this->assertSame(0, $this->asTenant($this->tenant, fn () => PaymentProof::count()));
        $this->assertSame(0, $this->asTenant($this->tenant, fn () => Attachment::count()));
    }

    // ---- Riwayat dan kuitansi ----

    public function test_payment_history_shows_active_payments_with_receipts_and_hides_voided_ones(): void
    {
        [$token, $invoice] = $this->residentWithInvoice();
        $kept = $this->record([[$invoice, 400_000]]);
        $voided = $this->record([[$invoice, 300_000]]);
        $this->actingAs($this->owner)->post(route('owner.payments.void', $voided), ['reason' => 'Salah catat']);
        auth()->logout();

        $this->get($this->portalUrl($token, '/pembayaran'))->assertOk()
            ->assertSee('Rp 400.000')->assertDontSee('Rp 300.000')
            ->assertSee(route('portal.payments.receipt', ['token' => $token, 'payment' => $kept->id]), false);
        $this->get($this->portalUrl($token, '/kuitansi/'.$voided->id))->assertNotFound();
    }

    public function test_the_receipt_page_and_pdf_work(): void
    {
        [$token, $invoice] = $this->residentWithInvoice();
        $payment = $this->record([[$invoice, 900_000]]);

        $this->get($this->portalUrl($token, '/kuitansi/'.$payment->id))->assertOk()
            ->assertSee($payment->receipt_number)->assertSee('Rp 900.000')->assertSee('Unduh PDF');

        $pdf = $this->get($this->portalUrl($token, '/kuitansi/'.$payment->id.'/pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_the_lease_page_shows_the_summary_and_no_internal_notes(): void
    {
        [$token, , $resident] = $this->residentWithInvoice();
        $this->asTenant($this->tenant, fn () => $resident->leases()->update([
            'leases.notes' => 'CATATAN-INTERNAL-PEMILIK', 'leases.checkin_notes' => 'CHECKIN-INTERNAL',
        ]));

        $this->get($this->portalUrl($token, '/kontrak'))->assertOk()
            ->assertSee('Rp 1.500.000')->assertSee('Aktif')->assertSee($this->property->name)
            ->assertDontSee('CATATAN-INTERNAL-PEMILIK')->assertDontSee('CHECKIN-INTERNAL');
    }

    // ---- Unggah bukti transfer ----

    public function test_a_proof_upload_goes_to_the_pending_queue_and_does_not_touch_the_invoice(): void
    {
        [$token, $invoice, $resident] = $this->residentWithInvoice();

        $this->proof($token, $invoice, ['amount' => '1.500.000', 'sender_note' => 'Transfer dari rekening ayah'])
            ->assertRedirect(route('portal.invoices.show', ['token' => $token, 'invoice' => $invoice->id]))
            ->assertSessionHasNoErrors();

        $proof = $this->asTenant($this->tenant, fn () => PaymentProof::sole());
        $this->assertSame([ProofStatus::Pending, 1_500_000, $invoice->id, $resident->id, 'Transfer dari rekening ayah'], [
            $proof->status, $proof->amount, $proof->invoice_id, $proof->resident_id, $proof->sender_note,
        ]);

        $attachment = $this->asTenant($this->tenant, fn () => Attachment::sole());
        $this->assertSame(['payment_proof', 'transfer_proof', $resident->id, null], [
            $attachment->attachable_type, $attachment->category->value, $attachment->uploaded_by_resident_id, $attachment->uploaded_by,
        ]);
        Storage::disk('private')->assertExists($attachment->path);

        $fresh = $this->fresh($invoice);
        $this->assertSame([0, 'issued'], [$fresh->paid_total, $fresh->status->value]);
        $this->assertSame(0, $this->asTenant($this->tenant, fn () => Payment::count()));
    }

    public function test_the_proof_then_appears_in_the_owners_queue_and_on_the_portal_with_its_status(): void
    {
        [$token, $invoice] = $this->residentWithInvoice();
        $this->proof($token, $invoice, ['amount' => '1.000.000']);

        $this->actingAs($this->owner)->get(route('owner.proofs.index'))->assertOk()->assertSee('Rp 1.000.000');
        auth()->logout();

        $this->get($this->portalUrl($token, '/tagihan/'.$invoice->id))->assertSee('Menunggu verifikasi')->assertSee('Rp 1.000.000');
        $this->get($this->portalUrl($token))->assertSee('1 bukti transfer menunggu diperiksa pengelola');

        $proof = $this->asTenant($this->tenant, fn () => PaymentProof::sole());
        $this->actingAs($this->owner)->post(route('owner.proofs.reject', $proof), ['reason' => 'Nominal tidak sesuai']);
        auth()->logout();
        $this->get($this->portalUrl($token, '/tagihan/'.$invoice->id))->assertSee('Ditolak')->assertSee('Alasan penolakan: Nominal tidak sesuai');
    }

    public function test_proof_validation_size_type_and_amount(): void
    {
        [$token, $invoice] = $this->residentWithInvoice();

        $this->proof($token, $invoice, [], UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf'))->assertSessionHasErrors('file');
        $this->proof($token, $invoice, [], UploadedFile::fake()->create('virus.exe', 10))->assertSessionHasErrors('file');
        $this->proof($token, $invoice, [], UploadedFile::fake()->create('palsu.png', 10, 'text/plain'))->assertSessionHasErrors();
        $this->proof($token, $invoice, ['amount' => '0'])->assertSessionHasErrors('amount');
        $this->proof($token, $invoice, ['amount' => '1,5'])->assertSessionHasErrors('amount');
        $this->proof($token, $invoice, ['amount' => '1.500.001'])->assertSessionHasErrors('amount');
        $this->post($this->portalUrl($token, '/tagihan/'.$invoice->id.'/bukti'), ['amount' => '1.000'])->assertSessionHasErrors('file');
        $this->proof($token, $invoice, ['paid_on' => '2026-10-16'])->assertSessionHasErrors('paid_on');

        $this->assertSame(0, $this->asTenant($this->tenant, fn () => PaymentProof::count()));
        $this->proof($token, $invoice, [], UploadedFile::fake()->create('ok.pdf', 100, 'application/pdf'))->assertSessionHasNoErrors();
        $this->assertSame(1, $this->asTenant($this->tenant, fn () => PaymentProof::count()));
    }

    public function test_a_proof_cannot_be_uploaded_for_a_paid_invoice(): void
    {
        [$token, $invoice] = $this->residentWithInvoice();
        $this->record([[$invoice, 1_500_000]]);

        $this->proof($token, $invoice, ['amount' => '1.000'])->assertSessionHasErrors('general');

        $this->assertSame(0, $this->asTenant($this->tenant, fn () => PaymentProof::count()));
    }

    public function test_uploads_are_rate_limited(): void
    {
        [$token, $invoice] = $this->residentWithInvoice();
        for ($i = 0; $i < 5; $i++) {
            $this->proof($token, $invoice, ['amount' => '1.000'], null, true)->assertSessionHasNoErrors();
            // Bukti diperiksa pemilik: batas bukti menunggu per tagihan tidak ikut diuji di sini.
            $this->asTenant($this->tenant, fn () => PaymentProof::query()->update(['status' => ProofStatus::Rejected]));
        }

        $this->proof($token, $invoice, ['amount' => '1.000'], null, true)->assertStatus(429);
        $this->assertSame(5, $this->asTenant($this->tenant, fn () => PaymentProof::count()));

        // Pembatas dihitung per tautan: tautan penghuni lain tidak ikut terhenti.
        [$other, $otherInvoice] = $this->residentWithInvoice();
        $this->proof($other, $otherInvoice, ['amount' => '1.000'], null, true)->assertSessionHasNoErrors();
    }

    // ---- Komplain ----

    private function createComplaintFor(string $token, string $title): int
    {
        $this->post($this->portalUrl($token, '/komplain'), [
            'category' => 'electricity', 'title' => $title, 'description' => 'Lampu kamar tidak menyala.',
        ])->assertSessionHasNoErrors();

        return (int) $this->asTenant($this->tenant, fn () => Complaint::query()->where('title', $title)->value('id'));
    }

    public function test_a_resident_files_a_complaint_for_their_own_room_with_a_photo(): void
    {
        [$token, , $resident] = $this->residentWithInvoice();
        $roomId = $this->asTenant($this->tenant, fn () => $resident->leases()->first()->room_id);

        $this->post($this->portalUrl($token, '/komplain'), [
            'category' => 'water', 'title' => 'Air tidak mengalir', 'description' => 'Sejak pagi air mati.',
            'photo' => UploadedFile::fake()->image('keran.jpg'),
        ])->assertSessionHasNoErrors();

        $complaint = $this->asTenant($this->tenant, fn () => Complaint::sole());
        $this->assertSame([$resident->id, $roomId, 'new', 'water'], [$complaint->resident_id, $complaint->room_id, $complaint->status->value, $complaint->category->value]);

        $photo = $this->asTenant($this->tenant, fn () => Attachment::sole());
        $this->assertSame(['complaint_photo', $resident->id], [$photo->category->value, $photo->uploaded_by_resident_id]);

        $this->get($this->portalUrl($token, '/komplain/'.$complaint->id))->assertOk()->assertSee('Air tidak mengalir')->assertSee('Baru');
    }

    public function test_the_resident_and_room_of_a_complaint_cannot_be_chosen_by_the_client(): void
    {
        [$token, , $resident] = $this->residentWithInvoice();
        [, , $other] = $this->residentWithInvoice();
        $otherRoom = $this->asTenant($this->tenant, fn () => $other->leases()->first()->room_id);

        $this->post($this->portalUrl($token, '/komplain'), [
            'category' => 'water', 'title' => 'Air mati', 'description' => 'Air mati sejak pagi',
            'resident_id' => $other->id, 'room_id' => $otherRoom, 'property_id' => 999, 'status' => 'done', 'tenant_id' => 999,
        ])->assertSessionHasNoErrors();

        $complaint = $this->asTenant($this->tenant, fn () => Complaint::sole());
        $this->assertSame([$resident->id, 'new', $this->tenant->id], [$complaint->resident_id, $complaint->status->value, $complaint->tenant_id]);
        $this->assertNotSame($otherRoom, $complaint->room_id);
    }

    public function test_complaint_validation(): void
    {
        [$token] = $this->residentWithInvoice();
        $post = fn (array $overrides) => $this->post($this->portalUrl($token, '/komplain'), $overrides + [
            'category' => 'water', 'title' => 'Air mati', 'description' => 'Air mati sejak pagi',
        ]);

        $post(['category' => 'banjir'])->assertSessionHasErrors('category');
        $post(['title' => 'ab'])->assertSessionHasErrors('title');
        $post(['description' => ''])->assertSessionHasErrors('description');
        $post(['photo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertSessionHasErrors('photo');
        $post(['photo' => UploadedFile::fake()->create('besar.png', 6000, 'image/png')])->assertSessionHasErrors('photo');

        $this->assertSame(0, $this->asTenant($this->tenant, fn () => Complaint::count()));
    }

    public function test_the_conversation_is_two_way_hides_staff_names_and_closes_with_the_ticket(): void
    {
        [$token, , $resident] = $this->residentWithInvoice();
        $id = $this->createComplaintFor($token, 'Pintu rusak');
        $complaint = $this->asTenant($this->tenant, fn () => Complaint::findOrFail($id));

        $this->post($this->portalUrl($token, '/komplain/'.$id.'/komentar'), ['body' => 'Sudah dua hari begini.'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('owner.complaints.comments.store', $complaint), ['body' => 'Teknisi datang besok.']);
        auth()->logout();

        $this->get($this->portalUrl($token, '/komplain/'.$id))->assertOk()
            ->assertSeeInOrder(['Anda', 'Sudah dua hari begini.', 'Pengelola', 'Teknisi datang besok.'])
            ->assertDontSee($this->owner->name);

        $this->actingAs($this->owner)->post(route('owner.complaints.status', $complaint), ['status' => 'done']);
        auth()->logout();

        $this->get($this->portalUrl($token, '/komplain/'.$id))->assertSee('Selesai')->assertSee('Tiket ini sudah ditutup')->assertDontSee('Kirim komentar');
        $this->post($this->portalUrl($token, '/komplain/'.$id.'/komentar'), ['body' => 'Terlambat'])->assertSessionHasErrors('body');
        $this->assertNotNull($resident);
    }

    // ---- Pengalihan galat, draf, QRIS per properti, dan atribusi audit ----

    public function test_validation_failures_return_the_resident_to_the_right_portal_page_not_the_site_root(): void
    {
        [$token, $invoice] = $this->residentWithInvoice();
        $invoicePage = route('portal.invoices.show', ['token' => $token, 'invoice' => $invoice->id]);

        // Galat dari Form Request.
        $this->proof($token, $invoice, [], UploadedFile::fake()->create('virus.exe', 10))->assertRedirect($invoicePage)->assertSessionHasErrors('file');
        // Galat dari Action (nominal melebihi sisa tagihan).
        $this->proof($token, $invoice, ['amount' => '9.999.999'])->assertRedirect($invoicePage)->assertSessionHasErrors('amount');
        // Pesan galat benar-benar tampil di halaman tujuan.
        $this->get($invoicePage)->assertSee('Nominal melebihi sisa tagihan');

        $createPage = route('portal.complaints.create', ['token' => $token]);
        $this->post($this->portalUrl($token, '/komplain'), ['category' => 'banjir', 'title' => 'x', 'description' => 'y'])->assertRedirect($createPage)->assertSessionHasErrors('category');

        $id = $this->createComplaintFor($token, 'Pintu rusak');
        $showPage = route('portal.complaints.show', ['token' => $token, 'complaint' => $id]);
        $this->post($this->portalUrl($token, '/komplain/'.$id.'/komentar'), ['body' => ''])->assertRedirect($showPage)->assertSessionHasErrors('body');
    }

    public function test_a_resident_without_an_active_lease_is_sent_back_to_the_form_with_the_reason(): void
    {
        [$token, , $resident] = $this->residentWithInvoice();
        $this->asTenant($this->tenant, fn () => $resident->leases()->update(['leases.status' => 'ended', 'leases.active_room_id' => null]));

        $this->post($this->portalUrl($token, '/komplain'), ['category' => 'water', 'title' => 'Air mati', 'description' => 'Air mati sejak pagi'])
            ->assertRedirect(route('portal.complaints.create', ['token' => $token]))
            ->assertSessionHasErrors('resident_id');
    }

    public function test_draft_invoices_are_invisible_and_cannot_receive_proofs(): void
    {
        [$token, $invoice] = $this->residentWithInvoice();
        $this->asTenant($this->tenant, fn () => Invoice::query()->whereKey($invoice->id)->update(['status' => 'draft']));

        $this->get($this->portalUrl($token, '/tagihan/'.$invoice->id))->assertNotFound();
        $this->get($this->portalUrl($token, '/tagihan'))->assertOk()->assertDontSee($invoice->number);
        $this->proof($token, $invoice)->assertNotFound();
        $this->assertSame(0, $this->asTenant($this->tenant, fn () => PaymentProof::count()));
    }

    public function test_a_property_specific_qris_is_only_served_to_residents_of_that_property(): void
    {
        $otherProperty = $this->propertyFor($this->tenant, ['name' => 'Kos Lain']);
        $account = $this->account(['type' => 'qris', 'bank_name' => null, 'account_number' => null, 'property_id' => $otherProperty->id]);
        $this->asTenant($this->tenant, fn () => app(StoreAttachment::class)($account, UploadedFile::fake()->image('qris.png'), AttachmentCategory::QrisImage));
        [$token] = $this->residentWithInvoice();

        $this->get($this->portalUrl($token, '/rekening/'.$account->id.'/qris'))->assertNotFound();

        $general = $this->account(['type' => 'qris', 'bank_name' => null, 'account_number' => null, 'is_default' => false]);
        $this->asTenant($this->tenant, fn () => app(StoreAttachment::class)($general, UploadedFile::fake()->image('qris2.png'), AttachmentCategory::QrisImage));
        $this->get($this->portalUrl($token, '/rekening/'.$general->id.'/qris'))->assertOk();
    }

    public function test_a_residents_portal_actions_are_not_attributed_to_whoever_is_logged_in_in_the_same_browser(): void
    {
        [$token] = $this->residentWithInvoice();
        [, $strangerOwner] = $this->tenantWithOwner('business');

        $this->actingAs($strangerOwner)->post($this->portalUrl($token, '/komplain'), [
            'category' => 'water', 'title' => 'Air mati', 'description' => 'Air mati sejak pagi',
        ])->assertSessionHasNoErrors();

        $logs = $this->withoutTenant(fn () => AuditLog::query()->where('tenant_id', $this->tenant->id)->get());
        $this->assertNotEmpty($logs);
        $this->assertSame([null], $logs->pluck('user_id')->unique()->values()->all(), 'Aksi penghuni tidak boleh memuat id pengguna lain.');
        $this->assertSame(0, $this->withoutTenant(fn () => AuditLog::query()->where('user_id', $strangerOwner->id)->count()));
    }

    // ---- Performa ----

    public function test_the_home_runs_a_constant_number_of_queries_regardless_of_the_invoice_count(): void
    {
        [$token, , $resident] = $this->residentWithInvoice();
        $count = function () use ($token) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($this->portalUrl($token))->assertOk();

            return count(DB::getQueryLog());
        };

        $count(); // permintaan pertama menulis last_used_at: dibuang supaya perbandingan sebanding
        $this->travel(2)->minutes();
        $few = $count();

        $lease = $this->asTenant($this->tenant, fn () => $resident->leases()->first());
        $this->asTenant($this->tenant, fn () => Invoice::factory()->for($this->tenant)->for($this->property)->for($lease)->for($resident, 'resident')
            ->count(12)->sequence(fn ($sequence) => ['number' => 'PORTAL-'.$sequence->index, 'due_date' => '2026-10-20'])->create());

        $this->travel(2)->minutes();
        $many = $count();

        $this->assertSame($few, $many, "Query bertambah dari {$few} menjadi {$many}: ada N+1 di beranda.");
        $this->assertLessThanOrEqual(20, $many);
    }

    public function test_the_invoice_list_is_paginated_without_n_plus_one(): void
    {
        [$token, , $resident] = $this->residentWithInvoice();
        $lease = $this->asTenant($this->tenant, fn () => $resident->leases()->first());
        $this->asTenant($this->tenant, fn () => Invoice::factory()->for($this->tenant)->for($this->property)->for($lease)->for($resident, 'resident')
            ->count(25)->sequence(fn ($sequence) => ['number' => 'PORTAL-'.$sequence->index])->create());

        DB::enableQueryLog();
        $this->get($this->portalUrl($token, '/tagihan'))->assertOk();

        $this->assertLessThanOrEqual(15, count(DB::getQueryLog()));
        $this->get($this->portalUrl($token, '/tagihan?page=3'))->assertOk();
    }

    public function test_message_values_for_a_resident_never_carry_a_real_link(): void
    {
        [, , $resident] = $this->residentWithInvoice();

        $values = $this->asTenant($this->tenant, fn () => app(MessageSubjects::class)->portal(Resident::findOrFail($resident->id)));

        $this->assertSame('[[tautan-portal]]', $values['link']);
        $this->assertDoesNotMatchRegularExpression('/[A-Za-z0-9]{64}/', json_encode($values));
    }
}
