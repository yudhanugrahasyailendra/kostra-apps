<?php

namespace Tests\Feature\Properties;

use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

class ImportRoomsTest extends TestCase
{
    use CreatesTenantFixtures, RefreshDatabase;

    private function csv(string $content, string $name = 'kamar.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_valid_rows_are_imported_and_failed_rows_are_reported_with_reasons(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner('pro');
        $property = $this->propertyFor($tenant);
        RoomType::factory()->for($tenant)->create(['name' => 'Deluxe AC', 'base_price' => 1_800_000]);
        Room::factory()->for($tenant)->for($property)->create(['number' => '103']);

        $content = <<<'CSV'
nomor,lantai,tipe,harga,fasilitas
101,1,Deluxe AC,,AC|Kamar mandi dalam
102,1,,1.400.000,
103,1,,1.400.000,
,1,,1.400.000,
104,abc,,1.400.000,
105,1,Tidak Ada,,
106,1,,,
107,1,,1500.50,
108,2,,1.250.000,
101,1,,1.400.000,
CSV;

        $response = $this->actingAs($owner)->post(route('owner.rooms.import'), [
            'property_id' => $property->id,
            'file' => $this->csv($content),
        ]);

        $response->assertSessionHasNoErrors()->assertSessionHas('import_failures');

        $rooms = $this->asTenant($tenant, fn () => Room::orderBy('id')->get()->keyBy('number'));
        $this->assertEqualsCanonicalizing(['101', '102', '103', '108'], $rooms->keys()->all());
        $this->assertSame(1_800_000, $rooms['101']->price);
        $this->assertSame(['AC', 'Kamar mandi dalam'], $rooms['101']->facilities);
        $this->assertSame(1_400_000, $rooms['102']->price);
        $this->assertSame(1_250_000, $rooms['108']->price);

        $failures = collect(session('import_failures'));
        $this->assertCount(7, $failures);
        $this->assertSame([4, 5, 6, 7, 8, 9, 11], $failures->pluck('line')->all());
        $this->assertStringContainsString('sudah dipakai', $failures->firstWhere('line', 4)['reason']);
        $this->assertStringContainsString('kosong', $failures->firstWhere('line', 5)['reason']);
        $this->assertStringContainsString('Lantai', $failures->firstWhere('line', 6)['reason']);
        $this->assertStringContainsString('tidak ditemukan', $failures->firstWhere('line', 7)['reason']);
        $this->assertStringContainsString('Harga wajib', $failures->firstWhere('line', 8)['reason']);
        $this->assertStringContainsString('Harga harus', $failures->firstWhere('line', 9)['reason']);
        $this->assertStringContainsString('sudah dipakai', $failures->firstWhere('line', 11)['reason']);
    }

    public function test_semicolon_delimiter_bom_and_indonesian_headers_work(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $property = $this->propertyFor($tenant);

        $content = "\xEF\xBB\xBFNomor;Lantai;Harga\n201;2;1500000\n202;2;1600000\n";

        $this->actingAs($owner)->post(route('owner.rooms.import'), [
            'property_id' => $property->id, 'file' => $this->csv($content),
        ])->assertSessionHasNoErrors();

        $this->assertSame(['201', '202'], $this->asTenant($tenant, fn () => Room::orderBy('id')->pluck('number')->all()));
    }

    public function test_import_stops_at_the_plan_limit_and_reports_the_rest(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $property = $this->propertyFor($tenant);
        Room::factory()->count(8)->for($tenant)->for($property)->create();

        $content = "nomor,harga\n301,1000000\n302,1000000\n303,1000000\n304,1000000\n";

        $this->actingAs($owner)->post(route('owner.rooms.import'), [
            'property_id' => $property->id, 'file' => $this->csv($content),
        ]);

        $this->assertSame(10, $this->asTenant($tenant, fn () => Room::count()));
        $failures = collect(session('import_failures'));
        $this->assertCount(2, $failures);
        $this->assertStringContainsString('batas', $failures->first()['reason']);
    }

    public function test_file_without_number_column_or_wrong_type_or_too_large_is_rejected(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $property = $this->propertyFor($tenant);

        $this->actingAs($owner)->post(route('owner.rooms.import'), [
            'property_id' => $property->id, 'file' => $this->csv("lantai,harga\n1,1000000\n"),
        ])->assertSessionHasErrors('file');

        $this->actingAs($owner)->post(route('owner.rooms.import'), [
            'property_id' => $property->id, 'file' => UploadedFile::fake()->create('kamar.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('file');

        $this->actingAs($owner)->post(route('owner.rooms.import'), [
            'property_id' => $property->id, 'file' => UploadedFile::fake()->create('kamar.csv', 6000, 'text/csv'),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, $this->asTenant($tenant, fn () => Room::count()));
    }

    public function test_formula_like_values_are_stored_as_plain_text_and_never_executed(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $property = $this->propertyFor($tenant);

        $this->actingAs($owner)->post(route('owner.rooms.import'), [
            'property_id' => $property->id,
            'file' => $this->csv("nomor,harga,fasilitas\n=1+1,1000000,=HYPERLINK(\"x\")\n"),
        ]);

        $room = $this->asTenant($tenant, fn () => Room::firstOrFail());
        $this->assertSame('=1+1', $room->number);

        $this->actingAs($owner)->get(route('owner.rooms.index'))->assertOk()->assertSee('=1+1', false);
    }
}
