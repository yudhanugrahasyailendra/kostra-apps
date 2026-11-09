<?php

namespace Tests\Feature\Complaints;

use App\Actions\AddComplaintComment;
use App\Actions\AssignComplaint;
use App\Actions\ChangeComplaintStatus;
use App\Actions\CreateComplaint;
use App\Enums\AttachmentCategory;
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
use App\Policies\ComplaintPolicy;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

class ComplaintDomainTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    private User $ownerB;

    private Property $propertyA;

    private Room $roomA;

    private Resident $residentA;

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
        $this->ownerB = User::factory()->forTenant($this->tenantB)->owner()->create();

        [$this->propertyA, $this->roomA, $this->residentA] = $this->seedTenant($this->tenantA);
        [, $this->roomB, $this->residentB] = $this->seedTenant($this->tenantB);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array{Property, Room, Resident} */
    private function seedTenant(Tenant $tenant): array
    {
        return TenantContext::instance()->runAs($tenant, function () {
            $property = Property::factory()->create();

            return [
                $property,
                Room::factory()->create(['property_id' => $property->id]),
                Resident::factory()->create(),
            ];
        });
    }

    private function inA(callable $callback): mixed
    {
        return TenantContext::instance()->runAs($this->tenantA, $callback);
    }

    private function inB(callable $callback): mixed
    {
        return TenantContext::instance()->runAs($this->tenantB, $callback);
    }

    private function complaintA(array $attributes = []): Complaint
    {
        return $this->inA(fn () => Complaint::factory()->create($attributes + [
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA->id,
            'resident_id' => $this->residentA->id,
        ]));
    }

    // Pembuatan

    public function test_staf_membuat_tiket_atas_nama_penghuni_dengan_foto(): void
    {
        $complaint = $this->inA(fn () => app(CreateComplaint::class)->handle([
            'room_id' => $this->roomA->id,
            'resident_id' => $this->residentA->id,
            'category' => 'water',
            'title' => '  Air mati  ',
            'description' => 'Air tidak mengalir sejak pagi.',
        ], $this->ownerA, [UploadedFile::fake()->image('keran.jpg')]));

        $this->assertSame(ComplaintStatus::New, $complaint->status);
        $this->assertSame('Air mati', $complaint->title);
        $this->assertSame($this->propertyA->id, $complaint->property_id);
        $this->assertNull($complaint->closed_at);

        $photo = $this->inA(fn () => Attachment::query()->sole());
        $this->assertSame(AttachmentCategory::ComplaintPhoto, $photo->category);
        $this->assertSame($complaint->id, $photo->attachable_id);
    }

    public function test_tiket_menolak_kamar_atau_penghuni_tenant_lain(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->inA(fn () => app(CreateComplaint::class)->handle([
            'room_id' => $this->roomB->id,
            'resident_id' => $this->residentA->id,
            'category' => 'water',
            'title' => 'X',
            'description' => 'Y',
        ], $this->ownerA));
    }

    // Transisi status

    public function test_transisi_sah_berjalan_dan_mengirim_event_tiap_perubahan(): void
    {
        Event::fake([ComplaintStatusChanged::class]);
        $complaint = $this->complaintA();

        $this->inA(function () use ($complaint) {
            $change = app(ChangeComplaintStatus::class);
            $change->handle($complaint, ComplaintStatus::InProgress);
            $this->assertNull($complaint->fresh()->closed_at);

            $change->handle($complaint, ComplaintStatus::Done);
        });

        Event::assertDispatchedTimes(ComplaintStatusChanged::class, 2);
        Event::assertDispatched(ComplaintStatusChanged::class, fn ($e) => $e->from === ComplaintStatus::New
            && $e->to === ComplaintStatus::InProgress && $e->complaint->is($complaint));
        Event::assertDispatched(ComplaintStatusChanged::class, fn ($e) => $e->from === ComplaintStatus::InProgress
            && $e->to === ComplaintStatus::Done);
    }

    public function test_selesai_dan_ditolak_mengisi_resolved_at_dan_closed_at(): void
    {
        foreach ([ComplaintStatus::Done, ComplaintStatus::Rejected] as $final) {
            $complaint = $this->complaintA();

            $this->inA(fn () => app(ChangeComplaintStatus::class)->handle($complaint, $final));

            $fresh = $this->inA(fn () => $complaint->fresh());
            $this->assertSame($final, $fresh->status);
            $this->assertNotNull($fresh->closed_at);
            $this->assertNotNull($fresh->resolved_at);
        }
    }

    public function test_transisi_tidak_sah_ditolak_dan_tidak_mengirim_event(): void
    {
        Event::fake([ComplaintStatusChanged::class]);
        $closed = $this->complaintA(['status' => ComplaintStatus::Done, 'closed_at' => now(), 'resolved_at' => now()]);
        $inProgress = $this->complaintA(['status' => ComplaintStatus::InProgress]);

        foreach ([[$closed, ComplaintStatus::InProgress], [$closed, ComplaintStatus::New], [$inProgress, ComplaintStatus::New], [$inProgress, ComplaintStatus::InProgress]] as [$complaint, $to]) {
            try {
                $this->inA(fn () => app(ChangeComplaintStatus::class)->handle($complaint, $to));
                $this->fail('Transisi tidak sah seharusnya ditolak.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('status', $e->errors());
            }
        }

        Event::assertNotDispatched(ComplaintStatusChanged::class);
        $this->assertSame(ComplaintStatus::Done, $this->inA(fn () => $closed->fresh())->status);
    }

    // Scope openLongerThan

    public function test_scope_open_longer_than_menangkap_tiket_terbuka_lebih_dari_tiga_hari(): void
    {
        $old = $this->complaintA(['created_at' => now()->subDays(4)]);
        $oldInProgress = $this->complaintA(['status' => ComplaintStatus::InProgress, 'created_at' => now()->subDays(10)]);
        $this->complaintA(['created_at' => now()->subDays(2)]);
        $this->complaintA(['status' => ComplaintStatus::Done, 'closed_at' => now(), 'created_at' => now()->subDays(20)]);
        $this->complaintA(['status' => ComplaintStatus::Rejected, 'closed_at' => now(), 'created_at' => now()->subDays(20)]);

        $ids = $this->inA(fn () => Complaint::query()->openLongerThan(3)->pluck('id')->sort()->values()->all());

        $this->assertSame([$old->id, $oldInProgress->id], $ids);
        $this->assertTrue($old->isStale());
    }

    // Penugasan

    public function test_penugasan_ke_pengguna_tenant_lain_ditolak(): void
    {
        $complaint = $this->complaintA();

        try {
            $this->inA(fn () => app(AssignComplaint::class)->handle($complaint, $this->ownerB));
            $this->fail('Penugasan lintas tenant seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('assigned_to', $e->errors());
        }

        $this->assertNull($this->inA(fn () => $complaint->fresh())->assigned_to);
    }

    public function test_penugasan_ke_staf_tenant_sama_dan_pelepasan_berjalan(): void
    {
        $complaint = $this->complaintA();
        $staff = User::factory()->forTenant($this->tenantA)->staff()->create();

        $this->inA(fn () => app(AssignComplaint::class)->handle($complaint, $staff));
        $this->assertSame($staff->id, $this->inA(fn () => $complaint->fresh())->assigned_to);

        $this->inA(fn () => app(AssignComplaint::class)->handle($complaint, null));
        $this->assertNull($this->inA(fn () => $complaint->fresh())->assigned_to);
    }

    public function test_penugasan_ke_staf_nonaktif_ditolak(): void
    {
        $inactive = User::factory()->forTenant($this->tenantA)->staff()->create(['is_active' => false]);

        $this->expectException(ValidationException::class);
        $this->inA(fn () => app(AssignComplaint::class)->handle($this->complaintA(), $inactive));
    }

    public function test_tiket_tertutup_tidak_bisa_ditugaskan_ulang(): void
    {
        $closed = $this->complaintA(['status' => ComplaintStatus::Done, 'closed_at' => now(), 'resolved_at' => now()]);

        $this->expectException(ValidationException::class);
        $this->inA(fn () => app(AssignComplaint::class)->handle($closed, $this->ownerA));
    }

    // Komentar

    public function test_komentar_dari_pengguna_atau_penghuni_tersimpan_urut_waktu(): void
    {
        $complaint = $this->complaintA();

        $this->inA(function () use ($complaint) {
            app(AddComplaintComment::class)->handle($complaint, 'Teknisi datang besok.', $this->ownerA);
            Carbon::setTestNow('2026-11-09 10:05:00');
            app(AddComplaintComment::class)->handle($complaint, 'Baik, terima kasih.', null, $this->residentA);
        });

        $comments = $this->inA(fn () => $complaint->comments()->get());
        $this->assertSame(['Teknisi datang besok.', 'Baik, terima kasih.'], $comments->pluck('body')->all());
        $this->assertSame($this->ownerA->id, $comments[0]->user_id);
        $this->assertNull($comments[0]->resident_id);
        $this->assertSame($this->residentA->id, $comments[1]->resident_id);
        $this->assertNull($comments[1]->user_id);
    }

    public function test_komentar_tanpa_penulis_atau_dengan_dua_penulis_ditolak(): void
    {
        $complaint = $this->complaintA();

        $this->inA(function () use ($complaint) {
            $action = app(AddComplaintComment::class);

            foreach ([[null, null], [$this->ownerA, $this->residentA]] as [$user, $resident]) {
                try {
                    $action->handle($complaint, 'Halo', $user, $resident);
                    $this->fail('Komentar tanpa tepat satu penulis seharusnya ditolak.');
                } catch (InvalidArgumentException) {
                    // diharapkan
                }
            }

            $this->assertSame(0, ComplaintComment::query()->count());
        });
    }

    public function test_model_komentar_menolak_penulis_ganda_atau_kosong_langsung_ke_basis_data(): void
    {
        $complaint = $this->complaintA();

        $this->inA(function () use ($complaint) {
            foreach ([[null, null], [$this->ownerA->id, $this->residentA->id]] as [$userId, $residentId]) {
                try {
                    ComplaintComment::create([
                        'complaint_id' => $complaint->id, 'user_id' => $userId, 'resident_id' => $residentId, 'body' => 'x',
                    ]);
                    $this->fail('Model seharusnya menolak.');
                } catch (InvalidArgumentException $e) {
                    $this->assertStringContainsString('tepat satu penulis', $e->getMessage());
                }
            }

            $this->assertSame(0, ComplaintComment::query()->count());
        });
    }

    public function test_komentar_dari_penulis_tenant_lain_ditolak_dan_komentar_kosong_ditolak(): void
    {
        $complaint = $this->complaintA();

        $this->inA(function () use ($complaint) {
            $action = app(AddComplaintComment::class);

            try {
                $action->handle($complaint, 'Halo', $this->ownerB);
                $this->fail('Penulis tenant lain seharusnya ditolak.');
            } catch (InvalidArgumentException) {
                // diharapkan
            }

            $this->expectException(ValidationException::class);
            $action->handle($complaint, '   ', $this->ownerA);
        });
    }

    // Tidak ada penghapusan

    public function test_tidak_ada_route_penghapusan_tiket(): void
    {
        $deleteRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'komplain') && in_array('DELETE', $route->methods(), true));

        $this->assertCount(0, $deleteRoutes);
        $this->assertFalse(method_exists(ComplaintPolicy::class, 'delete'));
        $this->assertSame([], glob(app_path('Actions/{Delete,Destroy,Remove}Complaint*.php'), GLOB_BRACE));
    }

    // Isolasi tenant

    public function test_isolasi_tenant_untuk_tiket_dan_komentar(): void
    {
        $complaint = $this->complaintA();
        $this->inA(fn () => app(AddComplaintComment::class)->handle($complaint, 'Rahasia A', $this->ownerA));

        $this->inB(function () use ($complaint) {
            $this->assertSame(0, Complaint::query()->count());
            $this->assertSame(0, ComplaintComment::query()->count());
            $this->assertNull(Complaint::query()->find($complaint->id));
        });

        $this->inA(function () {
            $this->assertSame(1, Complaint::query()->count());
            $this->assertSame(1, ComplaintComment::query()->count());
        });
    }
}
