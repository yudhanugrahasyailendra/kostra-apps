<?php

namespace Tests\Feature\Properties;

use App\Enums\RoomStatus;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoomManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->makeTenant();
        $this->owner = User::factory()->forTenant($this->tenant)->owner()->create();
        $this->property = $this->inTenant($this->tenant, fn () => Property::factory()->create());
    }

    private function makeTenant(?int $maxProperties = null, ?int $maxRooms = null): Tenant
    {
        $plan = Plan::factory()->create(['max_properties' => $maxProperties, 'max_rooms' => $maxRooms]);

        return Tenant::factory()->create(['plan_id' => $plan->id]);
    }

    private function inTenant(Tenant $tenant, callable $callback): mixed
    {
        return TenantContext::instance()->runAs($tenant, $callback);
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('kamar.csv', $content);
    }

    public function test_membuat_20_kamar_sekaligus_dalam_satu_langkah(): void
    {
        $type = $this->inTenant($this->tenant, fn () => RoomType::factory()->create(['base_price' => 1750000]));

        $this->actingAs($this->owner)->post(route('owner.rooms.bulk'), [
            'property_id' => $this->property->id, 'start' => 101, 'end' => 120, 'floor' => 1, 'room_type_id' => $type->id,
        ])->assertRedirect()->assertSessionHas('success', '20 kamar ditambahkan.');

        $this->inTenant($this->tenant, function () {
            $this->assertSame(20, Room::query()->count());
            $this->assertSame(1750000, Room::query()->where('number', '110')->first()->price);
            $this->assertSame(RoomStatus::Vacant, Room::query()->where('number', '101')->first()->status);
        });
    }

    public function test_massal_gagal_seluruhnya_bila_ada_nomor_yang_sudah_dipakai(): void
    {
        $this->inTenant($this->tenant, fn () => Room::factory()->create(['property_id' => $this->property->id, 'number' => '105']));

        $this->actingAs($this->owner)->post(route('owner.rooms.bulk'), [
            'property_id' => $this->property->id, 'start' => 101, 'end' => 110, 'price' => 1000000,
        ])->assertSessionHasErrors('start');

        $this->inTenant($this->tenant, fn () => $this->assertSame(1, Room::query()->count()));
    }

    public function test_nomor_sama_di_properti_berbeda_boleh_di_properti_sama_ditolak_dan_nomor_terhapus_boleh_dipakai_ulang(): void
    {
        $other = $this->inTenant($this->tenant, fn () => Property::factory()->create());
        $payload = ['number' => '201', 'price' => 1000000];

        $this->actingAs($this->owner)->post(route('owner.rooms.store'), $payload + ['property_id' => $this->property->id])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('owner.rooms.store'), $payload + ['property_id' => $other->id])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('owner.rooms.store'), $payload + ['property_id' => $this->property->id])
            ->assertSessionHasErrors(['number' => 'Nomor kamar sudah dipakai di properti ini.']);

        $room = $this->inTenant($this->tenant, fn () => Room::query()->where('property_id', $this->property->id)->where('number', '201')->firstOrFail());
        $this->actingAs($this->owner)->delete(route('owner.rooms.destroy', $room))->assertRedirect();

        $this->actingAs($this->owner)->post(route('owner.rooms.store'), $payload + ['property_id' => $this->property->id])->assertSessionHasNoErrors();
    }

    public function test_kamar_tidak_bisa_diatur_terisi_secara_manual(): void
    {
        $this->actingAs($this->owner)->post(route('owner.rooms.store'), [
            'property_id' => $this->property->id, 'number' => '301', 'price' => 1000000, 'status' => 'occupied',
        ])->assertSessionHasErrors('status');

        $this->actingAs($this->owner)->post(route('owner.rooms.store'), [
            'property_id' => $this->property->id, 'number' => '302', 'price' => 1000000, 'status' => 'maintenance',
        ])->assertSessionHasNoErrors();
    }

    public function test_impor_baris_valid_masuk_dan_baris_gagal_dilaporkan_dengan_alasan(): void
    {
        $this->inTenant($this->tenant, fn () => RoomType::factory()->create(['name' => 'Standar', 'base_price' => 1200000]));
        $this->inTenant($this->tenant, fn () => Room::factory()->create(['property_id' => $this->property->id, 'number' => '103']));

        $content = implode("\n", [
            'nomor;lantai;tipe;harga;catatan',
            '101;1;Standar;;Dekat tangga',       // harga dari tipe
            '102;1;;Rp 1.500.000;',              // harga diformat
            '103;1;Standar;1000000;',            // nomor ganda
            '104;x;Standar;1000000;',            // lantai salah
            '105;1;Mewah;1000000;',              // tipe tak ada
            '106;1;;;',                          // tanpa harga dan tipe
            '101;2;Standar;1000000;',            // ganda di dalam berkas
        ]);

        $response = $this->actingAs($this->owner)->post(route('owner.rooms.import'), [
            'property_id' => $this->property->id, 'file' => $this->csv($content),
        ])->assertRedirect();

        $result = session('import_result');
        $this->assertSame(2, $result['created']);
        $this->assertSame([4, 5, 6, 7, 8], array_column($result['failures'], 'row'));
        $this->assertStringContainsString('sudah dipakai', $result['failures'][0]['reason']);
        $this->assertStringContainsString('Lantai', $result['failures'][1]['reason']);
        $this->assertStringContainsString('Mewah', $result['failures'][2]['reason']);
        $this->assertStringContainsString('Harga kosong', $result['failures'][3]['reason']);

        $this->inTenant($this->tenant, function () {
            $this->assertSame(1200000, Room::query()->where('number', '101')->first()->price);
            $this->assertSame(1500000, Room::query()->where('number', '102')->first()->price);
        });
    }

    public function test_impor_tanpa_kolom_nomor_ditolak(): void
    {
        $this->actingAs($this->owner)->post(route('owner.rooms.import'), [
            'property_id' => $this->property->id, 'file' => $this->csv("harga\n1000000"),
        ])->assertSessionHasErrors('file');
    }

    public function test_paket_gratis_tidak_bisa_membuat_kamar_ke_11_atau_properti_ke_2(): void
    {
        $tenant = $this->makeTenant(maxProperties: 1, maxRooms: 10);
        $owner = User::factory()->forTenant($tenant)->owner()->create();
        $property = $this->inTenant($tenant, fn () => Property::factory()->create());

        $this->actingAs($owner)->post(route('owner.rooms.bulk'), [
            'property_id' => $property->id, 'start' => 1, 'end' => 10, 'price' => 1000000,
        ])->assertSessionHasNoErrors();

        $this->actingAs($owner)->post(route('owner.rooms.store'), [
            'property_id' => $property->id, 'number' => '11', 'price' => 1000000,
        ])->assertSessionHasErrors('limit');
        $this->assertStringContainsString('Naik paket', session('errors')->get('limit')[0]);

        $this->actingAs($owner)->post(route('owner.properties.store'), ['name' => 'Cabang 2'])
            ->assertSessionHasErrors('limit');

        $this->inTenant($tenant, function () {
            $this->assertSame(10, Room::query()->count());
            $this->assertSame(1, Property::query()->count());
        });
    }

    public function test_massal_dan_impor_menghormati_batas_paket(): void
    {
        $tenant = $this->makeTenant(maxProperties: 1, maxRooms: 10);
        $owner = User::factory()->forTenant($tenant)->owner()->create();
        $property = $this->inTenant($tenant, fn () => Property::factory()->create());

        $this->actingAs($owner)->post(route('owner.rooms.bulk'), [
            'property_id' => $property->id, 'start' => 1, 'end' => 11, 'price' => 1000000,
        ])->assertSessionHasErrors('limit');
        $this->inTenant($tenant, fn () => $this->assertSame(0, Room::query()->count()));

        $rows = collect(range(1, 12))->map(fn ($n) => "{$n};1;;1000000;")->prepend('nomor;lantai;tipe;harga;catatan')->implode("\n");
        $this->actingAs($owner)->post(route('owner.rooms.import'), ['property_id' => $property->id, 'file' => $this->csv($rows)]);

        $result = session('import_result');
        $this->assertSame(10, $result['created']);
        $this->assertCount(2, $result['failures']);
        $this->assertStringContainsString('batas kamar', $result['failures'][0]['reason']);
    }

    public function test_mengubah_harga_tipe_tidak_mengubah_harga_kamar_yang_ada(): void
    {
        $type = $this->inTenant($this->tenant, fn () => RoomType::factory()->create(['base_price' => 1500000]));
        $room = $this->inTenant($this->tenant, fn () => Room::factory()->create([
            'property_id' => $this->property->id, 'room_type_id' => $type->id, 'price' => 1500000,
        ]));

        $this->actingAs($this->owner)->patch(route('owner.room-types.update', $type), [
            'name' => $type->name, 'base_price' => 2000000,
        ])->assertSessionHasNoErrors();

        $this->inTenant($this->tenant, function () use ($type, $room) {
            $this->assertSame(2000000, $type->fresh()->base_price);
            $this->assertSame(1500000, $room->fresh()->price);
        });
    }

    public function test_staf_boleh_melihat_tetapi_tidak_boleh_mengubah(): void
    {
        $staff = User::factory()->forTenant($this->tenant)->staff()->create();

        $this->actingAs($staff)->get(route('owner.rooms.index'))->assertOk();
        $this->actingAs($staff)->get(route('owner.properties.index'))->assertOk();
        $this->actingAs($staff)->get(route('owner.room-types.index'))->assertOk();

        $this->actingAs($staff)->post(route('owner.rooms.store'), ['property_id' => $this->property->id, 'number' => '1', 'price' => 1])->assertForbidden();
        $this->actingAs($staff)->post(route('owner.properties.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($staff)->post(route('owner.room-types.store'), ['name' => 'X', 'base_price' => 1])->assertForbidden();
    }

    public function test_properti_yang_masih_punya_kamar_tidak_bisa_dihapus(): void
    {
        $this->inTenant($this->tenant, fn () => Room::factory()->create(['property_id' => $this->property->id]));

        $this->actingAs($this->owner)->delete(route('owner.properties.destroy', $this->property))->assertSessionHasErrors('property');
    }

    public function test_daftar_100_kamar_tanpa_n_plus_1(): void
    {
        $type = $this->inTenant($this->tenant, fn () => RoomType::factory()->create());
        $this->inTenant($this->tenant, fn () => Room::factory()->count(100)->create(['property_id' => $this->property->id, 'room_type_id' => $type->id]));

        DB::enableQueryLog();
        $this->actingAs($this->owner)->get(route('owner.rooms.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Owner/Rooms/Index')->has('rooms', 100)->where('rooms.0.property_name', $this->property->name));
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(20, $queries, "Terlalu banyak query ({$queries}) untuk 100 kamar.");
    }

    public function test_filter_daftar_kamar(): void
    {
        $this->inTenant($this->tenant, function () {
            Room::factory()->create(['property_id' => $this->property->id, 'number' => 'A1']);
            Room::factory()->create(['property_id' => $this->property->id, 'number' => 'A2', 'status' => RoomStatus::Maintenance]);
        });

        $this->actingAs($this->owner)->get(route('owner.rooms.index', ['status' => 'maintenance']))
            ->assertInertia(fn ($page) => $page->has('rooms', 1)->where('rooms.0.number', 'A2'));
    }

    public function test_isolasi_tenant_untuk_properti_tipe_dan_kamar(): void
    {
        $tenantB = $this->makeTenant();
        $ownerB = User::factory()->forTenant($tenantB)->owner()->create();
        [$propertyB, $typeB, $roomB] = $this->inTenant($tenantB, function () {
            $property = Property::factory()->create();

            return [$property, RoomType::factory()->create(), Room::factory()->create(['property_id' => $property->id])];
        });

        // Lewat URL: route model binding terscope tenant.
        $this->actingAs($this->owner)->patch(route('owner.rooms.update', $roomB), ['property_id' => $this->property->id, 'number' => 'X', 'price' => 1])->assertNotFound();
        $this->actingAs($this->owner)->delete(route('owner.rooms.destroy', $roomB))->assertNotFound();
        $this->actingAs($this->owner)->patch(route('owner.properties.update', $propertyB), ['name' => 'X'])->assertNotFound();
        $this->actingAs($this->owner)->delete(route('owner.properties.destroy', $propertyB))->assertNotFound();
        $this->actingAs($this->owner)->patch(route('owner.room-types.update', $typeB), ['name' => 'X', 'base_price' => 1])->assertNotFound();
        $this->actingAs($this->owner)->delete(route('owner.room-types.destroy', $typeB))->assertNotFound();

        // Lewat input: id properti dan tipe milik tenant lain ditolak validasi.
        $this->actingAs($this->owner)->post(route('owner.rooms.store'), ['property_id' => $propertyB->id, 'number' => '1', 'price' => 1])->assertSessionHasErrors('property_id');
        $this->actingAs($this->owner)->post(route('owner.rooms.store'), ['property_id' => $this->property->id, 'room_type_id' => $typeB->id, 'number' => '1'])->assertSessionHasErrors('room_type_id');
        $this->actingAs($this->owner)->post(route('owner.rooms.bulk'), ['property_id' => $propertyB->id, 'start' => 1, 'end' => 2, 'price' => 1])->assertSessionHasErrors('property_id');
        $this->actingAs($this->owner)->post(route('owner.rooms.import'), ['property_id' => $propertyB->id, 'file' => $this->csv("nomor;harga\n1;1")])->assertSessionHasErrors('property_id');
        $this->actingAs($this->owner)->post(route('owner.room-types.store'), ['property_id' => $propertyB->id, 'name' => 'X', 'base_price' => 1])->assertSessionHasErrors('property_id');

        // Daftar hanya memuat data tenant sendiri.
        $this->actingAs($this->owner)->get(route('owner.rooms.index'))
            ->assertInertia(fn ($page) => $page->has('properties', 1)->where('properties.0.id', $this->property->id)->has('rooms', 0)->has('roomTypes', 0));

        $this->inTenant($tenantB, fn () => $this->assertSame(1, Room::query()->count()));
        $this->actingAs($ownerB)->get(route('owner.rooms.index'))->assertInertia(fn ($page) => $page->has('rooms', 1));
    }

    public function test_props_tidak_membocorkan_tenant_id(): void
    {
        $this->inTenant($this->tenant, fn () => Room::factory()->create(['property_id' => $this->property->id]));

        $this->actingAs($this->owner)->get(route('owner.rooms.index'))
            ->assertInertia(fn ($page) => $page->missing('rooms.0.tenant_id')->missing('properties.0.tenant_id')->missing('rooms.0.deleted_at'));
    }

    public function test_ekspor_memuat_properti_tipe_dan_kamar_tenant_sendiri(): void
    {
        $this->inTenant($this->tenant, fn () => Room::factory()->create(['property_id' => $this->property->id, 'number' => 'EKS-1']));
        $tenantB = $this->makeTenant();
        $this->inTenant($tenantB, function () {
            $property = Property::factory()->create();
            Room::factory()->create(['property_id' => $property->id, 'number' => 'RAHASIA-B']);
        });

        $response = $this->actingAs($this->owner)->get(route('owner.export'))->assertOk();

        $zip = new \ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString('EKS-1', (string) $zip->getFromName('kamar.csv'));
        $this->assertStringNotContainsString('RAHASIA-B', (string) $zip->getFromName('kamar.csv'));
        $this->assertNotFalse($zip->getFromName('properti.csv'));
        $this->assertNotFalse($zip->getFromName('tipe_kamar.csv'));
        $zip->close();
    }
}
