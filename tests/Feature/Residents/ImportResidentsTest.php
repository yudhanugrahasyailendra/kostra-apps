<?php

namespace Tests\Feature\Residents;

use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

class ImportResidentsTest extends TestCase
{
    use CreatesTenantFixtures, RefreshDatabase;

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('penghuni.csv', $content);
    }

    public function test_valid_rows_are_imported_duplicates_flagged_and_invalid_rows_reported(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        Resident::factory()->for($tenant)->create(['name' => 'Sudah Ada', 'phone' => '+6281200000009']);

        $content = <<<'CSV'
nama,telepon,email,pekerjaan,kontak_darurat_nama,kontak_darurat_telepon
Siti Nur Aisyah,0812-0000-0001,siti@example.com,Karyawan,Ibu Siti,081311110000
Bima Pratama,6281200000002,,Mahasiswa,,
Ganda Lama,081200000009,,,,
Ganda Dalam Berkas,081200000001,,,,
,081200000003,,,,
Tanpa Nomor,,,,,
Nomor Salah,12345,,,,
Email Salah,081200000004,bukan-email,,,
Darurat Salah,081200000005,,,Ayah,xyz
CSV;

        $this->actingAs($owner)->post(route('owner.residents.import'), [
            'file' => $this->csv($content), 'consent' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('owner.residents.index'));

        $residents = $this->asTenant($tenant, fn () => Resident::orderBy('id')->get()->keyBy('name'));
        $this->assertEqualsCanonicalizing(
            ['Sudah Ada', 'Siti Nur Aisyah', 'Bima Pratama', 'Ganda Lama', 'Ganda Dalam Berkas'],
            $residents->keys()->all(),
        );
        $this->assertSame('+6281200000001', $residents['Siti Nur Aisyah']->phone);
        $this->assertSame('+6281311110000', $residents['Siti Nur Aisyah']->emergency_phone);
        $this->assertNotNull($residents['Siti Nur Aisyah']->consented_at);

        $duplicates = collect(session('import_duplicates'));
        $this->assertSame([4, 5], $duplicates->pluck('line')->all());

        $failures = collect(session('import_failures'));
        $this->assertSame([6, 7, 8, 9, 10], $failures->pluck('line')->all());
        $this->assertStringContainsString('Nama kosong', $failures->firstWhere('line', 6)['reason']);
        $this->assertStringContainsString('Nomor WhatsApp', $failures->firstWhere('line', 7)['reason']);
        $this->assertStringContainsString('Nomor WhatsApp', $failures->firstWhere('line', 8)['reason']);
        $this->assertStringContainsString('Email', $failures->firstWhere('line', 9)['reason']);
        $this->assertStringContainsString('kontak darurat', $failures->firstWhere('line', 10)['reason']);
    }

    public function test_consent_is_required_for_import(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post(route('owner.residents.import'), [
            'file' => $this->csv("nama,telepon\nSiti,081200000001\n"),
        ])->assertSessionHasErrors('consent');

        $this->assertSame(0, $this->asTenant($tenant, fn () => Resident::count()));
    }

    public function test_semicolon_bom_and_alias_headers_work(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post(route('owner.residents.import'), [
            'file' => $this->csv("\xEF\xBB\xBFNama;No_HP;Kampus\nAndi Wijaya;081200000010;Unhas\n"), 'consent' => '1',
        ])->assertSessionHasNoErrors();

        $resident = $this->asTenant($tenant, fn () => Resident::firstOrFail());
        $this->assertSame('Andi Wijaya', $resident->name);
        $this->assertSame('+6281200000010', $resident->phone);
        $this->assertSame('Unhas', $resident->occupation);
    }

    public function test_bad_files_are_rejected(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $post = fn ($file) => $this->actingAs($owner)->post(route('owner.residents.import'), ['file' => $file, 'consent' => '1']);

        $post($this->csv("telepon\n081200000001\n"))->assertSessionHasErrors('file');
        $post(UploadedFile::fake()->create('penghuni.pdf', 10, 'application/pdf'))->assertSessionHasErrors('file');
        $post(UploadedFile::fake()->create('penghuni.csv', 6000, 'text/csv'))->assertSessionHasErrors('file');

        $this->assertSame(0, $this->asTenant($tenant, fn () => Resident::count()));
    }

    public function test_staff_may_import_and_formula_like_values_stay_plain_text(): void
    {
        [$tenant] = $this->tenantWithOwner();
        $staff = User::factory()->for($tenant)->staff()->create();

        $this->actingAs($staff)->post(route('owner.residents.import'), [
            'file' => $this->csv("nama,telepon\n=HYPERLINK(\"x\"),081200000001\n"), 'consent' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('=HYPERLINK("x")', $this->asTenant($tenant, fn () => Resident::firstOrFail()->name));
        $this->actingAs($staff)->get(route('owner.residents.index'))->assertOk()->assertSee('=HYPERLINK(&quot;x&quot;)', false);
    }
}
