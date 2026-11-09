<?php

namespace Tests\Feature\Residents;

use App\Models\Resident;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportResidentsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->ownerA = User::factory()->forTenant($this->tenantA)->owner()->create();
    }

    private function inTenant(Tenant $tenant, callable $callback): mixed
    {
        return TenantContext::instance()->runAs($tenant, $callback);
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('penghuni.csv', $content);
    }

    private function import(string $content)
    {
        return $this->actingAs($this->ownerA)->post(route('owner.residents.import'), ['file' => $this->csv($content)]);
    }

    public function test_baris_valid_masuk_dan_nomor_dinormalisasi(): void
    {
        $response = $this->import("nama,whatsapp,email,pekerjaan,kontak darurat,hubungan,telepon darurat,catatan\nAgus,0812-1111-0001,agus@contoh.id,Karyawan,Ibu Agus,Ibu,0811000111,Perokok\nBudi,+62 812 1111 0002,,Mahasiswa,,,,\n");

        $response->assertRedirect()->assertSessionHas('resident_import_result', fn (array $r) => $r['created'] === 2 && $r['failures'] === [] && $r['duplicates'] === []);

        $this->inTenant($this->tenantA, function () {
            $this->assertSame(['+6281211110001', '+6281211110002'], Resident::query()->orderBy('id')->pluck('phone')->all());
            $agus = Resident::query()->where('name', 'Agus')->firstOrFail();
            $this->assertSame('Karyawan', $agus->occupation);
            $this->assertSame('+62811000111', $agus->emergency_phone);
            $this->assertSame('Perokok', $agus->notes);
        });
    }

    public function test_nomor_ganda_ditandai_tetapi_tidak_ditolak_dan_baris_valid_tetap_masuk(): void
    {
        $this->inTenant($this->tenantA, fn () => Resident::factory()->create(['name' => 'Sudah Ada', 'phone' => '+6281200000001']));
        // Nomor yang sama di tenant lain bukan duplikat.
        $this->inTenant($this->tenantB, fn () => Resident::factory()->create(['phone' => '+6281200000003']));

        $response = $this->import("nama;whatsapp\nBaru Satu;081200000001\nBaru Dua;081200000002\nBaru Tiga;081200000002\nBaru Empat;081200000003\nRusak;12345\n;081200000099\n");

        $response->assertSessionHas('resident_import_result', function (array $r) {
            return $r['created'] === 4
                && count($r['failures']) === 2
                && $r['failures'][0]['row'] === 6 && $r['failures'][1]['row'] === 7
                && collect($r['duplicates'])->pluck('row')->sort()->values()->all() === [2, 4];
        });

        $this->inTenant($this->tenantA, function () {
            $this->assertSame(5, Resident::query()->count());
            $this->assertSame(2, Resident::query()->where('phone', '+6281200000002')->count());
            $this->assertSame(0, Resident::query()->where('name', 'Rusak')->count());
        });
    }

    public function test_impor_tanpa_kolom_nama_atau_whatsapp_ditolak(): void
    {
        $this->import("email\nx@y.id\n")->assertSessionHasErrors('file');
        $this->import("nama\nAgus\n")->assertSessionHasErrors('file');

        $this->inTenant($this->tenantA, fn () => $this->assertSame(0, Resident::query()->count()));
    }

    public function test_lebih_dari_500_baris_ditolak(): void
    {
        $rows = collect(range(1, 501))->map(fn ($i) => "Penghuni {$i},08125".str_pad((string) $i, 7, '0', STR_PAD_LEFT))->implode("\n");

        $this->import("nama,whatsapp\n{$rows}\n")->assertSessionHasErrors('file');

        $this->inTenant($this->tenantA, fn () => $this->assertSame(0, Resident::query()->count()));
    }

    public function test_berkas_selain_csv_ditolak(): void
    {
        $this->actingAs($this->ownerA)->post(route('owner.residents.import'), [
            'file' => UploadedFile::fake()->create('penghuni.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('file');
    }

    public function test_impor_masuk_ke_tenant_pengimpor_saja(): void
    {
        $this->import("nama,whatsapp\nAgus,081200000001\n");

        $this->inTenant($this->tenantB, fn () => $this->assertSame(0, Resident::query()->count()));
        $this->inTenant($this->tenantA, fn () => $this->assertSame(1, Resident::query()->count()));
    }

    public function test_templat_csv_dapat_diunduh(): void
    {
        $this->actingAs($this->ownerA)->get(route('owner.residents.template'))
            ->assertOk()
            ->assertDownload('templat-impor-penghuni.csv');
    }

    public function test_impor_butuh_login(): void
    {
        $this->post(route('owner.residents.import'), ['file' => $this->csv("nama,whatsapp\nA,081200000001\n")])->assertRedirect();
    }
}
