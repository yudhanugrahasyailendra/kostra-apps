<?php

namespace Tests\Feature\Owner;

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class TenantExportTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> isi CSV per nama berkas */
    private function unzip($response): array
    {
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));

        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $files[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }
        $zip->close();

        return $files;
    }

    public function test_ekspor_hanya_memuat_data_tenant_sendiri_tanpa_kolom_rahasia(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $ownerA = User::factory()->forTenant($tenantA)->owner()->create(['email' => 'pemilik-a@contoh.id']);
        User::factory()->forTenant($tenantB)->owner()->create(['email' => 'pemilik-b@contoh.id']);
        TenantContext::instance()->runAs($tenantA, fn () => Invitation::factory()->state(['invited_by' => $ownerA->id])->create());

        $files = $this->unzip($this->actingAs($ownerA)->get(route('owner.export'))->assertOk());

        $this->assertArrayHasKey('pengguna.csv', $files);
        $this->assertArrayHasKey('undangan.csv', $files);
        $this->assertStringContainsString('pemilik-a@contoh.id', $files['pengguna.csv']);
        $this->assertStringNotContainsString('pemilik-b@contoh.id', $files['pengguna.csv']);

        $all = implode("\n", $files);
        $this->assertStringNotContainsString('password', $all);
        $this->assertStringNotContainsString('token_hash', $all);
        $this->assertStringNotContainsString('remember_token', $all);
    }

    public function test_staf_tanpa_izin_tidak_bisa_mengekspor(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->forTenant($tenant)->staff()->create();

        $this->actingAs($staff)->get(route('owner.export'))->assertForbidden();
    }

    public function test_staf_dengan_izin_bisa_mengekspor(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->forTenant($tenant)->staff(['data.export'])->create();

        $this->actingAs($staff)->get(route('owner.export'))->assertOk();
    }
}
