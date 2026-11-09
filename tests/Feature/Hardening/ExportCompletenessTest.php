<?php

namespace Tests\Feature\Hardening;

use App\Actions\ExportTenantData;
use App\Exports\TenantExportRegistry;
use App\Models\AuditLog;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Resident;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Demo\DemoTenantBuilder;
use App\Tenancy\TenantContext;
use Database\Seeders\MessageTemplateSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ExportCompletenessTest extends TestCase
{
    use RefreshDatabase;

    /** Kolom yang tidak boleh keluar dari ekspor pada model mana pun. */
    private const FORBIDDEN_COLUMNS = ['password', 'remember_token', 'token_hash', 'path', 'disk', 'tenant_id', 'secret', 'api_key'];

    /** @return list<class-string> */
    private function tenantModels(): array
    {
        $models = [];

        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');

            if (in_array(BelongsToTenant::class, class_uses_recursive($class), true)) {
                $models[] = $class;
            }
        }

        return $models;
    }

    public function test_setiap_model_tenant_terdaftar_di_registri_ekspor_kecuali_audit_log(): void
    {
        $registered = app(TenantExportRegistry::class)->all();

        // AuditLog sengaja tidak diekspor: log tidak dapat diubah pengguna (NFR-08) dan memuat jejak internal.
        $missing = array_values(array_diff($this->tenantModels(), $registered, [AuditLog::class]));

        $this->assertSame([], $missing, 'Model tenant belum terdaftar di registri ekspor: '.implode(', ', $missing));
    }

    public function test_tidak_ada_kolom_rahasia_pada_definisi_ekspor(): void
    {
        foreach (app(TenantExportRegistry::class)->all() as $model) {
            $this->assertSame([], array_values(array_intersect($model::exportColumns(), self::FORBIDDEN_COLUMNS)), "$model mengekspor kolom terlarang.");
        }
    }

    public function test_ekspor_tenant_demo_tidak_memuat_data_tenant_lain_atau_rahasia(): void
    {
        $this->seed([PlanSeeder::class, MessageTemplateSeeder::class]);

        $other = Tenant::factory()->create();
        TenantContext::instance()->runAs($other, fn () => Resident::factory()->create(['name' => 'PENGHUNI-TENANT-LAIN', 'email' => 'lain@tenant-b.test']));
        User::factory()->forTenant($other)->owner()->create(['email' => 'pemilik-b@tenant-b.test']);

        $result = app(DemoTenantBuilder::class)->build(['rooms' => 6, 'months' => 2, 'email' => 'ekspor@kostra.test', 'password' => 'rahasia-ekspor-12']);
        $owner = $result['owner'];
        $passwordHash = User::query()->withoutGlobalScopes()->findOrFail($owner->id)->getAuthPassword();

        $zipPath = TenantContext::instance()->runAs($result['tenant'], fn () => app(ExportTenantData::class)->handle());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath));
        $all = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $all .= $zip->getFromIndex($i);
        }
        $zip->close();
        @unlink($zipPath);

        $this->assertStringNotContainsString('PENGHUNI-TENANT-LAIN', $all);
        $this->assertStringNotContainsString('tenant-b.test', $all);
        $this->assertStringNotContainsString($passwordHash, $all);
        $this->assertStringNotContainsString('rahasia-ekspor-12', $all);
        $this->assertStringContainsString('ekspor@kostra.test', $all);
    }
}
