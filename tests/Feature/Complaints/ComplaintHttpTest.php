<?php

namespace Tests\Feature\Complaints;

use App\Enums\ComplaintStatus;
use App\Events\ComplaintStatusChanged;
use App\Models\Attachment;
use App\Models\Complaint;
use App\Models\ComplaintComment;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ComplaintHttpTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    private User $staffA;

    private User $ownerB;

    private Property $propertyA;

    private Room $roomA;

    private Room $roomA2;

    private Resident $residentA;

    private Property $propertyB;

    private Room $roomB;

    private Resident $residentB;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-09 10:00:00');
        Storage::fake('attachments');

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->ownerA = User::factory()->forTenant($this->tenantA)->owner()->create();
        $this->staffA = User::factory()->forTenant($this->tenantA)->staff()->create(['name' => 'Penjaga A']);
        $this->ownerB = User::factory()->forTenant($this->tenantB)->owner()->create();

        TenantContext::instance()->runAs($this->tenantA, function () {
            $this->propertyA = Property::factory()->create();
            $this->roomA = Room::factory()->create(['property_id' => $this->propertyA->id, 'number' => '101']);
            $this->roomA2 = Room::factory()->create(['property_id' => $this->propertyA->id, 'number' => '102']);
            $this->residentA = Resident::factory()->create(['name' => 'Budi Penghuni']);
        });
        TenantContext::instance()->runAs($this->tenantB, function () {
            $this->propertyB = Property::factory()->create();
            $this->roomB = Room::factory()->create(['property_id' => $this->propertyB->id]);
            $this->residentB = Resident::factory()->create();
        });
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

    private function complaintIn(Tenant $tenant, array $attributes = []): Complaint
    {
        $isA = $tenant->is($this->tenantA);

        return $this->inTenant($tenant, fn () => Complaint::factory()->create($attributes + [
            'property_id' => $isA ? $this->propertyA->id : $this->propertyB->id,
            'room_id' => $isA ? $this->roomA->id : $this->roomB->id,
            'resident_id' => $isA ? $this->residentA->id : $this->residentB->id,
        ]));
    }

    /** @return array<string, mixed> */
    private function body(array $overrides = []): array
    {
        return $overrides + [
            'room_id' => $this->roomA->id,
            'resident_id' => $this->residentA->id,
            'category' => 'electricity',
            'title' => 'Lampu kamar mati',
            'description' => 'Lampu utama tidak menyala.',
        ];
    }

    // Alur lengkap di UI: buat, tugaskan, komentari, tutup

    public function test_tiket_dibuat_ditugaskan_dikomentari_dan_ditutup(): void
    {
        Event::fake([ComplaintStatusChanged::class]);

        $response = $this->actingAs($this->ownerA)->post('/app/komplain', $this->body([
            'photos' => [UploadedFile::fake()->image('lampu.jpg')],
        ]));

        $complaint = $this->inTenant($this->tenantA, fn () => Complaint::query()->sole());
        $response->assertRedirect("/app/komplain/{$complaint->id}")->assertSessionHas('success');
        $this->assertSame(ComplaintStatus::New, $complaint->status);
        $this->assertSame($this->propertyA->id, $complaint->property_id);

        $this->actingAs($this->ownerA)->post("/app/komplain/{$complaint->id}/penanggung-jawab", ['assigned_to' => $this->staffA->id])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->ownerA)->post("/app/komplain/{$complaint->id}/komentar", ['body' => 'Sedang dicek teknisi.'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->ownerA)->post("/app/komplain/{$complaint->id}/status", ['status' => 'in_progress'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->staffA)->post("/app/komplain/{$complaint->id}/status", ['status' => 'done'])
            ->assertSessionHasNoErrors();

        $closed = $this->inTenant($this->tenantA, fn () => $complaint->fresh());
        $this->assertSame(ComplaintStatus::Done, $closed->status);
        $this->assertSame($this->staffA->id, $closed->assigned_to);
        $this->assertNotNull($closed->closed_at);
        Event::assertDispatchedTimes(ComplaintStatusChanged::class, 2);

        $this->actingAs($this->ownerA)->get("/app/komplain/{$complaint->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Owner/Complaints/Show')
                ->where('complaint.status', 'done')
                ->where('complaint.assignee_name', 'Penjaga A')
                ->where('complaint.resident_name', 'Budi Penghuni')
                ->has('complaint.comments', 1)
                ->where('complaint.comments.0.body', 'Sedang dicek teknisi.')
                ->where('complaint.comments.0.author_type', 'staff')
                ->has('complaint.photos', 1)
                ->where('complaint.allowed_statuses', [])
            );
    }

    public function test_perubahan_status_tidak_sah_menampilkan_galat_validasi(): void
    {
        $complaint = $this->complaintIn($this->tenantA, ['status' => ComplaintStatus::Done, 'closed_at' => now(), 'resolved_at' => now()]);

        $this->actingAs($this->ownerA)->post("/app/komplain/{$complaint->id}/status", ['status' => 'new'])
            ->assertSessionHasErrors('status');
    }

    // Validasi

    public function test_validasi_pembuatan_tiket_berbahasa_indonesia(): void
    {
        $this->actingAs($this->ownerA)->post('/app/komplain', [])
            ->assertSessionHasErrors(['room_id', 'resident_id', 'category', 'title', 'description']);

        $this->actingAs($this->ownerA)->post('/app/komplain', $this->body(['photos' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]]))
            ->assertSessionHasErrors(['photos.0' => 'Foto harus berformat JPG atau PNG.']);
    }

    // Daftar dan filter

    public function test_daftar_menyaring_status_kamar_kategori_dan_penanggung_jawab(): void
    {
        $this->complaintIn($this->tenantA, ['title' => 'Baru 101', 'room_id' => $this->roomA->id, 'category' => 'water']);
        $this->complaintIn($this->tenantA, ['title' => 'Proses 102', 'room_id' => $this->roomA2->id, 'status' => ComplaintStatus::InProgress, 'assigned_to' => $this->staffA->id]);
        $this->complaintIn($this->tenantA, ['title' => 'Selesai 101', 'status' => ComplaintStatus::Done, 'closed_at' => now(), 'resolved_at' => now()]);

        $titles = fn (string $query) => $this->actingAs($this->ownerA)->get('/app/komplain'.$query)->viewData('page')['props']['complaints'];
        $names = fn (string $query) => collect($titles($query))->pluck('title')->sort()->values()->all();

        $this->assertSame(['Baru 101', 'Proses 102'], $names(''));
        $this->assertSame(['Selesai 101'], $names('?status=done'));
        $this->assertSame(['Baru 101', 'Proses 102', 'Selesai 101'], $names('?status=all'));
        $this->assertSame(['Proses 102'], $names("?room_id={$this->roomA2->id}&status=all"));
        $this->assertSame(['Baru 101'], $names('?category=water&status=all'));
        $this->assertSame(['Proses 102'], $names("?assigned_to={$this->staffA->id}&status=all"));
        $this->assertSame(['Baru 101', 'Selesai 101'], $names('?assigned_to=none&status=all'));
    }

    public function test_tiket_terbuka_lebih_dari_tiga_hari_ditandai_di_daftar(): void
    {
        $this->complaintIn($this->tenantA, ['title' => 'Lama', 'created_at' => now()->subDays(5)]);
        $this->complaintIn($this->tenantA, ['title' => 'Baru', 'created_at' => now()->subDay()]);
        $this->complaintIn($this->tenantA, ['title' => 'Lama tapi selesai', 'created_at' => now()->subDays(9), 'status' => ComplaintStatus::Done, 'closed_at' => now()]);

        $this->actingAs($this->ownerA)->get('/app/komplain?status=all')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stale_count', 1)
                ->where('complaints', fn ($rows) => collect($rows)->pluck('is_stale', 'title')->all() === ['Lama' => true, 'Baru' => false, 'Lama tapi selesai' => false])
            );

        $this->actingAs($this->ownerA)->get('/app/komplain?status=all&stale=1')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('complaints', 1)->where('complaints.0.title', 'Lama'));
    }

    // Props aman

    public function test_props_tidak_memuat_kolom_terlarang(): void
    {
        $complaint = $this->complaintIn($this->tenantA);
        $this->inTenant($this->tenantA, fn () => ComplaintComment::factory()->create(['complaint_id' => $complaint->id, 'user_id' => $this->ownerA->id]));

        foreach (['/app/komplain', "/app/komplain/{$complaint->id}"] as $url) {
            $json = json_encode($this->actingAs($this->ownerA)->get($url)->viewData('page')['props']);

            foreach (['tenant_id', 'token_hash', 'password', 'remember_token', '"path"', '"disk"', 'ktp'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $json, "Props {$url} memuat {$forbidden}");
            }
            // Telepon penghuni tidak dipakai layar komplain.
            $this->assertStringNotContainsString($this->residentA->phone, $json);
        }
    }

    // Otorisasi

    public function test_tamu_ditolak_dan_staf_tanpa_izin_khusus_tetap_boleh_menangani_tiket(): void
    {
        $this->get('/app/komplain')->assertRedirect('/masuk');

        $this->actingAs($this->staffA)->get('/app/komplain')->assertOk();
    }

    public function test_tidak_ada_route_hapus_tiket(): void
    {
        $complaint = $this->complaintIn($this->tenantA);

        $this->actingAs($this->ownerA)->delete("/app/komplain/{$complaint->id}")->assertStatus(405);
        $this->assertSame(1, $this->inTenant($this->tenantA, fn () => Complaint::query()->count()));
    }

    // Isolasi tenant lewat UI dan URL

    public function test_pengguna_tenant_a_tidak_bisa_membaca_atau_mengubah_tiket_tenant_b(): void
    {
        $theirs = $this->complaintIn($this->tenantB, ['title' => 'Rahasia B']);

        $this->actingAs($this->ownerA)->get("/app/komplain/{$theirs->id}")->assertNotFound();
        $this->actingAs($this->ownerA)->post("/app/komplain/{$theirs->id}/status", ['status' => 'done'])->assertNotFound();
        $this->actingAs($this->ownerA)->post("/app/komplain/{$theirs->id}/komentar", ['body' => 'Halo'])->assertNotFound();
        $this->actingAs($this->ownerA)->post("/app/komplain/{$theirs->id}/penanggung-jawab", ['assigned_to' => $this->staffA->id])->assertNotFound();

        $fresh = $this->inTenant($this->tenantB, fn () => $theirs->fresh());
        $this->assertSame(ComplaintStatus::New, $fresh->status);
        $this->assertSame(0, $this->inTenant($this->tenantB, fn () => ComplaintComment::query()->count()));

        $json = json_encode($this->actingAs($this->ownerA)->get('/app/komplain?status=all')->viewData('page')['props']);
        $this->assertStringNotContainsString('Rahasia B', $json);
    }

    public function test_tiket_tidak_bisa_dibuat_dengan_kamar_atau_penghuni_tenant_lain(): void
    {
        $this->actingAs($this->ownerA)->post('/app/komplain', $this->body(['room_id' => $this->roomB->id]))
            ->assertSessionHasErrors('room_id');
        $this->actingAs($this->ownerA)->post('/app/komplain', $this->body(['resident_id' => $this->residentB->id]))
            ->assertSessionHasErrors('resident_id');

        $this->assertSame(0, Complaint::query()->withoutGlobalScopes()->count()); // alasan: tes memeriksa lintas tenant
    }

    public function test_penugasan_ke_pengguna_tenant_lain_ditolak_lewat_ui(): void
    {
        $complaint = $this->complaintIn($this->tenantA);

        $this->actingAs($this->ownerA)->post("/app/komplain/{$complaint->id}/penanggung-jawab", ['assigned_to' => $this->ownerB->id])
            ->assertSessionHasErrors('assigned_to');

        $this->assertNull($this->inTenant($this->tenantA, fn () => $complaint->fresh())->assigned_to);
    }

    public function test_foto_komplain_tenant_lain_tidak_bisa_dibuka(): void
    {
        $theirs = $this->complaintIn($this->tenantB);
        $attachment = $this->inTenant($this->tenantB, fn () => Attachment::factory()->create([
            'attachable_type' => 'complaint',
            'attachable_id' => $theirs->id,
            'category' => 'complaint_photo',
        ]));

        $url = URL::temporarySignedRoute('owner.attachments.show', now()->addMinutes(5), ['attachment' => $attachment->id], absolute: false);

        $this->actingAs($this->ownerA)->get($url)->assertNotFound();
    }
}
