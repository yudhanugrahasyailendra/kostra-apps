<?php

namespace Tests\Feature\Residents;

use App\Models\AuditLog;
use App\Models\Resident;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PhoneNumber;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ResidentManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    private User $staffA;

    private User $ownerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->ownerA = User::factory()->forTenant($this->tenantA)->owner()->create();
        $this->staffA = User::factory()->forTenant($this->tenantA)->staff()->create();
        $this->ownerB = User::factory()->forTenant($this->tenantB)->owner()->create();
    }

    private function inTenant(Tenant $tenant, callable $callback): mixed
    {
        return TenantContext::instance()->runAs($tenant, $callback);
    }

    private function makeResident(Tenant $tenant, array $attributes = []): Resident
    {
        return $this->inTenant($tenant, fn () => Resident::factory()->create($attributes));
    }

    public function test_nomor_lokal_dan_internasional_dinormalisasi_menjadi_nilai_sama(): void
    {
        foreach (['0812-3456-7890', '+62 812 3456 7890', '6281234567890', '81234567890'] as $input) {
            $this->assertSame('+6281234567890', PhoneNumber::normalize($input), $input);
        }

        $this->assertNull(PhoneNumber::normalize('12345'));
        $this->assertNull(PhoneNumber::normalize('abc'));
    }

    public function test_pemilik_menambah_penghuni_dan_nomor_disimpan_internasional(): void
    {
        $this->actingAs($this->ownerA)->post(route('owner.residents.store'), [
            'name' => 'Bagus Tri', 'phone' => '0812-3899-1204', 'email' => 'bagus@contoh.id',
            'occupation' => 'Mahasiswa UGM', 'emergency_name' => 'Ibu Tri', 'emergency_relation' => 'Ibu', 'emergency_phone' => '0811223344',
            'consented' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->inTenant($this->tenantA, function () {
            $resident = Resident::query()->firstOrFail();
            $this->assertSame('+6281238991204', $resident->phone);
            $this->assertSame('+62811223344', $resident->emergency_phone);
            $this->assertTrue($resident->is_active);
            $this->assertNotNull($resident->consented_at);
        });
    }

    public function test_nomor_whatsapp_tidak_valid_ditolak_dengan_pesan_indonesia(): void
    {
        $this->actingAs($this->ownerA)->post(route('owner.residents.store'), ['name' => 'Siti', 'phone' => '12345'])
            ->assertSessionHasErrors(['phone' => 'Nomor WhatsApp tidak valid. Contoh: 0812 3456 7890.']);

        $this->actingAs($this->ownerA)->post(route('owner.residents.store'), ['phone' => '081234567890'])
            ->assertSessionHasErrors('name');

        $this->inTenant($this->tenantA, fn () => $this->assertSame(0, Resident::query()->count()));
    }

    public function test_staf_boleh_menambah_dan_mengubah_tetapi_tidak_menghapus(): void
    {
        $this->actingAs($this->staffA)->post(route('owner.residents.store'), ['name' => 'Dewi', 'phone' => '081200000001'])
            ->assertSessionHasNoErrors();

        $resident = $this->inTenant($this->tenantA, fn () => Resident::query()->firstOrFail());

        $this->actingAs($this->staffA)->patch(route('owner.residents.update', $resident), ['name' => 'Dewi Lestari', 'phone' => '081200000001'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->staffA)->delete(route('owner.residents.destroy', $resident))->assertForbidden();

        $this->actingAs($this->ownerA)->delete(route('owner.residents.destroy', $resident))->assertRedirect();

        $this->inTenant($this->tenantA, function () use ($resident) {
            $this->assertSame(0, Resident::query()->count());
            $this->assertSame('Dewi Lestari', Resident::withTrashed()->findOrFail($resident->id)->name);
        });
    }

    public function test_tidak_ada_kolom_nomor_ktp_di_skema(): void
    {
        foreach (['residents', 'attachments'] as $table) {
            foreach (Schema::getColumnListing($table) as $column) {
                $this->assertDoesNotMatchRegularExpression('/ktp|nik|identity|id_number/i', $column, "{$table}.{$column}");
            }
        }
    }

    public function test_nilai_audit_penghuni_tidak_memuat_data_kontak(): void
    {
        $this->actingAs($this->ownerA)->post(route('owner.residents.store'), [
            'name' => 'Rani', 'phone' => '081277778888', 'email' => 'rani@contoh.id', 'notes' => 'rahasia',
        ])->assertSessionHasNoErrors();

        $log = $this->inTenant($this->tenantA, fn () => AuditLog::query()->where('event', 'created')->where('auditable_type', 'resident')->firstOrFail());
        $json = json_encode($log->new_values);

        $this->assertStringContainsString('Rani', $json);
        $this->assertStringNotContainsString('081277778888', $json);
        $this->assertStringNotContainsString('+6281277778888', $json);
        $this->assertStringNotContainsString('rani@contoh.id', $json);
        $this->assertStringNotContainsString('rahasia', $json);
    }

    public function test_pencarian_dan_filter_status(): void
    {
        $this->makeResident($this->tenantA, ['name' => 'Agus Salim', 'phone' => '+6281200000010']);
        $this->makeResident($this->tenantA, ['name' => 'Budi Santoso', 'phone' => '+6281200000020']);
        $this->makeResident($this->tenantA, ['name' => 'Citra Alumni', 'phone' => '+6281200000030', 'is_active' => false]);

        $this->actingAs($this->ownerA)->get(route('owner.residents.index', ['q' => 'budi']))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Owner/Residents/Index')->has('residents', 1)->where('residents.0.name', 'Budi Santoso'));

        // Pencarian lewat nomor, dengan format lokal.
        $this->actingAs($this->ownerA)->get(route('owner.residents.index', ['q' => '0812 0000 0030']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('residents', 1)->where('residents.0.name', 'Citra Alumni'));

        $this->actingAs($this->ownerA)->get(route('owner.residents.index', ['status' => 'active']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('residents', 2));

        $this->actingAs($this->ownerA)->get(route('owner.residents.index', ['status' => 'inactive']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('residents', 1)->where('residents.0.name', 'Citra Alumni')->where('residents.0.is_active', false));

        $this->actingAs($this->ownerA)->get(route('owner.residents.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('residents', 3));
    }

    public function test_nomor_ganda_ditandai_di_daftar(): void
    {
        $this->makeResident($this->tenantA, ['name' => 'Kembar Satu', 'phone' => '+6281299990000']);
        $this->makeResident($this->tenantA, ['name' => 'Kembar Dua', 'phone' => '+6281299990000']);
        $this->makeResident($this->tenantA, ['name' => 'Unik', 'phone' => '+6281299991111']);
        // Nomor yang sama di tenant lain tidak dihitung sebagai ganda.
        $this->makeResident($this->tenantB, ['name' => 'Tenant B', 'phone' => '+6281299991111']);

        $this->actingAs($this->ownerA)->get(route('owner.residents.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('residents', fn ($residents) => collect($residents)->mapWithKeys(fn ($r) => [$r['name'] => $r['has_duplicate_phone']])->all() == [
                    'Kembar Satu' => true, 'Kembar Dua' => true, 'Unik' => false,
                ]));
    }

    public function test_pencarian_di_antara_500_penghuni_kurang_dari_satu_detik(): void
    {
        $this->inTenant($this->tenantA, fn () => Resident::factory()->count(500)->create());
        $this->inTenant($this->tenantA, fn () => Resident::factory()->create(['name' => 'Zulkifli Unik']));

        $start = microtime(true);
        $this->actingAs($this->ownerA)->get(route('owner.residents.index', ['q' => 'zulkifli']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('residents', 1));
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(1.0, $elapsed, 'Pencarian memakan '.round($elapsed, 2).' detik.');
    }

    public function test_props_daftar_tidak_memuat_kolom_terlarang(): void
    {
        $this->makeResident($this->tenantA, ['notes' => 'catatan internal']);

        $this->actingAs($this->ownerA)->get(route('owner.residents.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->missing('residents.0.tenant_id')
                ->missing('residents.0.deleted_at')
                ->missing('residents.0.notes')
                ->where('can.create', true));
    }

    public function test_isolasi_tenant_lewat_ui_dan_url(): void
    {
        $residentB = $this->makeResident($this->tenantB, ['name' => 'Milik B']);

        $this->actingAs($this->ownerA)->get(route('owner.residents.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('residents', 0));

        $this->actingAs($this->ownerA)->get(route('owner.residents.show', $residentB->id))->assertNotFound();
        $this->actingAs($this->ownerA)->patch(route('owner.residents.update', $residentB->id), ['name' => 'Diretas', 'phone' => '081200000009'])->assertNotFound();
        $this->actingAs($this->ownerA)->delete(route('owner.residents.destroy', $residentB->id))->assertNotFound();

        $this->inTenant($this->tenantB, fn () => $this->assertSame('Milik B', Resident::query()->findOrFail($residentB->id)->name));
    }

    public function test_halaman_penghuni_butuh_login(): void
    {
        $this->get(route('owner.residents.index'))->assertRedirect();
    }
}
