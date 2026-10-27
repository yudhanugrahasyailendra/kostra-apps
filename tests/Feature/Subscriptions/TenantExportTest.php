<?php

namespace Tests\Feature\Subscriptions;

use App\Actions\Attachments\StoreAttachment;
use App\Actions\Subscriptions\StartTrial;
use App\Actions\Subscriptions\SuspendTenant;
use App\Enums\AttachmentCategory;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Export\TenantDataExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\SubscriptionFixtures;
use Tests\TestCase;

class TenantExportTest extends TestCase
{
    use RefreshDatabase, SubscriptionFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->setUpSubscriptions();
    }

    /**
     * @return array<string, list<list<mixed>>> nama lembar => baris (termasuk judul kolom)
     */
    private function sheetsOf($response): array
    {
        $reader = new Reader;
        $reader->open($response->baseResponse->getFile()->getPathname());
        $sheets = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }

        $reader->close();

        return $sheets;
    }

    private function seedData(Tenant $tenant, string $prefix): void
    {
        $this->asTenant($tenant, function () use ($tenant, $prefix) {
            $property = Property::factory()->for($tenant)->create(['name' => $prefix.' Properti']);
            Room::factory()->for($tenant)->for($property)->create(['number' => $prefix.'-101']);
            Resident::factory()->for($tenant)->create(['name' => $prefix.' Penghuni', 'phone' => '+628123450000']);
            $category = ExpenseCategory::create(['name' => $prefix.' Kategori', 'is_system' => false, 'sort_order' => 1]);
            Expense::factory()->for($tenant)->for($category, 'category')->for(User::factory()->for($tenant)->create(), 'creator')
                ->create(['description' => $prefix.' Pengeluaran', 'amount' => 123_000]);
        });
    }

    public function test_the_owner_downloads_one_workbook_with_a_sheet_per_kind_of_data(): void
    {
        [$tenant, $owner] = $this->tenantOn('free');
        $this->seedData($tenant, 'Melati');

        $response = $this->actingAs($owner)->get(route('owner.export.download'))->assertOk();

        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));
        $this->assertStringContainsString('data-'.$tenant->slug, $response->headers->get('content-disposition'));
        $sheets = $this->sheetsOf($response);
        $this->assertSame(['Properti', 'Kamar', 'Penghuni', 'Kontrak', 'Tagihan', 'Pembayaran', 'Pengeluaran'], array_keys($sheets));
        $this->assertSame(['Nama', 'Nomor WhatsApp', 'Email', 'Pekerjaan', 'Aktif', 'Berhenti menerima pesan sejak'], $sheets['Penghuni'][0]);
        $this->assertContains('Melati Penghuni', array_column($sheets['Penghuni'], 0));
        $this->assertContains('Melati-101', array_column($sheets['Kamar'], 1));
        $this->assertContains('Melati Properti', array_column($sheets['Properti'], 0));
        $expense = collect($sheets['Pengeluaran'])->first(fn ($row) => ($row[4] ?? null) === 'Melati Pengeluaran');
        $this->assertSame(123_000, $expense[3], 'Uang diekspor sebagai angka rupiah bulat.');
    }

    public function test_the_export_contains_only_the_owners_own_tenant(): void
    {
        [$mine, $owner] = $this->tenantOn('free');
        [$other] = $this->tenantOn('free');
        $this->seedData($mine, 'Melati');
        $this->seedData($other, 'Anggrek');

        $response = $this->actingAs($owner)->get(route('owner.export.download'));
        $dump = json_encode($this->sheetsOf($response));

        $this->assertStringContainsString('Melati', $dump);
        $this->assertStringNotContainsString('Anggrek', $dump);
        $this->assertStringNotContainsString('+628123450000', json_encode(array_map(fn ($rows) => array_slice($rows, 1), array_diff_key($this->sheetsOf($response), ['Penghuni' => 1]))));
        $this->assertCount(2, $this->sheetsOf($response)['Penghuni'], 'Judul + satu penghuni milik tenant ini.');
    }

    public function test_attachments_and_identity_photos_are_never_exported(): void
    {
        [$tenant, $owner] = $this->tenantOn('free');
        $this->seedData($tenant, 'Melati');
        $this->asTenant($tenant, function () {
            $resident = Resident::query()->firstOrFail();
            app(StoreAttachment::class)($resident, UploadedFile::fake()->image('ktp-rahasia-budi.jpg'), AttachmentCategory::Ktp);
        });

        $dump = json_encode($this->sheetsOf($this->actingAs($owner)->get(route('owner.export.download'))));

        $this->assertStringNotContainsString('ktp-rahasia-budi', $dump);
        $this->assertStringNotContainsStringIgnoringCase('ktp', $dump);
        $this->assertStringNotContainsString('token', strtolower($dump));
        $this->assertStringNotContainsString('password', strtolower($dump));
    }

    public function test_phone_numbers_stay_intact_and_the_row_counts_match_the_sheets(): void
    {
        [$tenant, $owner] = $this->tenantOn('free');
        $this->seedData($tenant, 'Melati');

        $sheets = $this->sheetsOf($this->actingAs($owner)->get(route('owner.export.download')));

        $this->assertContains('+628123450000', array_column($sheets['Penghuni'], 1), 'Nomor internasional tidak diberi apostrof.');
        $counts = $this->asTenant($tenant, fn () => app(TenantDataExporter::class)->counts());
        foreach ($counts as $name => $count) {
            $this->assertSame($count, count($sheets[$name]) - 1, "Jumlah baris lembar {$name} berbeda dari ringkasan.");
        }
    }

    public function test_the_download_is_throttled(): void
    {
        [, $owner] = $this->tenantOn('free');

        foreach (range(1, 3) as $i) {
            $this->actingAs($owner)->get(route('owner.export.download'))->assertOk();
        }

        $this->actingAs($owner)->get(route('owner.export.download'))->assertStatus(429);
    }

    public function test_text_that_looks_like_a_formula_is_neutralised(): void
    {
        [$tenant, $owner] = $this->tenantOn('free');
        $this->asTenant($tenant, fn () => Resident::factory()->for($tenant)->create(['name' => '=HYPERLINK("http://x.test")']));

        $sheets = $this->sheetsOf($this->actingAs($owner)->get(route('owner.export.download')));

        $this->assertContains('\'=HYPERLINK("http://x.test")', array_column($sheets['Penghuni'], 0));
    }

    public function test_the_export_is_available_when_suspended_on_the_free_plan_and_after_a_trial_ended(): void
    {
        [$suspended, $suspendedOwner] = $this->tenantOn('pro');
        app(SuspendTenant::class)($suspended);
        [, $freeOwner] = $this->tenantOn('free');
        [$trial, $trialOwner] = $this->tenantOn('free');
        app(StartTrial::class)($trial, $this->plan('pro'));
        $this->travelTo(now()->addDays(20));
        $this->lifecycle($trial);

        foreach ([$suspendedOwner, $freeOwner, $trialOwner] as $owner) {
            $this->actingAs($owner)->get(route('owner.export.index'))->assertOk()->assertSee('Unduh Excel');
            $this->actingAs($owner)->get(route('owner.export.download'))->assertOk();
        }
    }

    public function test_only_the_owner_can_export(): void
    {
        [$tenant] = $this->tenantOn('free');
        $staff = User::factory()->for($tenant)->staff(['finance.view', 'settings.manage', 'documents.view'])->create();

        $this->actingAs($staff)->get(route('owner.export.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('owner.export.download'))->assertForbidden();
        auth()->logout();
        $this->get(route('owner.export.download'))->assertRedirect(route('login'));
        $this->actingAs($this->admin())->get(route('owner.export.download'))->assertForbidden();
    }

    public function test_each_download_is_recorded_in_the_audit_trail(): void
    {
        [$tenant, $owner] = $this->tenantOn('free');

        $this->actingAs($owner)->get(route('owner.export.download'))->assertOk();

        $log = $this->withoutTenant(fn () => AuditLog::query()->where('event', 'exported')->latest('id')->first());
        $this->assertNotNull($log);
        $this->assertSame([$tenant->id, $owner->id], [$log->tenant_id, $log->user_id]);
        $this->assertContains('Penghuni', $log->new_values['sheets']);
    }

    public function test_the_export_page_summarises_the_row_counts(): void
    {
        [$tenant, $owner] = $this->tenantOn('free');
        $this->seedData($tenant, 'Melati');

        $this->actingAs($owner)->get(route('owner.export.index'))->assertOk()
            ->assertSee('Properti')->assertSee('Pengeluaran')->assertSee('foto KTP dan berkas lampiran');
    }

    public function test_the_export_is_linked_from_settings(): void
    {
        [, $owner] = $this->tenantOn('free');

        $this->actingAs($owner)->get(route('owner.settings'))->assertSee(route('owner.export.index'), false);
    }
}
