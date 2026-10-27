<?php

namespace Tests\Feature\Complaints;

use App\Actions\Complaints\AddComplaintComment;
use App\Actions\Complaints\AssignComplaint;
use App\Actions\Complaints\ChangeComplaintStatus;
use App\Enums\ComplaintStatus;
use App\Events\ComplaintCreated;
use App\Events\ComplaintStatusChanged;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintComment;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\Resident;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\ComplaintPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\FinanceFixtures;
use Tests\TestCase;

class ComplaintTest extends TestCase
{
    use FinanceFixtures, RefreshDatabase;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->setUpFinance();
        $this->lease = $this->leaseFor();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'resident_id' => $this->lease->primary_resident_id,
            'category' => 'electricity',
            'title' => 'Lampu kamar mati',
            'description' => 'Lampu utama tidak menyala sejak semalam.',
        ], $overrides);
    }

    private function complaint(array $attributes = [], ?Lease $lease = null, ?Tenant $tenant = null): Complaint
    {
        $tenant ??= $this->tenant;
        $lease ??= $this->lease;

        return $this->asTenant($tenant, fn () => Complaint::factory()->for($tenant)
            ->for($lease->property, 'property')->for($lease->room, 'room')->for($lease->primaryResident, 'resident')
            ->create($attributes));
    }

    private function fresh(Complaint $complaint): Complaint
    {
        return $this->asTenant($this->tenant, fn () => Complaint::findOrFail($complaint->getKey()));
    }

    private function change(Complaint $complaint, ComplaintStatus $to): Complaint
    {
        return $this->asTenant($this->tenant, fn () => app(ChangeComplaintStatus::class)($complaint, $to));
    }

    // ---- Membuat tiket ----

    public function test_staff_creates_a_ticket_for_a_resident_with_a_photo(): void
    {
        Event::fake([ComplaintCreated::class]);

        $this->actingAs($this->owner)->post(route('owner.complaints.store'), $this->payload(['photo' => UploadedFile::fake()->image('lampu.jpg')]))
            ->assertSessionHasNoErrors();

        $complaint = $this->asTenant($this->tenant, fn () => Complaint::sole());
        $this->assertSame(
            [$this->lease->room_id, $this->lease->property_id, $this->lease->primary_resident_id, 'new', 'electricity', null, null],
            [$complaint->room_id, $complaint->property_id, $complaint->resident_id, $complaint->status->value, $complaint->category->value, $complaint->assigned_to, $complaint->closed_at],
        );

        $photo = $this->asTenant($this->tenant, fn () => Attachment::sole());
        $this->assertSame(['complaint', 'complaint_photo'], [$photo->attachable_type, $photo->category->value]);
        Storage::disk('private')->assertExists($photo->path);
        Event::assertDispatched(ComplaintCreated::class, fn ($e) => $e->complaintId === $complaint->id && $e->tenantId === $this->tenant->id);
    }

    public function test_the_room_always_comes_from_the_residents_active_lease(): void
    {
        $other = $this->leaseFor();

        $this->actingAs($this->owner)->post(route('owner.complaints.store'), $this->payload(['resident_id' => $other->primary_resident_id, 'room_id' => $this->lease->room_id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($other->room_id, $this->asTenant($this->tenant, fn () => Complaint::sole())->room_id);
    }

    public function test_creation_validation(): void
    {
        $post = fn (array $overrides) => $this->actingAs($this->owner)->post(route('owner.complaints.store'), $this->payload($overrides));

        $post(['title' => 'ab'])->assertSessionHasErrors('title');
        $post(['title' => str_repeat('x', 151)])->assertSessionHasErrors('title');
        $post(['description' => ''])->assertSessionHasErrors('description');
        $post(['category' => 'banjir'])->assertSessionHasErrors('category');
        $post(['resident_id' => 999999])->assertSessionHasErrors('resident_id');
        $post(['photo' => UploadedFile::fake()->create('x.exe', 10)])->assertSessionHasErrors('photo');
        $post(['photo' => UploadedFile::fake()->create('besar.png', 6000, 'image/png')])->assertSessionHasErrors('photo');

        $this->assertSame(0, $this->asTenant($this->tenant, fn () => Complaint::count()));
    }

    public function test_a_resident_without_an_active_lease_cannot_get_a_ticket(): void
    {
        $this->asTenant($this->tenant, fn () => $this->lease->update(['status' => 'ended', 'active_room_id' => null]));

        $this->actingAs($this->owner)->post(route('owner.complaints.store'), $this->payload())->assertSessionHasErrors('resident_id');

        $this->assertSame(0, $this->asTenant($this->tenant, fn () => Complaint::count()));
    }

    public function test_a_resident_of_another_tenant_is_rejected(): void
    {
        [$other] = $this->tenantWithOwner('business');
        $foreign = $this->leaseFor([], $other);

        $this->actingAs($this->owner)->post(route('owner.complaints.store'), $this->payload(['resident_id' => $foreign->primary_resident_id]))
            ->assertSessionHasErrors('resident_id');

        $this->assertSame(0, $this->asTenant($other, fn () => Complaint::count()));
    }

    // ---- Status ----

    public function test_valid_transitions_set_the_close_timestamps_and_fire_the_event_each_time(): void
    {
        Event::fake([ComplaintStatusChanged::class]);
        $complaint = $this->complaint();

        $this->change($complaint, ComplaintStatus::InProgress);
        $mid = $this->fresh($complaint);
        $this->assertSame([ComplaintStatus::InProgress, null, null], [$mid->status, $mid->resolved_at, $mid->closed_at]);

        $this->change($complaint, ComplaintStatus::Done);
        $done = $this->fresh($complaint);
        $this->assertSame(ComplaintStatus::Done, $done->status);
        $this->assertNotNull($done->resolved_at);
        $this->assertNotNull($done->closed_at);

        Event::assertDispatchedTimes(ComplaintStatusChanged::class, 2);
        Event::assertDispatched(ComplaintStatusChanged::class, fn ($e) => [$e->from, $e->to, $e->tenantId] === ['new', 'in_progress', $this->tenant->id]);
        Event::assertDispatched(ComplaintStatusChanged::class, fn ($e) => [$e->from, $e->to] === ['in_progress', 'done']);
    }

    public function test_rejecting_straight_from_new_closes_the_ticket_too(): void
    {
        $complaint = $this->complaint();

        $this->change($complaint, ComplaintStatus::Rejected);

        $fresh = $this->fresh($complaint);
        $this->assertSame(ComplaintStatus::Rejected, $fresh->status);
        $this->assertNotNull($fresh->closed_at);
    }

    public function test_invalid_transitions_are_refused_without_events_or_changes(): void
    {
        Event::fake([ComplaintStatusChanged::class]);
        $new = $this->complaint();
        $closed = $this->complaint();
        $this->change($closed, ComplaintStatus::Done);
        $closedAt = $this->fresh($closed)->closed_at;
        Event::fake([ComplaintStatusChanged::class]);

        foreach ([[$new, ComplaintStatus::New], [$closed, ComplaintStatus::InProgress], [$closed, ComplaintStatus::New], [$closed, ComplaintStatus::Rejected], [$closed, ComplaintStatus::Done]] as [$ticket, $to]) {
            try {
                $this->change($ticket, $to);
                $this->fail("Transisi ke {$to->value} seharusnya ditolak.");
            } catch (ValidationException) {
                // diharapkan
            }
        }

        $this->assertSame(ComplaintStatus::New, $this->fresh($new)->status);
        $this->assertEquals($closedAt, $this->fresh($closed)->closed_at);
        Event::assertNotDispatched(ComplaintStatusChanged::class);
    }

    public function test_status_changes_through_the_ui_and_unknown_values_are_rejected(): void
    {
        $complaint = $this->complaint();

        $this->actingAs($this->owner)->post(route('owner.complaints.status', $complaint), ['status' => 'in_progress'])
            ->assertRedirect(route('owner.complaints.show', $complaint));
        $this->assertSame(ComplaintStatus::InProgress, $this->fresh($complaint)->status);

        $this->actingAs($this->owner)->post(route('owner.complaints.status', $complaint), ['status' => 'archived'])->assertSessionHasErrors('status');
        $this->actingAs($this->owner)->post(route('owner.complaints.status', $complaint), ['status' => 'new'])->assertSessionHasErrors('status');
    }

    // ---- Scope tiket lama ----

    public function test_open_longer_than_uses_a_strict_three_day_boundary(): void
    {
        $now = CarbonImmutable::parse('2026-10-15 03:00:00', 'UTC');
        $exactly = $this->complaint(['created_at' => $now->subDays(3)]);
        $over = $this->complaint(['created_at' => $now->subDays(3)->subSecond()]);
        $fresh = $this->complaint(['created_at' => $now->subDay()]);
        $closedOld = $this->complaint(['created_at' => $now->subDays(10), 'status' => ComplaintStatus::Done]);
        $progressOld = $this->complaint(['created_at' => $now->subDays(8), 'status' => ComplaintStatus::InProgress]);

        $ids = $this->asTenant($this->tenant, fn () => Complaint::query()->openLongerThan(3, $now)->pluck('id')->all());

        $this->assertEqualsCanonicalizing([$over->id, $progressOld->id], $ids);
        $this->assertNotContains($exactly->id, $ids);
        $this->assertNotContains($closedOld->id, $ids);
        $this->assertNotContains($fresh->id, $ids);
    }

    public function test_stale_tickets_are_flagged_with_text_in_the_list(): void
    {
        $this->complaint(['title' => 'Tiket lama', 'created_at' => now()->subDays(5)]);
        $this->complaint(['title' => 'Tiket baru']);

        $html = $this->actingAs($this->owner)->get(route('owner.complaints.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'Terbuka lebih dari 3 hari'));
    }

    // ---- Komentar ----

    public function test_staff_and_resident_comments_keep_their_order(): void
    {
        $complaint = $this->complaint();

        $this->actingAs($this->owner)->post(route('owner.complaints.comments.store', $complaint), ['body' => 'Teknisi datang besok pagi.'])
            ->assertSessionHasNoErrors();
        $this->asTenant($this->tenant, fn () => app(AddComplaintComment::class)($complaint, 'Baik, terima kasih.', null, $complaint->resident));

        $comments = $this->asTenant($this->tenant, fn () => $complaint->comments()->get());
        $this->assertSame(['Teknisi datang besok pagi.', 'Baik, terima kasih.'], $comments->pluck('body')->all());
        $this->assertSame([$this->owner->id, null], $comments->pluck('user_id')->all());
        $this->assertSame([null, $complaint->resident_id], $comments->pluck('resident_id')->all());

        $this->actingAs($this->owner)->get(route('owner.complaints.show', $complaint))
            ->assertSee('Teknisi datang besok pagi.')->assertSee('Baik, terima kasih.')->assertSee('Penghuni');
    }

    public function test_a_comment_needs_exactly_one_author(): void
    {
        $complaint = $this->complaint();
        $resident = $this->asTenant($this->tenant, fn () => $complaint->resident);
        $add = fn (?User $user, ?Resident $resident) => $this->asTenant($this->tenant, fn () => app(AddComplaintComment::class)($complaint, 'Isi', $user, $resident));

        $this->assertThrowsValidation(fn () => $add(null, null));
        $this->assertThrowsValidation(fn () => $add($this->owner, $resident));
        $this->assertSame(0, $this->asTenant($this->tenant, fn () => ComplaintComment::count()));
    }

    public function test_a_resident_cannot_comment_on_someone_elses_ticket(): void
    {
        $complaint = $this->complaint();
        $stranger = $this->asTenant($this->tenant, fn () => Resident::factory()->for($this->tenant)->create());

        $this->assertThrowsValidation(fn () => $this->asTenant($this->tenant, fn () => app(AddComplaintComment::class)($complaint, 'Halo', null, $stranger)));
    }

    public function test_the_database_rejects_comments_without_or_with_two_authors(): void
    {
        $complaint = $this->complaint();
        $insert = fn (array $authors) => $this->asTenant($this->tenant, fn () => ComplaintComment::create(['complaint_id' => $complaint->id, 'body' => 'x'] + $authors));

        foreach ([[], ['user_id' => $this->owner->id, 'resident_id' => $complaint->resident_id]] as $authors) {
            try {
                $insert($authors);
                $this->fail('Basis data seharusnya menolak komentar dengan penulis tidak tunggal.');
            } catch (QueryException) {
                // diharapkan
            }
        }

        $this->assertSame(0, $this->asTenant($this->tenant, fn () => ComplaintComment::count()));
    }

    public function test_comment_rules_and_closed_tickets(): void
    {
        $complaint = $this->complaint();
        $post = fn (string $body) => $this->actingAs($this->owner)->post(route('owner.complaints.comments.store', $complaint), ['body' => $body]);

        $post('')->assertSessionHasErrors('body');
        $post(str_repeat('x', 2001))->assertSessionHasErrors('body');

        $this->change($complaint, ComplaintStatus::Done);
        $post('Terlambat')->assertSessionHasErrors('body');
        $this->assertSame(0, $this->asTenant($this->tenant, fn () => ComplaintComment::count()));
    }

    // ---- Penugasan ----

    public function test_assigning_and_unassigning_a_responsible_user(): void
    {
        $complaint = $this->complaint();
        $staff = $this->staffWith();

        $this->actingAs($this->owner)->post(route('owner.complaints.assign', $complaint), ['assigned_to' => $staff->id])->assertSessionHasNoErrors();
        $this->assertSame($staff->id, $this->fresh($complaint)->assigned_to);

        $this->actingAs($this->owner)->post(route('owner.complaints.assign', $complaint), ['assigned_to' => ''])->assertSessionHasNoErrors();
        $this->assertNull($this->fresh($complaint)->assigned_to);
    }

    public function test_assigning_to_a_user_of_another_tenant_or_an_inactive_user_is_refused(): void
    {
        $complaint = $this->complaint();
        [, $foreignOwner] = $this->tenantWithOwner('business');
        $inactive = User::factory()->for($this->tenant)->staff()->create(['is_active' => false]);

        $this->actingAs($this->owner)->post(route('owner.complaints.assign', $complaint), ['assigned_to' => $foreignOwner->id])->assertSessionHasErrors('assigned_to');
        $this->actingAs($this->owner)->post(route('owner.complaints.assign', $complaint), ['assigned_to' => $inactive->id])->assertSessionHasErrors('assigned_to');
        $this->assertThrowsValidation(fn () => $this->asTenant($this->tenant, fn () => app(AssignComplaint::class)($complaint, $foreignOwner->id)));

        $this->assertNull($this->fresh($complaint)->assigned_to);
    }

    public function test_closed_tickets_cannot_be_reassigned(): void
    {
        $complaint = $this->complaint();
        $this->change($complaint, ComplaintStatus::Done);

        $this->actingAs($this->owner)->post(route('owner.complaints.assign', $complaint), ['assigned_to' => $this->owner->id])->assertSessionHasErrors('assigned_to');
    }

    // ---- Tidak ada penghapusan ----

    public function test_tickets_can_never_be_deleted(): void
    {
        $complaint = $this->complaint();

        $this->actingAs($this->owner)->delete(route('owner.complaints.show', $complaint))->assertStatus(405);

        $names = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => $r->getName())->filter();
        $this->assertFalse($names->contains(fn ($n) => str_contains($n, 'complaints.destroy')));
        $this->assertFalse(class_exists('App\\Actions\\Complaints\\DeleteComplaint'));
        $this->assertFalse(method_exists(ComplaintPolicy::class, 'delete'));
        $this->assertSame(1, $this->asTenant($this->tenant, fn () => Complaint::count()));
    }

    // ---- Daftar dan filter ----

    public function test_the_list_defaults_to_open_tickets_and_filters_by_room_category_and_assignee(): void
    {
        $second = $this->leaseFor();
        $staff = $this->staffWith();
        $this->complaint(['title' => 'Listrik kamar A', 'category' => 'electricity', 'assigned_to' => $staff->id]);
        $this->complaint(['title' => 'Air kamar B', 'category' => 'water'], $second);
        $this->complaint(['title' => 'Selesai lama', 'status' => ComplaintStatus::Done]);

        $get = fn (array $query = []) => $this->actingAs($this->owner)->get(route('owner.complaints.index', $query))->assertOk();

        $get()->assertSee('Listrik kamar A')->assertSee('Air kamar B')->assertDontSee('Selesai lama');
        $get(['status' => 'done'])->assertSee('Selesai lama')->assertDontSee('Listrik kamar A');
        $get(['status' => 'all'])->assertSee('Selesai lama')->assertSee('Air kamar B');
        $get(['room_id' => $second->room_id])->assertSee('Air kamar B')->assertDontSee('Listrik kamar A');
        $get(['category' => 'electricity'])->assertSee('Listrik kamar A')->assertDontSee('Air kamar B');
        $get(['assigned_to' => $staff->id])->assertSee('Listrik kamar A')->assertDontSee('Air kamar B');
        $get(['assigned_to' => 'none'])->assertSee('Air kamar B')->assertDontSee('Listrik kamar A');
        $this->actingAs($staff)->get(route('owner.complaints.index', ['assigned_to' => 'me']))->assertSee('Listrik kamar A')->assertDontSee('Air kamar B');
    }

    // ---- Isolasi tenant ----

    public function test_another_tenants_user_cannot_read_or_change_a_ticket(): void
    {
        $complaint = $this->complaint(['title' => 'Rahasia tenant A']);
        [$other, $stranger] = $this->tenantWithOwner('business');
        $foreign = $this->leaseFor([], $other);
        $this->complaint(['title' => 'Milik tenant B'], $foreign, $other);

        $this->actingAs($stranger)->get(route('owner.complaints.show', $complaint))->assertNotFound();
        $this->actingAs($stranger)->post(route('owner.complaints.status', $complaint), ['status' => 'done'])->assertNotFound();
        $this->actingAs($stranger)->post(route('owner.complaints.assign', $complaint), ['assigned_to' => $stranger->id])->assertNotFound();
        $this->actingAs($stranger)->post(route('owner.complaints.comments.store', $complaint), ['body' => 'Halo'])->assertNotFound();
        $this->actingAs($stranger)->post(route('owner.complaints.photos.store', $complaint), ['file' => UploadedFile::fake()->image('a.jpg')])->assertNotFound();
        $this->actingAs($stranger)->get(route('owner.complaints.index', ['status' => 'all']))->assertOk()
            ->assertSee('Milik tenant B')->assertDontSee('Rahasia tenant A');
        $this->actingAs($stranger)->get(route('owner.complaints.index', ['room_id' => $this->lease->room_id]))->assertSessionHasErrors('room_id');

        $fresh = $this->fresh($complaint);
        $this->assertSame([ComplaintStatus::New, null], [$fresh->status, $fresh->assigned_to]);
        $this->assertSame(0, $this->asTenant($this->tenant, fn () => ComplaintComment::count()));
    }

    public function test_comments_never_cross_tenants(): void
    {
        $complaint = $this->complaint();
        $this->actingAs($this->owner)->post(route('owner.complaints.comments.store', $complaint), ['body' => 'Catatan internal']);
        [$other] = $this->tenantWithOwner('business');

        $this->assertSame(0, $this->asTenant($other, fn () => ComplaintComment::count()));
        $this->assertSame(1, $this->asTenant($this->tenant, fn () => ComplaintComment::count()));
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('owner.complaints.index'))->assertRedirect(route('login'));
        $this->post(route('owner.complaints.store'), $this->payload())->assertRedirect(route('login'));
    }

    public function test_any_staff_member_can_work_tickets(): void
    {
        $staff = $this->staffWith();
        $complaint = $this->complaint();

        $this->actingAs($staff)->get(route('owner.complaints.index'))->assertOk();
        $this->actingAs($staff)->post(route('owner.complaints.status', $complaint), ['status' => 'in_progress'])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('owner.complaints.comments.store', $complaint), ['body' => 'Sedang dicek'])->assertSessionHasNoErrors();
    }

    // ---- Foto, audit, dan hubungan ke pengeluaran ----

    public function test_photos_can_be_added_while_open_but_not_after_closing(): void
    {
        $complaint = $this->complaint();
        $upload = fn () => $this->actingAs($this->owner)->post(route('owner.complaints.photos.store', $complaint), ['file' => UploadedFile::fake()->image('a.png')]);

        $upload()->assertSessionHasNoErrors();
        $this->assertSame(1, $this->asTenant($this->tenant, fn () => Attachment::count()));

        $this->change($complaint, ComplaintStatus::Done);
        $upload()->assertSessionHasErrors('file');
        $this->assertSame(1, $this->asTenant($this->tenant, fn () => Attachment::count()));
    }

    public function test_ticket_photos_cannot_be_deleted_after_closing_or_by_another_tenant(): void
    {
        $complaint = $this->complaint();
        $this->actingAs($this->owner)->post(route('owner.complaints.photos.store', $complaint), ['file' => UploadedFile::fake()->image('a.png')]);
        $photo = $this->asTenant($this->tenant, fn () => Attachment::sole());
        [, $stranger] = $this->tenantWithOwner('business');

        $this->actingAs($stranger)->delete(route('owner.attachments.destroy', $photo))->assertNotFound();

        $this->change($complaint, ComplaintStatus::Done);
        $this->actingAs($this->owner)->delete(route('owner.attachments.destroy', $photo))->assertSessionHasErrors('general');
        $this->assertSame(1, $this->asTenant($this->tenant, fn () => Attachment::count()));
        Storage::disk('private')->assertExists($photo->path);
    }

    public function test_photos_of_an_open_ticket_can_be_deleted(): void
    {
        $complaint = $this->complaint();
        $this->actingAs($this->owner)->post(route('owner.complaints.photos.store', $complaint), ['file' => UploadedFile::fake()->image('a.png')]);
        $photo = $this->asTenant($this->tenant, fn () => Attachment::sole());

        $this->actingAs($this->owner)->delete(route('owner.attachments.destroy', $photo))->assertRedirect(route('owner.complaints.show', $complaint));

        $this->assertSame(0, $this->asTenant($this->tenant, fn () => Attachment::count()));
    }

    public function test_a_ticket_of_a_removed_resident_still_renders(): void
    {
        $complaint = $this->complaint();
        $this->asTenant($this->tenant, fn () => $complaint->resident->delete());

        $this->actingAs($this->owner)->get(route('owner.complaints.show', $complaint))->assertOk()->assertSee('Penghuni dihapus');
        $this->actingAs($this->owner)->get(route('owner.complaints.index'))->assertOk()->assertSee('Penghuni dihapus');
    }

    public function test_uploaded_file_names_never_reach_the_audit_trail(): void
    {
        $complaint = $this->complaint();
        $this->actingAs($this->owner)->post(route('owner.complaints.photos.store', $complaint), ['file' => UploadedFile::fake()->image('foto_budi_kamar3.png')]);

        $log = $this->asTenant($this->tenant, fn () => AuditLog::where('event', 'created')->get()->first(fn ($l) => ($l->new_values['category'] ?? null) === 'complaint_photo'));

        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('original_name', $log->new_values);
        $this->assertStringNotContainsString('budi', json_encode($log->new_values));
    }

    public function test_ticket_text_is_masked_in_the_audit_trail(): void
    {
        $this->actingAs($this->owner)->post(route('owner.complaints.store'), $this->payload(['description' => 'Hubungi saya di 081234567890']))->assertSessionHasNoErrors();

        $log = $this->asTenant($this->tenant, fn () => AuditLog::where('event', 'created')->get()->first(fn ($l) => isset($l->new_values['category'])));

        $this->assertNotNull($log);
        $this->assertSame('***', $log->new_values['description']);
        $this->assertSame('***', $log->new_values['title']);
    }

    public function test_an_expense_can_only_point_to_an_existing_ticket(): void
    {
        $complaint = $this->complaint();

        $linked = $this->expense(['complaint_id' => $complaint->id]);
        $this->assertSame($complaint->id, $this->asTenant($this->tenant, fn () => Expense::findOrFail($linked->id))->complaint_id);

        $this->expectException(QueryException::class);
        $this->expense(['complaint_id' => 999999]);
    }

    public function test_the_full_cycle_works_in_the_ui(): void
    {
        $staff = $this->staffWith();

        $this->actingAs($this->owner)->post(route('owner.complaints.store'), $this->payload())->assertSessionHasNoErrors();
        $complaint = $this->asTenant($this->tenant, fn () => Complaint::sole());

        $this->actingAs($this->owner)->post(route('owner.complaints.assign', $complaint), ['assigned_to' => $staff->id]);
        $this->actingAs($this->owner)->post(route('owner.complaints.comments.store', $complaint), ['body' => 'Akan segera diperbaiki']);
        $this->actingAs($this->owner)->post(route('owner.complaints.status', $complaint), ['status' => 'in_progress']);
        $this->actingAs($this->owner)->post(route('owner.complaints.status', $complaint), ['status' => 'done']);

        $this->actingAs($this->owner)->get(route('owner.complaints.show', $complaint))->assertOk()
            ->assertSee('Lampu kamar mati')->assertSee($staff->name)->assertSee('Akan segera diperbaiki')
            ->assertSee('Selesai')->assertSee('Tiket sudah ditutup')->assertDontSee('Kirim komentar');

        $closed = $this->fresh($complaint);
        $this->assertNotNull($closed->closed_at);
        $this->assertSame($staff->id, $closed->assigned_to);
    }

    private function assertThrowsValidation(callable $callback): void
    {
        try {
            $callback();
            $this->fail('ValidationException diharapkan.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }
}
