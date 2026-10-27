<?php

namespace Tests\Feature\Residents;

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

class AnonymizeResidentTest extends TestCase
{
    use CreatesTenantFixtures, RefreshDatabase;

    public function test_owner_anonymizes_a_resident_and_everything_personal_is_gone(): void
    {
        Storage::fake('private');
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post(route('owner.residents.store'), [
            'name' => 'Rizky Dwi Saputra', 'phone' => '081234567890', 'email' => 'rizky@example.com',
            'emergency_name' => 'Ibu Rizky', 'emergency_phone' => '081311112222', 'notes' => 'Alergi kacang',
            'consent' => '1',
        ]);
        $resident = $this->asTenant($tenant, fn () => Resident::firstOrFail());

        $this->actingAs($owner)->put(route('owner.residents.update', $resident), [
            'name' => 'Rizky D. Saputra', 'phone' => '081234567890', 'email' => 'rizky@example.com',
        ]);

        $this->actingAs($owner)->post(route('owner.residents.attachments.store', $resident), [
            'category' => 'ktp', 'file' => UploadedFile::fake()->image('ktp.jpg'),
        ]);
        $this->actingAs($owner)->post(route('owner.residents.attachments.store', $resident), [
            'category' => 'photo', 'file' => UploadedFile::fake()->image('foto.png'),
        ]);
        $paths = $this->asTenant($tenant, fn () => Attachment::pluck('path')->all());
        $this->assertCount(2, $paths);

        $this->actingAs($owner)->delete(route('owner.residents.destroy', $resident))
            ->assertRedirect(route('owner.residents.index'));

        // Baris tetap ada (soft delete) tetapi tanpa identitas.
        $row = $this->asTenant($tenant, fn () => Resident::withTrashed()->findOrFail($resident->id));
        $this->assertTrue($row->trashed());
        $this->assertSame('Penghuni Dihapus', $row->name);
        $this->assertSame('anonim-'.$resident->id, $row->phone);
        $this->assertNull($row->email);
        $this->assertNull($row->emergency_name);
        $this->assertNull($row->emergency_phone);
        $this->assertNull($row->notes);
        $this->assertFalse($row->is_active);
        $this->assertNotNull($row->whatsapp_opt_out_at);

        // Lampiran dan berkasnya hilang.
        $this->assertSame(0, $this->asTenant($tenant, fn () => Attachment::count()));
        foreach ($paths as $path) {
            Storage::disk('private')->assertMissing($path);
        }

        // Jejak audit mencatat penghapusan, tanpa menyimpan data pribadi sama sekali.
        $logs = $this->withoutTenant(fn () => AuditLog::where('auditable_type', 'resident')->where('auditable_id', $resident->id)->get());
        $this->assertTrue($logs->contains('event.value', 'deleted'));
        $payload = $logs->map(fn ($log) => json_encode([$log->old_values, $log->new_values]))->implode(' ');
        foreach (['Rizky', 'rizky@example.com', '+62812', '+6281234567890', 'Alergi', 'Ibu Rizky'] as $secret) {
            $this->assertStringNotContainsString($secret, $payload);
        }

        $this->actingAs($owner)->get(route('owner.residents.index'))->assertDontSee('Rizky');
    }

    public function test_only_owner_can_anonymize_and_other_tenants_get_404(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $staff = User::factory()->for($tenant)->staff(['documents.view'])->create();
        $resident = Resident::factory()->for($tenant)->create(['name' => 'Masih Ada']);

        $this->actingAs($staff)->delete(route('owner.residents.destroy', $resident))->assertForbidden();

        [, $otherOwner] = $this->tenantWithOwner();
        $this->actingAs($otherOwner)->delete(route('owner.residents.destroy', $resident))->assertNotFound();

        $row = $this->asTenant($tenant, fn () => Resident::findOrFail($resident->id));
        $this->assertSame('Masih Ada', $row->name);
        $this->assertFalse($row->trashed());
    }
}
