<?php

namespace Tests\Feature\Hardening;

use App\Actions\Residents\AnonymizeResident;
use App\Actions\Staff\AcceptInvitation;
use App\Actions\Staff\InviteStaff;
use App\Enums\ProofStatus;
use App\Exceptions\InvalidInvitation;
use App\Models\Complaint;
use App\Models\Lease;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Plan;
use App\Models\PortalToken;
use App\Models\Tenant;
use App\Services\Plans\PlanLimits;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PortalFixtures;
use Tests\TestCase;

/**
 * Perbaikan dari audit seluruh aplikasi (Fase 12): batas unggahan penghuni, anonimisasi menyeluruh,
 * pencabutan tautan saat check-out, undangan yang diperiksa ulang, dan log tanpa data pribadi.
 */
class AuditFase12Test extends TestCase
{
    use PortalFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->setUpPortal();
    }

    private function upload(string $token, int $invoiceId)
    {
        $this->travel(61)->seconds();

        return $this->post($this->portalUrl($token, '/tagihan/'.$invoiceId.'/bukti'), [
            'amount' => '1.000', 'file' => UploadedFile::fake()->image('bukti.jpg'),
        ]);
    }

    public function test_a_resident_cannot_pile_up_more_than_three_pending_proofs_per_invoice(): void
    {
        [$token, $invoice] = $this->residentWithInvoice();

        foreach (range(1, 3) as $i) {
            $this->upload($token, $invoice->id)->assertSessionHasNoErrors();
        }

        $this->upload($token, $invoice->id)->assertSessionHasErrors('general');
        $this->assertSame(3, $this->asTenant($this->tenant, fn () => PaymentProof::count()));

        // Setelah pemilik memeriksa satu bukti, ada ruang lagi.
        $this->asTenant($this->tenant, fn () => PaymentProof::query()->limit(1)->update(['status' => ProofStatus::Rejected]));
        $this->upload($token, $invoice->id)->assertSessionHasNoErrors();
    }

    public function test_a_resident_cannot_keep_more_than_five_open_complaints(): void
    {
        [$token] = $this->residentWithInvoice();

        foreach (range(1, 5) as $i) {
            $this->travel(61)->seconds();
            $this->post($this->portalUrl($token, '/komplain'), ['category' => 'water', 'title' => "Keluhan {$i}", 'description' => 'Air mati.'])
                ->assertSessionHasNoErrors();
        }

        $this->travel(61)->seconds();
        $this->post($this->portalUrl($token, '/komplain'), ['category' => 'water', 'title' => 'Keluhan 6', 'description' => 'Air mati.'])
            ->assertSessionHasErrors('general');
        $this->assertSame(5, $this->asTenant($this->tenant, fn () => Complaint::count()));
    }

    public function test_anonymizing_a_resident_clears_personal_text_copied_into_other_tables_and_revokes_links(): void
    {
        [$token, $invoice, $resident] = $this->residentWithInvoice();
        $this->post($this->portalUrl($token, '/komplain'), ['category' => 'water', 'title' => 'Keran bocor Rizky', 'description' => 'Hubungi 081234567890'])
            ->assertSessionHasNoErrors();
        $this->upload($token, $invoice->id)->assertSessionHasNoErrors();

        $this->asTenant($this->tenant, function () use ($resident) {
            MessageLog::factory()->for($this->tenant)->create(['resident_id' => $resident->id, 'recipient' => '+6281234567890', 'body' => 'Halo Rizky, tagihan Anda']);
            PaymentProof::query()->update(['sender_note' => 'Transfer a.n. Rizky']);
            Payment::query()->where('resident_id', $resident->id)->update(['notes' => 'Dari Rizky']);

            // Kontrak sudah selesai: anonimisasi baru diizinkan.
            Lease::query()->update(['status' => 'ended', 'active_room_id' => null]);
        });

        $this->asTenant($this->tenant, fn () => app(AnonymizeResident::class)($resident->fresh()));

        $this->asTenant($this->tenant, function () {
            $this->assertSame([['', '[dihapus]']], MessageLog::query()->get(['recipient', 'body'])->map(fn ($l) => [$l->recipient, $l->body])->all());
            $complaint = Complaint::sole();
            $this->assertSame(['[dihapus]', '[dihapus]'], [$complaint->title, $complaint->description]);
            $this->assertNull(PaymentProof::sole()->sender_note);
            $this->assertSame(0, PortalToken::query()->whereNull('revoked_at')->count());
        });

        $this->get($this->portalUrl($token))->assertNotFound();
    }

    public function test_the_last_check_out_revokes_the_portal_link_of_the_former_resident(): void
    {
        [$token, , $resident] = $this->residentWithInvoice();
        $lease = $this->asTenant($this->tenant, fn () => Lease::query()->where('status', 'active')->firstOrFail());
        $this->get($this->portalUrl($token))->assertOk();

        $this->actingAs($this->owner)->post(route('owner.leases.check-out', $lease), [
            'checkout_date' => $this->tenant->today()->toDateString(), 'ended_reason' => 'moved_out',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, $this->asTenant($this->tenant, fn () => PortalToken::query()->where('resident_id', $resident->id)->whereNull('revoked_at')->count()));
        auth()->logout();
        $this->get($this->portalUrl($token))->assertNotFound();
    }

    public function test_accepting_an_invitation_rechecks_the_plan_user_limit_and_the_tenant_status(): void
    {
        $this->setPlan($this->tenant, 'starter'); // 2 pengguna
        $this->actingAs($this->owner);
        [, $token] = $this->asTenant($this->tenant, fn () => app(InviteStaff::class)($this->owner, 'staf@example.com', null));

        // Paket diturunkan ke Gratis (1 pengguna) sebelum undangan diterima.
        $this->setPlan($this->tenant, 'free');

        $this->expectException(InvalidInvitation::class);
        app(AcceptInvitation::class)($token, 'Staf Baru', 'password-rahasia-123');
    }

    public function test_accepting_an_invitation_is_refused_for_a_suspended_tenant(): void
    {
        $this->setPlan($this->tenant, 'business');
        $this->actingAs($this->owner);
        [, $token] = $this->asTenant($this->tenant, fn () => app(InviteStaff::class)($this->owner, 'staf@example.com', null));
        $this->tenant->update(['status' => 'suspended']);

        $this->expectException(InvalidInvitation::class);
        app(AcceptInvitation::class)($token, 'Staf Baru', 'password-rahasia-123');
    }

    public function test_an_invitation_within_the_limit_is_still_accepted(): void
    {
        $this->setPlan($this->tenant, 'starter');
        $this->actingAs($this->owner);
        [, $token] = $this->asTenant($this->tenant, fn () => app(InviteStaff::class)($this->owner, 'staf@example.com', null));

        $user = app(AcceptInvitation::class)($token, 'Staf Baru', 'password-rahasia-123');

        $this->assertSame('staf@example.com', $user->email);
        $this->assertSame(2, $this->asTenant($this->tenant, fn () => app(PlanLimits::class)->usedUsers($this->tenant)));
    }

    public function test_database_errors_are_reported_without_their_bound_values(): void
    {
        Log::spy();
        $secret = 'Rizky Dwi Saputra 081234567890';

        try {
            \DB::statement('INSERT INTO tidak_ada (nama) VALUES (?)', [$secret]);
        } catch (QueryException $e) {
            report($e);
        }

        Log::shouldHaveReceived('error')->withArgs(function ($message, $context = []) use ($secret) {
            return $message === 'Galat basis data.' && ! str_contains(json_encode($context), $secret);
        })->once();
    }

    private function setPlan(Tenant $tenant, string $key): void
    {
        $tenant->update(['plan_id' => Plan::query()->where('key', $key)->value('id')]);
        $this->tenant = $tenant->fresh();
    }
}
