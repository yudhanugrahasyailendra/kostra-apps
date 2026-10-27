<?php

namespace Tests\Feature\Residents;

use App\Models\AuditLog;
use App\Models\Resident;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

class ResidentPrivacyAndScaleTest extends TestCase
{
    use CreatesTenantFixtures, RefreshDatabase;

    public function test_no_column_can_hold_a_national_id_number(): void
    {
        foreach (['residents', 'attachments'] as $table) {
            foreach (Schema::getColumnListing($table) as $column) {
                $this->assertDoesNotMatchRegularExpression('/ktp|nik|national|identity|id_number|id_card/i', $column, "{$table}.{$column}");
            }
        }
    }

    public function test_audit_log_records_which_personal_fields_changed_but_never_their_values(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $resident = Resident::factory()->for($tenant)->create(['name' => 'Nama Lama', 'phone' => '+6281200000001']);

        $this->actingAs($owner)->put(route('owner.residents.update', $resident), [
            'name' => 'Nama Baru Sekali', 'phone' => '0812 0000 0002',
        ])->assertSessionHasNoErrors();

        $log = $this->withoutTenant(fn () => AuditLog::where('auditable_type', 'resident')
            ->where('auditable_id', $resident->id)->where('event', 'updated')->latest('id')->firstOrFail());

        $this->assertSame($owner->id, $log->user_id);
        $this->assertSame('***', $log->new_values['name']);
        $this->assertSame('***', $log->new_values['phone']);
        $this->assertStringNotContainsString('Nama', json_encode([$log->old_values, $log->new_values]));
        $this->assertStringNotContainsString('+62812', json_encode([$log->old_values, $log->new_values]));
    }

    public function test_searching_500_residents_is_fast_and_query_count_is_constant(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $queries = function () use ($owner): array {
            DB::enableQueryLog();
            $start = microtime(true);
            $this->actingAs($owner)->get(route('owner.residents.index', ['q' => 'andi']))->assertOk();
            $elapsed = microtime(true) - $start;
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();
            DB::flushQueryLog();

            return [$count, $elapsed];
        };

        Resident::factory()->count(5)->for($tenant)->create();
        Resident::factory()->for($tenant)->create(['name' => 'Andi Pertama']);
        [$small] = $queries();

        Resident::factory()->count(495)->for($tenant)->create();
        Resident::factory()->for($tenant)->create(['name' => 'Andi Cari Aku']);
        [$large, $elapsed] = $queries();

        $this->assertSame(502, $this->asTenant($tenant, fn () => Resident::count()));
        $this->assertSame($small, $large, 'Daftar penghuni terkena N+1.');
        $this->assertLessThan(1.0, $elapsed, 'Pencarian di antara 500 penghuni harus kurang dari 1 detik.');
    }

    public function test_residents_are_isolated_between_tenants(): void
    {
        [$tenantA, $ownerA] = $this->tenantWithOwner();
        $tenantB = Tenant::factory()->create();
        $residentA = Resident::factory()->for($tenantA)->create(['name' => 'Warga A']);
        $residentB = Resident::factory()->for($tenantB)->create(['name' => 'Warga B']);

        $this->asTenant($tenantA, function () use ($residentB) {
            $this->assertNull(Resident::find($residentB->id));
            $this->assertSame(1, Resident::count());
        });

        $a = $this->actingAs($ownerA);
        $a->get(route('owner.residents.index'))->assertSee('Warga A')->assertDontSee('Warga B');
        $a->get(route('owner.residents.show', $residentB))->assertNotFound();
        $a->get(route('owner.residents.edit', $residentB))->assertNotFound();
        $a->put(route('owner.residents.update', $residentB), ['name' => 'Diretas', 'phone' => '081200000099'])->assertNotFound();
        $a->delete(route('owner.residents.destroy', $residentB))->assertNotFound();
        $a->post(route('owner.residents.attachments.store', $residentB), [
            'category' => 'photo', 'file' => UploadedFile::fake()->image('a.jpg'),
        ])->assertNotFound();

        $this->assertSame('Warga B', $this->withoutTenant(fn () => Resident::findOrFail($residentB->id)->name));
        $this->assertSame($residentA->tenant_id, $tenantA->id);
    }
}
