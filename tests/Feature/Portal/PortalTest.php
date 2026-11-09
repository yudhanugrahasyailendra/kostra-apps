<?php

namespace Tests\Feature\Portal;

use App\Actions\DeliverWhatsAppMessage;
use App\Actions\QueueWhatsAppMessage;
use App\Actions\RecordPayment;
use App\Actions\RevokePortalLinks;
use App\Enums\AttachmentCategory;
use App\Enums\ComplaintStatus;
use App\Enums\InvoiceStatus;
use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Enums\PaymentMethod;
use App\Enums\ProofStatus;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Attachment;
use App\Models\Complaint;
use App\Models\ComplaintComment;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use App\Models\Plan;
use App\Models\PortalToken;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Factories\PortalTokenFactory;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    private Property $propertyA;

    private Room $roomA;

    private Resident $budi;

    private Resident $citra;

    private Resident $siti;

    private Invoice $invoiceBudi;

    private Invoice $invoiceCitra;

    private Invoice $invoiceSiti;

    private string $tokenBudi;

    private string $tokenCitra;

    private string $tokenSiti;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-09 04:00:00');
        Storage::fake('attachments');
        RateLimiter::clear('portal-invalid:127.0.0.1');
        $this->seed(MessageTemplateSeeder::class);

        $this->tenantA = Tenant::factory()->create(['plan_id' => Plan::factory()->create(['monthly_message_quota' => 100])->id]);
        $this->tenantB = Tenant::factory()->create(['plan_id' => Plan::factory()->create(['monthly_message_quota' => 100])->id]);
        $this->ownerA = User::factory()->forTenant($this->tenantA)->owner()->create();

        [$this->budi, $this->invoiceBudi, $this->tokenBudi, $this->roomA, $this->propertyA] = $this->seedResident($this->tenantA, 'Budi Penghuni');
        [$this->citra, $this->invoiceCitra, $this->tokenCitra] = $this->seedResident($this->tenantA, 'Citra Penghuni', $this->propertyA);
        [$this->siti, $this->invoiceSiti, $this->tokenSiti] = $this->seedResident($this->tenantB, 'Siti Tenant B');

        $this->inTenant($this->tenantA, fn () => PaymentAccount::create([
            'type' => 'bank', 'bank_name' => 'BCA', 'account_number' => '8820491022', 'account_name' => 'Hendra', 'is_default' => true,
        ]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function inTenant(Tenant $tenant, callable $callback): mixed
    {
        return TenantContext::instance()->runAs($tenant, $callback);
    }

    /** @return array{0: Resident, 1: Invoice, 2: string, 3: Room, 4: Property} */
    private function seedResident(Tenant $tenant, string $name, ?Property $property = null): array
    {
        return $this->inTenant($tenant, function () use ($name, $property) {
            $property ??= Property::factory()->create();
            $room = Room::factory()->create(['property_id' => $property->id]);
            $resident = Resident::factory()->create(['name' => $name, 'phone' => '+62812'.fake()->numerify('########')]);
            $lease = Lease::factory()->create(['property_id' => $property->id, 'room_id' => $room->id, 'primary_resident_id' => $resident->id]);
            $invoice = Invoice::factory()->create([
                'property_id' => $property->id, 'lease_id' => $lease->id, 'resident_id' => $resident->id,
                'due_date' => now()->addDays(2)->toDateString(), 'notes' => 'Catatan internal pemilik',
            ]);
            [, $token] = PortalTokenFactory::issue($resident->id);

            return [$resident, $invoice, $token, $room, $property];
        });
    }

    private function pay(Resident $resident, Invoice $invoice, Tenant $tenant, User $by): Payment
    {
        return $this->inTenant($tenant, fn () => app(RecordPayment::class)->handle(
            $resident, $invoice->total, now(), PaymentMethod::Transfer, [$invoice->id => $invoice->total], $by,
        ));
    }

    /** @return array<string, mixed> */
    private function props(string $url): array
    {
        return $this->get($url)->viewData('page')['props'];
    }

    // Token

    public function test_beranda_menampilkan_tagihan_penghuni_dari_token(): void
    {
        $this->get("/p/{$this->tokenBudi}")
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Portal/Home')
                ->where('shell.resident.name', 'Budi Penghuni')
                ->where('shell.read_only', false)
                ->has('invoices', 1)
                ->where('invoices.0.number', $this->invoiceBudi->number)
                ->where('invoices.0.outstanding', 1500000)
                ->where('outstanding_total', 1500000)
                ->has('invoices.0.items', 1)
            );
    }

    public function test_token_tidak_valid_dicabut_atau_kedaluwarsa_menghasilkan_respons_identik(): void
    {
        [$revoked, $revokedPlain] = $this->inTenant($this->tenantA, fn () => PortalTokenFactory::issue($this->budi->id, ['revoked_at' => now()]));
        [, $expiredPlain] = $this->inTenant($this->tenantA, fn () => PortalTokenFactory::issue($this->budi->id, ['expires_at' => now()->subMinute()]));
        $unknown = PortalToken::generate();
        $this->inTenant($this->tenantA, fn () => $this->budi->delete());
        $deletedResidentToken = $this->tokenBudi;

        $bodies = [];
        foreach ([$unknown, $revokedPlain, $expiredPlain, $deletedResidentToken, 'bukan-token', str_repeat('A', 64), strtoupper($unknown)] as $token) {
            $response = $this->get("/p/{$token}")->assertNotFound();
            $page = $response->viewData('page');
            $bodies[] = json_encode([$page['component'], $page['props']['status'] ?? null, $page['props']['errors'] ?? null]);
            $this->assertStringNotContainsString('kedaluwarsa', strtolower((string) $response->getContent()) === '' ? '' : strip_tags(json_encode($page['props'])));
        }

        $this->assertCount(1, array_unique($bodies), 'Semua kegagalan harus menghasilkan respons yang sama.');
        $this->assertSame('Portal/Invalid', json_decode($bodies[0])[0]);
    }

    public function test_percobaan_token_salah_berulang_dibatasi(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->get('/p/'.PortalToken::generate())->assertNotFound();
        }

        $this->get('/p/'.PortalToken::generate())->assertStatus(429);
        // Token yang benar pun ditahan dari IP yang sedang dibatasi.
        $this->get("/p/{$this->tokenBudi}")->assertStatus(429);
    }

    public function test_hanya_hash_token_disimpan_dan_last_used_at_terisi(): void
    {
        $this->assertSame(0, PortalToken::query()->withoutGlobalScopes()->where('token_hash', $this->tokenBudi)->count()); // alasan: tes lintas tenant
        $row = $this->inTenant($this->tenantA, fn () => PortalToken::query()->where('token_hash', PortalToken::hash($this->tokenBudi))->sole());
        $this->assertSame(64, strlen($row->token_hash));
        $this->assertNull($row->last_used_at);

        $this->get("/p/{$this->tokenBudi}")->assertOk();

        $this->assertNotNull($this->inTenant($this->tenantA, fn () => $row->fresh())->last_used_at);
        $this->assertArrayNotHasKey('token_hash', $row->toArray());
    }

    public function test_pencabutan_langsung_menutup_akses(): void
    {
        $this->get("/p/{$this->tokenBudi}")->assertOk();

        $this->actingAs($this->ownerA)->post("/app/penghuni/{$this->budi->id}/portal/cabut")->assertSessionHas('success');

        auth()->logout();
        $this->get("/p/{$this->tokenBudi}")->assertNotFound();
        $this->get("/p/{$this->tokenBudi}/riwayat")->assertNotFound();
        $this->post("/p/{$this->tokenBudi}/komplain", [])->assertNotFound();
        // Tautan penghuni lain tidak ikut tercabut.
        $this->get("/p/{$this->tokenCitra}")->assertOk();
    }

    // IDOR dan isolasi tenant

    public function test_penghuni_a_tidak_bisa_membuka_data_penghuni_b_walau_tahu_id(): void
    {
        $payment = $this->pay($this->citra, $this->invoiceCitra, $this->tenantA, $this->ownerA);
        $complaint = $this->inTenant($this->tenantA, fn () => Complaint::factory()->create([
            'property_id' => $this->propertyA->id, 'room_id' => $this->roomA->id, 'resident_id' => $this->citra->id,
        ]));
        $mine = "/p/{$this->tokenBudi}";

        $this->get("$mine/tagihan/{$this->invoiceCitra->id}")->assertNotFound();
        $this->post("$mine/tagihan/{$this->invoiceCitra->id}/bukti", ['amount' => 1000, 'file' => UploadedFile::fake()->image('b.jpg')])->assertNotFound();
        $this->get("$mine/kuitansi/{$payment->id}")->assertNotFound();
        $this->get("$mine/kuitansi/{$payment->id}/pdf")->assertNotFound();
        $this->get("$mine/komplain/{$complaint->id}")->assertNotFound();
        $this->post("$mine/komplain/{$complaint->id}/komentar", ['body' => 'Halo'])->assertNotFound();

        $this->assertSame(0, $this->inTenant($this->tenantA, fn () => PaymentProof::query()->count()));
        $this->assertSame(0, $this->inTenant($this->tenantA, fn () => ComplaintComment::query()->count()));
        // Pemilik sah tetap bisa.
        $this->get("/p/{$this->tokenCitra}/tagihan/{$this->invoiceCitra->id}")->assertOk();
        $this->get("/p/{$this->tokenCitra}/kuitansi/{$payment->id}")->assertOk();
        $this->get("/p/{$this->tokenCitra}/komplain/{$complaint->id}")->assertOk();
    }

    public function test_token_tenant_lain_tidak_membuka_data_tenant_ini(): void
    {
        $paymentA = $this->pay($this->budi, $this->invoiceBudi, $this->tenantA, $this->ownerA);
        $theirs = "/p/{$this->tokenSiti}";

        $this->get("$theirs/tagihan/{$this->invoiceBudi->id}")->assertNotFound();
        $this->get("$theirs/kuitansi/{$paymentA->id}")->assertNotFound();
        $json = json_encode($this->props($theirs));
        $this->assertStringContainsString('Siti Tenant B', $json);
        $this->assertStringNotContainsString('Budi Penghuni', $json);
        $this->assertStringNotContainsString($this->invoiceBudi->number, $json);
    }

    // Unggah bukti

    public function test_unggahan_bukti_membuat_pending_dan_tidak_mengubah_tagihan(): void
    {
        $this->post("/p/{$this->tokenBudi}/tagihan/{$this->invoiceBudi->id}/bukti", [
            'amount' => 1500000, 'paid_on' => '2026-11-09', 'sender_note' => 'Lunas via BCA',
            'file' => UploadedFile::fake()->image('struk.jpg'),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $proof = $this->inTenant($this->tenantA, fn () => PaymentProof::query()->sole());
        $this->assertSame(ProofStatus::Pending, $proof->status);
        $this->assertSame($this->invoiceBudi->id, $proof->invoice_id);
        $this->assertSame($this->budi->id, $proof->resident_id);
        $attachment = $this->inTenant($this->tenantA, fn () => Attachment::query()->sole());
        $this->assertSame(AttachmentCategory::TransferProof, $attachment->category);
        $this->assertSame($proof->id, $attachment->attachable_id);
        $this->assertSame($this->budi->id, $attachment->uploaded_by_resident_id);
        $this->assertNull($attachment->uploaded_by);

        $invoice = $this->inTenant($this->tenantA, fn () => $this->invoiceBudi->fresh());
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertSame(0, $invoice->paid_total);
        $this->assertSame(0, $this->inTenant($this->tenantA, fn () => Payment::query()->count()));

        $this->get("/p/{$this->tokenBudi}")->assertInertia(fn (AssertableInertia $page) => $page->where('invoices.0.has_pending_proof', true));
    }

    public function test_validasi_ukuran_tipe_dan_jumlah_bukti(): void
    {
        $url = "/p/{$this->tokenBudi}/tagihan/{$this->invoiceBudi->id}/bukti";

        $this->post($url, ['amount' => 1000, 'file' => UploadedFile::fake()->create('besar.jpg', 6000, 'image/jpeg')])
            ->assertSessionHasErrors(['file' => 'Ukuran berkas maksimal 5 MB.']);
        $this->post($url, ['amount' => 1000, 'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])
            ->assertSessionHasErrors(['file' => 'Berkas harus berformat JPG, PNG, atau PDF.']);
        $this->post($url, ['file' => UploadedFile::fake()->image('a.jpg')])->assertSessionHasErrors(['amount' => 'Jumlah transfer wajib diisi.']);
        $this->post($url, ['amount' => 9999999, 'file' => UploadedFile::fake()->image('a.jpg')])->assertSessionHasErrors('amount'); // melebihi sisa

        $this->assertSame(0, $this->inTenant($this->tenantA, fn () => PaymentProof::query()->count()));
    }

    public function test_bukti_menunggu_dibatasi_tiga_per_tagihan(): void
    {
        $url = "/p/{$this->tokenBudi}/tagihan/{$this->invoiceBudi->id}/bukti";

        for ($i = 0; $i < 3; $i++) {
            $this->post($url, ['amount' => 1000, 'file' => UploadedFile::fake()->image("b$i.jpg")])->assertSessionHasNoErrors();
        }
        $this->post($url, ['amount' => 1000, 'file' => UploadedFile::fake()->image('b4.jpg')])->assertSessionHasErrors('file');

        $this->assertSame(3, $this->inTenant($this->tenantA, fn () => PaymentProof::query()->count()));
    }

    public function test_unggahan_dibatasi_laju_per_penghuni(): void
    {
        $url = "/p/{$this->tokenBudi}/komplain";

        for ($i = 0; $i < 10; $i++) {
            $this->post($url, [])->assertSessionHasErrors();
        }
        $this->post($url, [])->assertStatus(429);
        // Penghuni lain tidak ikut terblokir.
        $this->post("/p/{$this->tokenCitra}/komplain", [])->assertSessionHasErrors();
    }

    // Komplain

    public function test_penghuni_mengajukan_dan_memantau_komplain(): void
    {
        $this->post("/p/{$this->tokenBudi}/komplain", [
            'category' => 'water', 'title' => 'Kran bocor', 'description' => 'Kran kamar mandi bocor.',
            'photos' => [UploadedFile::fake()->image('kran.jpg')],
            // Kolom ini tidak boleh dipercaya dari klien.
            'room_id' => 999999, 'resident_id' => $this->citra->id, 'status' => 'done', 'assigned_to' => $this->ownerA->id,
        ])->assertSessionHasNoErrors();

        $complaint = $this->inTenant($this->tenantA, fn () => Complaint::query()->sole());
        $this->assertSame($this->budi->id, $complaint->resident_id);
        $this->assertSame($this->roomA->id, $complaint->room_id);
        $this->assertSame(ComplaintStatus::New, $complaint->status);
        $this->assertNull($complaint->assigned_to);
        $photo = $this->inTenant($this->tenantA, fn () => Attachment::query()->sole());
        $this->assertSame(AttachmentCategory::ComplaintPhoto, $photo->category);
        $this->assertSame($this->budi->id, $photo->uploaded_by_resident_id);
        $this->assertNull($photo->uploaded_by);

        $this->post("/p/{$this->tokenBudi}/komplain/{$complaint->id}/komentar", ['body' => 'Mohon cepat ya'])->assertSessionHasNoErrors();
        $this->inTenant($this->tenantA, fn () => ComplaintComment::factory()->create(['complaint_id' => $complaint->id, 'user_id' => $this->ownerA->id, 'body' => 'Teknisi datang besok']));

        $this->get("/p/{$this->tokenBudi}/komplain/{$complaint->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Portal/ComplaintShow')
                ->where('complaint.status_label', 'Baru')
                ->has('complaint.comments', 2)
                ->where('complaint.comments.0.author_name', 'Anda')
                ->where('complaint.comments.1.author_name', 'Pengelola'));

        $this->inTenant($this->tenantA, fn () => $complaint->update(['status' => ComplaintStatus::Done, 'closed_at' => now()]));
        $this->post("/p/{$this->tokenBudi}/komplain/{$complaint->id}/komentar", ['body' => 'Masih?'])->assertSessionHasErrors('body');
    }

    public function test_penghuni_tanpa_kontrak_aktif_tidak_bisa_mengajukan_komplain(): void
    {
        $this->inTenant($this->tenantA, fn () => Lease::query()->update(['status' => 'ended', 'active_room_id' => null]));

        $this->post("/p/{$this->tokenBudi}/komplain", ['category' => 'water', 'title' => 'X', 'description' => 'Y'])->assertSessionHasErrors('title');
        $this->assertSame(0, $this->inTenant($this->tenantA, fn () => Complaint::query()->count()));
    }

    // Kuitansi, rekening, kontrak

    public function test_kuitansi_riwayat_dan_pdf_hanya_pembayaran_aktif_milik_sendiri(): void
    {
        $payment = $this->pay($this->budi, $this->invoiceBudi, $this->tenantA, $this->ownerA);

        $this->get("/p/{$this->tokenBudi}/riwayat")->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Portal/History')->has('payments', 1)->has('invoices', 1)->where('payments.0.receipt_number', $payment->receipt_number));
        $this->get("/p/{$this->tokenBudi}/kuitansi/{$payment->id}")->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Portal/Receipt')->where('receipt.number', $payment->receipt_number)->missing('receipt.recorded_by_name'));
        $pdf = $this->get("/p/{$this->tokenBudi}/kuitansi/{$payment->id}/pdf");
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());

        $this->inTenant($this->tenantA, fn () => $payment->forceFill(['status' => 'voided'])->save());
        $this->get("/p/{$this->tokenBudi}/kuitansi/{$payment->id}")->assertNotFound();
    }

    public function test_halaman_tagihan_menampilkan_rekening_dan_kontrak_ringkas(): void
    {
        $this->get("/p/{$this->tokenBudi}/tagihan/{$this->invoiceBudi->id}")->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Portal/Invoice')->where('can_pay', true)
            ->has('accounts', 1)->where('accounts.0.account_number', '8820491022')->missing('accounts.0.is_default'));

        $this->get("/p/{$this->tokenBudi}/kontrak")->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Portal/Contract')->where('lease.rent_amount', 1500000)->has('lease.room_number')->missing('lease.notes'));
    }

    public function test_props_portal_tidak_memuat_kolom_terlarang(): void
    {
        $this->pay($this->citra, $this->invoiceCitra, $this->tenantA, $this->ownerA);
        $payment = $this->pay($this->budi, $this->invoiceBudi, $this->tenantA, $this->ownerA);
        $urls = ['', '/riwayat', '/kontrak', '/komplain', "/kuitansi/{$payment->id}", "/tagihan/{$this->invoiceBudi->id}"];

        foreach ($urls as $path) {
            $json = json_encode($this->props("/p/{$this->tokenBudi}$path"));

            foreach (['tenant_id', 'token_hash', $this->tokenBudi, 'Catatan internal pemilik', 'created_by', 'recorded_by', 'assigned_to', 'Citra Penghuni', 'password', $this->citra->phone] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $json, "Props $path memuat $forbidden");
            }
        }
    }

    // Sesi dan tenant ditangguhkan

    public function test_portal_tidak_memberi_akses_ke_rute_pemilik_atau_admin(): void
    {
        $this->get("/p/{$this->tokenBudi}")->assertOk();

        $this->assertGuest();
        $this->get('/app')->assertRedirect('/masuk');
        $this->get('/app/tagihan')->assertRedirect('/masuk');
        $this->assertContains($this->get('/admin')->getStatusCode(), [302, 403, 404]);
    }

    public function test_tenant_ditangguhkan_hanya_boleh_membaca(): void
    {
        $this->tenantA->forceFill(['status' => 'suspended', 'suspended_at' => now()])->save();

        $this->get("/p/{$this->tokenBudi}")->assertOk();
        $this->post("/p/{$this->tokenBudi}/tagihan/{$this->invoiceBudi->id}/bukti", ['amount' => 1000, 'file' => UploadedFile::fake()->image('a.jpg')])->assertForbidden();
        $this->assertSame(0, $this->inTenant($this->tenantA, fn () => PaymentProof::query()->count()));
    }

    // Performa

    public function test_beranda_tidak_n_plus_satu(): void
    {
        $count = function (string $token): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get("/p/$token")->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        // Permintaan pertama menulis last_used_at; dipanaskan dulu supaya yang diukur hanya biaya per tagihan.
        $this->get("/p/{$this->tokenBudi}")->assertOk();
        $withOne = $count($this->tokenBudi);

        $this->inTenant($this->tenantA, function () {
            $lease = Lease::query()->where('primary_resident_id', $this->budi->id)->sole();
            foreach (range(1, 5) as $i) {
                Invoice::factory()->create([
                    'property_id' => $this->propertyA->id, 'lease_id' => $lease->id, 'resident_id' => $this->budi->id,
                    'period' => '2026-0'.$i, 'due_date' => now()->addDays($i)->toDateString(),
                ]);
            }
        });
        $withSix = $count($this->tokenBudi);

        $this->assertSame($withOne, $withSix, 'Jumlah query tidak boleh bertambah mengikuti jumlah tagihan.');
        $this->assertLessThanOrEqual(15, $withSix);
    }

    // Sisi pemilik: kirim tautan lewat WhatsApp

    public function test_pemilik_mengirim_tautan_portal_dan_tautan_di_pesan_bisa_dibuka_lalu_disunting_setelah_terkirim(): void
    {
        Queue::fake();

        $this->actingAs($this->ownerA)->post("/app/penghuni/{$this->budi->id}/portal")->assertSessionHas('success');

        $log = $this->inTenant($this->tenantA, fn () => MessageLog::query()->sole());
        $this->assertSame(MessageStatus::Queued, $log->status);
        $this->assertSame('resident', $log->subject_type);
        preg_match('#/p/([a-f0-9]{64})#', $log->body, $match);
        $this->assertNotEmpty($match, 'Pesan harus memuat tautan portal.');
        $this->get("/p/{$match[1]}")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('shell.resident.name', 'Budi Penghuni'));

        // Setelah terkirim, token di log disunting: DB tidak menyimpan kunci akses.
        Queue::assertPushed(SendWhatsAppMessage::class);
        (new SendWhatsAppMessage($this->tenantA->id, $log->id))->handle(app(DeliverWhatsAppMessage::class));
        $sent = $this->inTenant($this->tenantA, fn () => $log->fresh());
        $this->assertSame(MessageStatus::Sent, $sent->status);
        $this->assertStringNotContainsString($match[1], $sent->body);
        $this->assertStringContainsString('/p/[tautan-rahasia]', $sent->body);
        $this->get("/p/{$match[1]}")->assertOk(); // tautan di tangan penghuni tetap berlaku
    }

    public function test_pengingat_dan_notifikasi_membawa_tautan_portal_penghuni_yang_benar(): void
    {
        Queue::fake();
        $this->inTenant($this->tenantA, fn () => app(QueueWhatsAppMessage::class)->handle(
            MessageTemplateKey::ReminderDueToday, $this->budi, ['jumlah' => 'Rp 1', 'jatuh_tempo' => '9 Nov', 'link' => 'https://jahat.example/p/palsu'],
        ));

        $body = $this->inTenant($this->tenantA, fn () => MessageLog::query()->sole()->body);
        $this->assertStringNotContainsString('jahat.example', $body);
        preg_match('#/p/([a-f0-9]{64})#', $body, $match);
        $this->get("/p/{$match[1]}")->assertInertia(fn (AssertableInertia $page) => $page->where('shell.resident.name', 'Budi Penghuni'));
    }

    public function test_penghuni_opt_out_tidak_dikirimi_tautan_dan_tidak_ada_token_dibuat(): void
    {
        Queue::fake();
        $this->inTenant($this->tenantA, fn () => $this->budi->update(['whatsapp_opt_out_at' => now()]));
        $before = $this->inTenant($this->tenantA, fn () => PortalToken::query()->count());

        $this->actingAs($this->ownerA)->post("/app/penghuni/{$this->budi->id}/portal")->assertSessionHasErrors('portal');

        $this->assertSame($before, $this->inTenant($this->tenantA, fn () => PortalToken::query()->count()));
    }

    public function test_halaman_penghuni_pemilik_tidak_memuat_token_dan_menampilkan_jumlah_tautan(): void
    {
        $this->actingAs($this->ownerA)->get("/app/penghuni/{$this->budi->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('portal.active_links', 1)->where('portal.last_used_at', null));

        $json = json_encode($this->actingAs($this->ownerA)->get("/app/penghuni/{$this->budi->id}")->viewData('page')['props']);
        $this->assertStringNotContainsString($this->tokenBudi, $json);
        $this->assertStringNotContainsString('token_hash', $json);
        $this->assertStringNotContainsString(PortalToken::hash($this->tokenBudi), $json);
    }

    public function test_pemilik_tenant_a_tidak_bisa_mengirim_atau_mencabut_tautan_penghuni_tenant_b(): void
    {
        Queue::fake();

        $this->actingAs($this->ownerA)->post("/app/penghuni/{$this->siti->id}/portal")->assertNotFound();
        $this->actingAs($this->ownerA)->post("/app/penghuni/{$this->siti->id}/portal/cabut")->assertNotFound();

        $this->get("/p/{$this->tokenSiti}")->assertOk();
        $this->assertSame(0, $this->inTenant($this->tenantB, fn () => MessageLog::query()->count()));
    }

    public function test_pencabutan_memakai_action_dan_tercatat_di_audit(): void
    {
        $count = $this->inTenant($this->tenantA, fn () => app(RevokePortalLinks::class)->handle($this->budi));

        $this->assertSame(1, $count);
        $this->assertSame(0, $this->inTenant($this->tenantA, fn () => PortalToken::query()->usable()->where('resident_id', $this->budi->id)->count()));
        $this->assertSame(0, $this->inTenant($this->tenantA, fn () => app(RevokePortalLinks::class)->handle($this->budi)));
    }
}
