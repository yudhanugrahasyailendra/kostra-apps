<?php

namespace Tests\Feature\Residents;

use App\Actions\Attachments\ReadAttachment;
use App\Actions\Attachments\StoreAttachment;
use App\Enums\AttachmentCategory;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

class AttachmentSecurityTest extends TestCase
{
    use CreatesTenantFixtures, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Resident $resident;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        [$this->tenant, $this->owner] = $this->tenantWithOwner();
        $this->resident = Resident::factory()->for($this->tenant)->create();
    }

    private function image(string $name = 'KTP Budi Santoso.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 200, 120);
    }

    private function upload(User $user, string $category, ?UploadedFile $file = null, ?Resident $resident = null)
    {
        return $this->actingAs($user)->post(
            route('owner.residents.attachments.store', $resident ?? $this->resident),
            ['category' => $category, 'file' => $file ?? $this->image()],
        );
    }

    private function attachment(?string $category = null): Attachment
    {
        return $this->asTenant($this->tenant, fn () => Attachment::when($category, fn ($q) => $q->where('category', $category))->latest('id')->firstOrFail());
    }

    public function test_ktp_is_encrypted_on_disk_but_readable_through_the_app(): void
    {
        $file = $this->image();
        $original = file_get_contents($file->getRealPath());

        $this->upload($this->owner, 'ktp', $file)->assertSessionHasNoErrors();

        $attachment = $this->attachment('ktp');
        $onDisk = Storage::disk('private')->get($attachment->path);

        $this->assertTrue($attachment->is_sensitive);
        $this->assertNotSame($original, $onDisk);
        $this->assertStringNotContainsString("\xFF\xD8\xFF", substr($onDisk, 0, 10));
        $this->assertSame($original, $this->asTenant($this->tenant, fn () => app(ReadAttachment::class)($attachment)));
        $this->assertStringNotContainsString('Budi', $attachment->path);
        $this->assertSame('image/jpeg', $attachment->mime);
        $this->assertSame($this->tenant->id, $attachment->tenant_id);
    }

    public function test_non_sensitive_files_are_stored_plain_on_the_private_disk(): void
    {
        $file = $this->image('foto.png');
        $original = file_get_contents($file->getRealPath());

        $this->upload($this->owner, 'photo', $file)->assertSessionHasNoErrors();

        $attachment = $this->attachment('photo');
        $this->assertFalse($attachment->is_sensitive);
        $this->assertSame($original, Storage::disk('private')->get($attachment->path));
    }

    public function test_ktp_and_photo_are_single_per_resident_and_replace_the_old_file(): void
    {
        $this->upload($this->owner, 'ktp');
        $first = $this->attachment('ktp');

        $this->upload($this->owner, 'ktp');
        $second = $this->attachment('ktp');

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(1, $this->asTenant($this->tenant, fn () => Attachment::where('category', 'ktp')->count()));
        Storage::disk('private')->assertMissing($first->path);
        Storage::disk('private')->assertExists($second->path);

        $this->upload($this->owner, 'document');
        $this->upload($this->owner, 'document');
        $this->assertSame(2, $this->asTenant($this->tenant, fn () => Attachment::where('category', 'document')->count()));
    }

    public function test_oversized_wrong_type_and_disguised_files_are_rejected(): void
    {
        $this->upload($this->owner, 'ktp', UploadedFile::fake()->create('besar.jpg', 6000, 'image/jpeg'))->assertSessionHasErrors('file');
        $this->upload($this->owner, 'ktp', UploadedFile::fake()->create('dokumen.docx', 10))->assertSessionHasErrors('file');
        $this->upload($this->owner, 'ktp', UploadedFile::fake()->create('skrip.php', 1))->assertSessionHasErrors('file');
        $this->upload($this->owner, 'rahasia', $this->image())->assertSessionHasErrors('category');

        // PHP yang diberi nama .jpg: isi berkas, bukan namanya, yang diperiksa.
        $path = tempnam(sys_get_temp_dir(), 'kostra');
        file_put_contents($path, '<?php echo "pwned";');
        $disguised = new UploadedFile($path, 'foto.jpg', 'image/jpeg', null, true);

        $this->upload($this->owner, 'ktp', $disguised)->assertSessionHasErrors('file');
        @unlink($path);

        $this->assertSame(0, $this->asTenant($this->tenant, fn () => Attachment::count()));
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_store_action_rejects_disguised_content_even_without_form_validation(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kostra');
        file_put_contents($path, '<?php echo "pwned";');
        $disguised = new UploadedFile($path, 'foto.jpg', 'image/jpeg', null, true);

        try {
            $this->asTenant($this->tenant, fn () => app(StoreAttachment::class)($this->resident, $disguised, AttachmentCategory::Photo));
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
        } finally {
            @unlink($path);
        }
    }

    public function test_signed_link_serves_the_original_bytes_with_safe_headers_and_audits_sensitive_access(): void
    {
        $file = $this->image();
        $original = file_get_contents($file->getRealPath());
        $this->upload($this->owner, 'ktp', $file);
        $attachment = $this->attachment('ktp');

        $response = $this->actingAs($this->owner)->get($attachment->temporaryUrl());

        $response->assertOk();
        $this->assertSame($original, $response->getContent());
        $response->assertHeader('Content-Type', 'image/jpeg');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('Budi', $response->headers->get('Content-Disposition'));

        $logs = $this->withoutTenant(fn () => AuditLog::where('event', 'viewed_sensitive')->where('auditable_type', 'attachment')->get());
        $this->assertCount(1, $logs);
        $this->assertSame($attachment->id, $logs->first()->auditable_id);
        $this->assertSame($this->owner->id, $logs->first()->user_id);
        $this->assertSame($this->tenant->id, $logs->first()->tenant_id);

        $this->actingAs($this->owner)->get($attachment->temporaryUrl());
        $this->assertSame(2, $this->withoutTenant(fn () => AuditLog::where('event', 'viewed_sensitive')->count()));
    }

    public function test_uploaded_original_name_never_reaches_the_audit_log(): void
    {
        $this->upload($this->owner, 'ktp', $this->image('KTP Budi Santoso.jpg'));

        $payload = $this->withoutTenant(fn () => AuditLog::where('auditable_type', 'attachment')->get()
            ->map(fn ($log) => json_encode([$log->old_values, $log->new_values]))->implode(' '));

        $this->assertStringNotContainsString('Budi', $payload);
        $this->assertStringNotContainsString('.jpg', $payload);
    }

    public function test_non_sensitive_view_is_not_audited_as_sensitive(): void
    {
        $this->upload($this->owner, 'photo');
        $attachment = $this->attachment('photo');

        $this->actingAs($this->owner)->get($attachment->temporaryUrl())->assertOk();

        $this->assertSame(0, $this->withoutTenant(fn () => AuditLog::where('event', 'viewed_sensitive')->count()));
    }

    public function test_unsigned_tampered_and_expired_links_are_refused(): void
    {
        $this->upload($this->owner, 'ktp');
        $attachment = $this->attachment('ktp');
        $this->upload($this->owner, 'photo');
        $other = $this->attachment('photo');

        $this->actingAs($this->owner)->get(route('owner.attachments.show', $attachment))->assertForbidden();

        // Tanda tangan milik lampiran lain tidak berlaku untuk lampiran ini.
        $url = $attachment->temporaryUrl();
        $tampered = str_replace('/attachments/'.$attachment->id.'?', '/attachments/'.$other->id.'?', $url);
        $this->actingAs($this->owner)->get($tampered)->assertForbidden();

        $this->travel(Attachment::URL_TTL_MINUTES + 1)->minutes();
        $this->actingAs($this->owner)->get($url)->assertForbidden();
    }

    public function test_a_leaked_signed_link_is_useless_without_a_session(): void
    {
        $this->upload($this->owner, 'ktp');
        $url = $this->attachment('ktp')->temporaryUrl();

        auth()->logout();
        $this->flushSession();

        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_staff_needs_documents_permission_for_sensitive_files(): void
    {
        $this->upload($this->owner, 'ktp');
        $this->upload($this->owner, 'photo');
        $ktp = $this->attachment('ktp');
        $photo = $this->attachment('photo');

        $plain = User::factory()->for($this->tenant)->staff()->create();
        $allowed = User::factory()->for($this->tenant)->staff(['documents.view'])->create();

        $this->actingAs($plain)->get($ktp->temporaryUrl())->assertForbidden();
        $this->actingAs($plain)->get($photo->temporaryUrl())->assertOk();
        $this->actingAs($allowed)->get($ktp->temporaryUrl())->assertOk();

        $this->upload($plain, 'ktp')->assertForbidden();
        $this->upload($plain, 'photo')->assertSessionHasNoErrors();
        $this->upload($allowed, 'ktp')->assertSessionHasNoErrors();

        // Unggahan KTP baru menggantikan yang lama, jadi ambil KTP yang berlaku sekarang.
        $ktp = $this->attachment('ktp');
        $this->actingAs($plain)->delete(route('owner.attachments.destroy', $ktp))->assertForbidden();

        $this->actingAs($plain)->get(route('owner.residents.show', $this->resident))
            ->assertOk()->assertSee('Perlu izin dokumen sensitif');
    }

    public function test_deleting_an_attachment_removes_the_row_and_the_file(): void
    {
        $this->upload($this->owner, 'document');
        $attachment = $this->attachment('document');

        $this->actingAs($this->owner)->delete(route('owner.attachments.destroy', $attachment))
            ->assertRedirect(route('owner.residents.show', $this->resident));

        $this->assertSame(0, $this->asTenant($this->tenant, fn () => Attachment::count()));
        Storage::disk('private')->assertMissing($attachment->path);
    }

    public function test_financial_attachments_cannot_be_deleted(): void
    {
        $attachment = $this->asTenant($this->tenant, fn () => $this->resident->attachments()->create([
            'category' => AttachmentCategory::TransferProof, 'disk' => 'private', 'path' => 'x/y.jpg',
            'original_name' => 'bukti.jpg', 'mime' => 'image/jpeg', 'size' => 10, 'is_sensitive' => false,
        ]));

        $this->actingAs($this->owner)->delete(route('owner.attachments.destroy', $attachment))
            ->assertSessionHasErrors('general');

        $this->assertSame(1, $this->asTenant($this->tenant, fn () => Attachment::count()));
    }

    public function test_property_photo_is_owner_only_and_replaced_on_new_upload(): void
    {
        $property = $this->propertyFor($this->tenant);
        $staff = User::factory()->for($this->tenant)->staff()->create();

        $this->actingAs($staff)->post(route('owner.properties.photo.store', $property), ['file' => $this->image('a.jpg')])->assertForbidden();

        $this->actingAs($this->owner)->post(route('owner.properties.photo.store', $property), ['file' => $this->image('a.jpg')])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('owner.properties.photo.store', $property), ['file' => $this->image('b.png')])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->asTenant($this->tenant, fn () => Attachment::where('attachable_type', 'property')->count()));

        $this->actingAs($this->owner)->get(route('owner.properties.edit', $property))->assertOk()->assertSee('Ganti foto');
        $this->actingAs($this->owner)->get(route('owner.properties.index'))->assertOk();

        $photo = $this->attachment('photo');
        $this->actingAs($staff)->delete(route('owner.attachments.destroy', $photo))->assertForbidden();
        $this->actingAs($this->owner)->delete(route('owner.attachments.destroy', $photo))
            ->assertRedirect(route('owner.properties.edit', $property));
    }

    public function test_tenant_isolation_for_attachments(): void
    {
        $this->upload($this->owner, 'ktp');
        $attachment = $this->attachment('ktp');

        [, $otherOwner] = $this->tenantWithOwner();

        $this->asTenant(Tenant::findOrFail($otherOwner->tenant_id), function () use ($attachment) {
            $this->assertNull(Attachment::find($attachment->id));
        });

        // Tanda tangan sah tetapi milik tenant lain: tetap 404.
        $this->actingAs($otherOwner)->get($attachment->temporaryUrl())->assertNotFound();
        $this->actingAs($otherOwner)->delete(route('owner.attachments.destroy', $attachment))->assertNotFound();
        $this->upload($otherOwner, 'photo', null, $this->resident)->assertNotFound();

        Storage::disk('private')->assertExists($attachment->path);
        $this->assertInstanceOf(Property::class, $this->propertyFor($this->tenant));
        $this->assertSame(0, app(TenantContext::class)->id() ?? 0);
    }
}
