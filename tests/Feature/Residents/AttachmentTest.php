<?php

namespace Tests\Feature\Residents;

use App\Actions\StoreAttachment;
use App\Enums\AttachmentCategory;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Resident;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantMismatchException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    /** PNG 1x1 asli, supaya deteksi tipe berdasarkan isi berkas berjalan. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    private User $staffA;

    private User $staffWithPermission;

    private User $ownerB;

    private Resident $resident;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('attachments');

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->ownerA = User::factory()->forTenant($this->tenantA)->owner()->create();
        $this->staffA = User::factory()->forTenant($this->tenantA)->staff()->create();
        $this->staffWithPermission = User::factory()->forTenant($this->tenantA)->staff(['files.sensitive'])->create();
        $this->ownerB = User::factory()->forTenant($this->tenantB)->owner()->create();
        $this->resident = $this->inTenant($this->tenantA, fn () => Resident::factory()->create());
    }

    private function inTenant(Tenant $tenant, callable $callback): mixed
    {
        return TenantContext::instance()->runAs($tenant, $callback);
    }

    private function png(string $name = 'ktp.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::PNG));
    }

    private function upload(User $user, UploadedFile $file, string $category = 'ktp', ?Resident $resident = null)
    {
        return $this->actingAs($user)->post(route('owner.residents.attachments.store', $resident ?? $this->resident), [
            'category' => $category,
            'file' => $file,
        ]);
    }

    private function storeKtp(): Attachment
    {
        $this->upload($this->ownerA, $this->png())->assertSessionHasNoErrors();

        return $this->inTenant($this->tenantA, fn () => Attachment::query()->firstOrFail());
    }

    private function signedUrl(Attachment $attachment, int $minutes = 5): string
    {
        // Tautan relatif (tanpa host) supaya tanda tangan tidak bergantung pada APP_URL atau proxy.
        return URL::temporarySignedRoute('owner.attachments.show', now()->addMinutes($minutes), ['attachment' => $attachment->id], absolute: false);
    }

    public function test_foto_ktp_tersimpan_terenkripsi_dan_terbaca_benar_lewat_aplikasi(): void
    {
        $attachment = $this->storeKtp();
        $original = base64_decode(self::PNG);

        $raw = Storage::disk('attachments')->get($attachment->path);
        $this->assertNotSame($original, $raw);
        $this->assertStringNotContainsString("\x89PNG", $raw);
        $this->assertStringNotContainsString('IHDR', $raw);

        $this->assertTrue($attachment->is_sensitive);
        $this->assertSame('image/png', $attachment->mime);
        $this->assertSame(strlen($original), $attachment->size);
        $this->assertSame('resident', $attachment->attachable_type);
        $this->assertSame($this->resident->id, $attachment->attachable_id);
        $this->assertSame($this->ownerA->id, $attachment->uploaded_by);
        $this->assertSame('attachments', $attachment->disk);

        $response = $this->actingAs($this->ownerA)->get($this->signedUrl($attachment));
        $response->assertOk();
        $this->assertSame($original, $response->getContent());
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_setiap_akses_foto_ktp_tercatat_di_audit_log(): void
    {
        $attachment = $this->storeKtp();

        $this->actingAs($this->staffWithPermission)->get($this->signedUrl($attachment))->assertOk();

        $log = $this->inTenant($this->tenantA, fn () => AuditLog::query()->where('event', 'attachment.viewed')->firstOrFail());
        $this->assertSame('attachment', $log->auditable_type);
        $this->assertSame($attachment->id, $log->auditable_id);
        $this->assertSame($this->staffWithPermission->id, $log->user_id);
        $this->assertStringNotContainsString($attachment->path, (string) json_encode($log->new_values));
    }

    public function test_nilai_audit_lampiran_tidak_memuat_jalur_berkas(): void
    {
        $attachment = $this->storeKtp();

        $created = $this->inTenant($this->tenantA, fn () => AuditLog::query()->where('event', 'created')->where('auditable_type', 'attachment')->firstOrFail());

        $this->assertStringNotContainsString($attachment->path, (string) json_encode($created->new_values));
        $this->assertStringNotContainsString('ktp.png', (string) json_encode($created->new_values));
    }

    public function test_foto_biasa_tidak_dienkripsi_dan_aksesnya_tidak_masuk_audit(): void
    {
        $this->upload($this->staffA, $this->png('foto.png'), 'photo')->assertSessionHasNoErrors();
        $attachment = $this->inTenant($this->tenantA, fn () => Attachment::query()->firstOrFail());

        $this->assertFalse($attachment->is_sensitive);
        $this->assertSame(base64_decode(self::PNG), Storage::disk('attachments')->get($attachment->path));

        $this->actingAs($this->staffA)->get($this->signedUrl($attachment))->assertOk();
        $this->inTenant($this->tenantA, fn () => $this->assertSame(0, AuditLog::query()->where('event', 'attachment.viewed')->count()));
    }

    public function test_unggahan_di_atas_5_mb_atau_bertipe_lain_ditolak(): void
    {
        $this->upload($this->ownerA, UploadedFile::fake()->create('besar.pdf', 5121, 'application/pdf'), 'photo')
            ->assertSessionHasErrors(['file' => 'Ukuran berkas maksimal 5 MB.']);

        $this->upload($this->ownerA, UploadedFile::fake()->create('lain.gif', 10, 'image/gif'), 'photo')
            ->assertSessionHasErrors(['file' => 'Berkas harus berformat JPG, PNG, atau PDF.']);

        // Teks yang hanya diberi ekstensi .jpg harus ditolak karena isinya bukan gambar.
        // UploadedFile::fake() menebak tipe dari nama berkas, jadi pakai berkas sungguhan agar tipe dibaca dari isinya.
        $fake = tempnam(sys_get_temp_dir(), 'palsu');
        file_put_contents($fake, '<?php echo 1;');
        $this->upload($this->ownerA, new UploadedFile($fake, 'palsu.jpg', 'image/jpeg', null, true), 'photo')
            ->assertSessionHasErrors(['file' => 'Berkas harus berformat JPG, PNG, atau PDF.']);
        @unlink($fake);

        $this->upload($this->ownerA, UploadedFile::fake()->create('skrip.php', 1, 'application/x-php'), 'photo')
            ->assertSessionHasErrors('file');

        $this->inTenant($this->tenantA, fn () => $this->assertSame(0, Attachment::query()->count()));
        $this->assertSame([], Storage::disk('attachments')->allFiles());
    }

    public function test_pdf_hingga_5_mb_diterima_dan_kategori_tidak_dikenal_ditolak(): void
    {
        $this->upload($this->ownerA, UploadedFile::fake()->create('dokumen.pdf', 5120, 'application/pdf'), 'photo')
            ->assertSessionHasNoErrors();

        $this->upload($this->ownerA, $this->png(), 'transfer_proof')->assertSessionHasErrors('category');
        $this->upload($this->ownerA, $this->png(), 'sembarang')->assertSessionHasErrors('category');
    }

    public function test_staf_tanpa_izin_tidak_bisa_mengunggah_atau_membuka_foto_ktp(): void
    {
        $this->upload($this->staffA, $this->png())->assertForbidden();
        $this->inTenant($this->tenantA, fn () => $this->assertSame(0, Attachment::query()->count()));

        $attachment = $this->storeKtp();

        $this->actingAs($this->staffA)->get($this->signedUrl($attachment))->assertForbidden();
        $this->actingAs($this->staffA)->delete(route('owner.attachments.destroy', $attachment))->assertForbidden();
        $this->inTenant($this->tenantA, fn () => $this->assertSame(0, AuditLog::query()->where('event', 'attachment.viewed')->count()));
    }

    public function test_staf_dengan_izin_bisa_mengunggah_dan_membuka_foto_ktp(): void
    {
        $this->upload($this->staffWithPermission, $this->png())->assertSessionHasNoErrors();
        $attachment = $this->inTenant($this->tenantA, fn () => Attachment::query()->firstOrFail());

        $this->actingAs($this->staffWithPermission)->get($this->signedUrl($attachment))->assertOk();
    }

    public function test_pengguna_tenant_lain_tidak_bisa_membuka_lampiran_meski_tautan_bertanda_tangan_sah(): void
    {
        $attachment = $this->storeKtp();

        $this->actingAs($this->ownerB)->get($this->signedUrl($attachment))->assertNotFound();
        $this->actingAs($this->ownerB)->delete(route('owner.attachments.destroy', $attachment))->assertNotFound();
        $this->inTenant($this->tenantA, fn () => $this->assertSame(1, Attachment::query()->count()));
    }

    public function test_tidak_bisa_melampirkan_ke_penghuni_tenant_lain(): void
    {
        $residentB = $this->inTenant($this->tenantB, fn () => Resident::factory()->create());

        $this->upload($this->ownerA, $this->png(), 'photo', $residentB)->assertNotFound();

        $this->inTenant($this->tenantB, fn () => $this->assertSame(0, Attachment::query()->count()));
        $this->assertSame([], Storage::disk('attachments')->allFiles());
    }

    public function test_tautan_kedaluwarsa_atau_tanpa_tanda_tangan_tidak_bisa_dibuka(): void
    {
        $attachment = $this->storeKtp();
        $url = $this->signedUrl($attachment, 5);

        $this->actingAs($this->ownerA)->get($url)->assertOk();

        $this->actingAs($this->ownerA)->get(route('owner.attachments.show', $attachment))->assertForbidden();
        $this->actingAs($this->ownerA)->get($url.'x')->assertForbidden();

        $this->travel(6)->minutes();
        $this->actingAs($this->ownerA)->get($url)->assertForbidden();
    }

    public function test_tamu_tidak_bisa_membuka_lampiran(): void
    {
        $attachment = $this->storeKtp();
        $url = $this->signedUrl($attachment);

        // storeKtp() memakai actingAs; lepas agar permintaan berikut benar-benar tanpa login.
        $this->app['auth']->forgetGuards();

        $this->get($url)->assertRedirect();
    }

    public function test_hapus_lampiran_menghapus_berkas_dan_baris(): void
    {
        $attachment = $this->storeKtp();
        $path = $attachment->path;

        $this->actingAs($this->ownerA)->delete(route('owner.attachments.destroy', $attachment))->assertRedirect();

        $this->inTenant($this->tenantA, fn () => $this->assertSame(0, Attachment::query()->count()));
        Storage::disk('attachments')->assertMissing($path);
    }

    public function test_menghapus_penghuni_ikut_menghapus_lampiran_dan_berkasnya(): void
    {
        $attachment = $this->storeKtp();
        $path = $attachment->path;

        $this->actingAs($this->ownerA)->delete(route('owner.residents.destroy', $this->resident))->assertRedirect();

        $this->inTenant($this->tenantA, fn () => $this->assertSame(0, Attachment::query()->count()));
        Storage::disk('attachments')->assertMissing($path);
    }

    public function test_action_menolak_induk_dari_tenant_lain(): void
    {
        $residentB = $this->inTenant($this->tenantB, fn () => Resident::factory()->create());

        $this->expectException(TenantMismatchException::class);

        $this->inTenant($this->tenantA, fn () => app(StoreAttachment::class)
            ->handle($residentB, $this->png(), AttachmentCategory::Photo, $this->ownerA));
    }

    public function test_halaman_detail_menampilkan_tautan_bertanda_tangan_tanpa_jalur_berkas(): void
    {
        $this->storeKtp();
        $this->upload($this->ownerA, $this->png('foto.png'), 'photo');

        $this->actingAs($this->ownerA)->get(route('owner.residents.show', $this->resident))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Owner/Residents/Show')
                ->where('resident.has_ktp', true)
                ->has('attachments', 2)
                ->where('attachments.0.url', fn (string $url) => str_contains($url, 'signature='))
                ->missing('attachments.0.path')
                ->missing('attachments.0.disk')
                ->missing('attachments.0.tenant_id')
                ->missing('resident.tenant_id')
                ->where('can.upload_sensitive', true));
    }

    public function test_staf_tanpa_izin_hanya_melihat_bahwa_ktp_ada_bukan_berkasnya(): void
    {
        $this->storeKtp();
        $this->upload($this->ownerA, $this->png('foto.png'), 'photo');

        $this->actingAs($this->staffA)->get(route('owner.residents.show', $this->resident))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('resident.has_ktp', true)
                ->has('attachments', 1)
                ->where('attachments.0.category', 'photo')
                ->where('can.upload_sensitive', false));
    }
}
