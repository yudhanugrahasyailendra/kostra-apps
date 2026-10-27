<?php

namespace Tests\Feature\Finance;

use App\Models\Invoice;
use App\Models\Resident;
use App\Services\Reports\IncomeReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\FinanceFixtures;
use Tests\TestCase;

class ReportAccessAndExportTest extends TestCase
{
    use FinanceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFinance();
    }

    private function seedData(): void
    {
        $invoice = $this->openInvoice();
        $this->record([[$invoice, 900_000]], ['paid_on' => '2026-10-02']);
        $this->invoiceDueDaysAgo(9, [], 1_250_000);
        $this->expense(['amount' => 250_000, 'spent_on' => '2026-10-03']);
    }

    /**
     * @return list<list<mixed>>
     */
    private function xlsxRows(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }

        $reader->close();

        return $rows;
    }

    public function test_every_report_requires_the_finance_view_permission(): void
    {
        $plain = $this->staffWith();

        foreach (['pemasukan', 'tunggakan', 'okupansi', 'rekap'] as $report) {
            $this->actingAs($plain)->get(route('owner.reports.show', $report))->assertForbidden();
            $this->actingAs($plain)->get(route('owner.reports.show', [$report, 'format' => 'xlsx']))->assertForbidden();
            $this->actingAs($plain)->get(route('owner.reports.show', [$report, 'format' => 'pdf']))->assertForbidden();
            $this->actingAs($this->staffWith(['finance.view']))->get(route('owner.reports.show', $report))->assertOk();
        }

        $this->actingAs($plain)->get(route('owner.reports.index'))->assertForbidden();
        $this->actingAs($this->owner)->get(route('owner.reports.index'))->assertRedirect(route('owner.reports.show', 'pemasukan'));
        $this->actingAs($this->owner)->get(route('owner.reports.show', 'tidak-ada'))->assertNotFound();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('owner.reports.index'))->assertRedirect(route('login'));
        $this->get(route('owner.reports.show', 'pemasukan'))->assertRedirect(route('login'));
    }

    public function test_the_nav_hides_finance_links_from_staff_without_permission(): void
    {
        $this->actingAs($this->staffWith())->get(route('owner.dashboard'))->assertOk()
            ->assertDontSee(route('owner.reports.index'), false)->assertDontSee(route('owner.expenses.index'), false);

        $this->actingAs($this->owner)->get(route('owner.dashboard'))->assertSee(route('owner.reports.index'), false);
    }

    public function test_filters_are_validated(): void
    {
        $get = fn (array $query) => $this->actingAs($this->owner)->get(route('owner.reports.show', ['report' => 'pemasukan'] + $query));

        $get(['month' => '2026-13'])->assertSessionHasErrors('month');
        $get(['from' => '2026-10-01'])->assertSessionHasErrors('to');
        $get(['from' => '2026-10-10', 'to' => '2026-10-01'])->assertSessionHasErrors('to');
        $get(['from' => '2025-01-01', 'to' => '2026-10-01'])->assertSessionHasErrors('to');
        $get(['format' => 'docx'])->assertSessionHasErrors('format');
        $get(['from' => '2026-10-01', 'to' => '2026-10-31'])->assertSessionHasNoErrors()->assertOk();
    }

    public function test_a_property_of_another_tenant_is_rejected_as_a_filter(): void
    {
        [$other] = $this->tenantWithOwner('business');
        $foreign = $this->propertyFor($other);

        $this->actingAs($this->owner)->get(route('owner.reports.show', ['report' => 'pemasukan', 'property_id' => $foreign->id]))
            ->assertSessionHasErrors('property_id');
    }

    public function test_reports_never_include_another_tenants_data(): void
    {
        $this->seedData();
        [$other] = $this->tenantWithOwner('business');
        $otherProperty = $this->propertyFor($other, ['name' => 'Kos Tetangga']);
        $this->leaseFor([], $other, $otherProperty);
        $this->generate(null, $other);
        $otherInvoice = $this->asTenant($other, fn () => Invoice::firstOrFail());
        $this->record([[$otherInvoice, 1_500_000]], ['paid_on' => '2026-10-04'], null, $other);
        $this->expense(['amount' => 777_000, 'spent_on' => '2026-10-04'], $other);

        $this->actingAs($this->owner)->get(route('owner.reports.show', ['report' => 'pemasukan', 'month' => '2026-10']))
            ->assertOk()->assertSee('Rp 900.000')->assertDontSee('Kos Tetangga')->assertDontSee('Rp 2.400.000');
        $this->actingAs($this->owner)->get(route('owner.reports.show', ['report' => 'rekap', 'month' => '2026-10']))
            ->assertSee('Rp 250.000')->assertDontSee('777.000');
        $this->actingAs($this->owner)->get(route('owner.reports.show', 'okupansi'))->assertDontSee('Kos Tetangga');
    }

    public function test_the_excel_export_has_the_same_rows_as_the_screen_plus_the_header_block(): void
    {
        $this->seedData();

        $response = $this->actingAs($this->owner)->get(route('owner.reports.show', ['report' => 'pemasukan', 'month' => '2026-10', 'format' => 'xlsx']));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));
        $this->assertStringContainsString('pemasukan-', $response->headers->get('content-disposition'));

        $rows = $this->xlsxRows($response->baseResponse->getFile()->getPathname());

        $this->assertSame('Laporan pemasukan', $rows[0][0]);
        $this->assertContains(['Properti', 'Semua properti'], $rows);
        $this->assertContains(['Periode', 'Oktober 2026'], $rows);
        $this->assertContains(['Tanggal cetak', '15 Oktober 2026'], $rows);
        $this->assertContains(['Properti', 'Jumlah pembayaran', 'Pemasukan'], $rows);
        $this->assertContains([$this->property->name, 1, 900_000], $rows);
        $this->assertContains(['Total', 1, 900_000], $rows);
    }

    public function test_the_arrears_export_lists_the_same_invoices_as_the_screen(): void
    {
        $this->seedData();

        $screen = $this->actingAs($this->owner)->get(route('owner.reports.show', 'tunggakan'))->assertOk();
        $export = $this->actingAs($this->owner)->get(route('owner.reports.show', ['report' => 'tunggakan', 'format' => 'xlsx']));
        $rows = $this->xlsxRows($export->baseResponse->getFile()->getPathname());

        $arrears = collect($rows)->filter(fn ($row) => str_starts_with((string) ($row[0] ?? ''), 'INV-'))->values();

        $this->assertCount(1, $arrears);
        $screen->assertSee($arrears[0][0])->assertSee('Rp 1.250.000');
        $this->assertSame(1_250_000, $arrears[0][7]);
        $this->assertSame('8-30', $arrears[0][6]);
        $this->assertContains(['Umur 8 sampai 30 hari', 1_250_000], $rows);
    }

    public function test_excel_text_cells_that_look_like_formulas_are_neutralised(): void
    {
        $invoice = $this->invoiceDueDaysAgo(9, [], 1_250_000);
        $this->asTenant($this->tenant, fn () => Resident::query()->whereKey($invoice->resident_id)->update(['name' => '=HYPERLINK("http://x.test")']));

        $export = $this->actingAs($this->owner)->get(route('owner.reports.show', ['report' => 'tunggakan', 'format' => 'xlsx']));
        $rows = $this->xlsxRows($export->baseResponse->getFile()->getPathname());

        $row = collect($rows)->first(fn ($r) => str_starts_with((string) ($r[0] ?? ''), 'INV-'));
        $this->assertSame("'=HYPERLINK(\"http://x.test\")", $row[1]);
    }

    public function test_the_pdf_export_is_a_pdf_and_its_view_carries_property_period_and_print_date(): void
    {
        $this->seedData();

        $response = $this->actingAs($this->owner)->get(route('owner.reports.show', ['report' => 'pemasukan', 'month' => '2026-10', 'format' => 'pdf']));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('pemasukan-', $response->headers->get('content-disposition'));

        $table = $this->asTenant($this->tenant, fn () => app(IncomeReport::class)->table(
            CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'), null,
            ['Properti' => $this->property->name, 'Periode' => 'Oktober 2026', 'Tanggal cetak' => '15 Oktober 2026'],
        ));
        $html = view('owner.reports.pdf', ['table' => $table])->render();

        foreach ([$this->property->name, 'Oktober 2026', '15 Oktober 2026', 'Rp 900.000'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
    }

    public function test_the_occupancy_report_exports_with_the_chosen_date(): void
    {
        $this->leaseFor();

        $export = $this->actingAs($this->owner)->get(route('owner.reports.show', ['report' => 'okupansi', 'date' => '2026-10-15', 'format' => 'xlsx']));
        $rows = $this->xlsxRows($export->baseResponse->getFile()->getPathname());

        $this->assertContains(['Periode', 'Per 15 Oktober 2026'], $rows);
        $row = collect($rows)->first(fn ($r) => ($r[0] ?? null) === $this->property->name);
        $this->assertEquals([$this->property->name, 1, 1, 0, 100], $row);
    }

    public function test_all_four_report_screens_render(): void
    {
        $this->seedData();

        foreach (['pemasukan' => 'Laporan pemasukan', 'tunggakan' => 'Laporan tunggakan', 'okupansi' => 'Laporan okupansi', 'rekap' => 'Rekap pemasukan'] as $key => $title) {
            $this->actingAs($this->owner)->get(route('owner.reports.show', $key))->assertOk()
                ->assertSee($title)->assertSee('Unduh Excel')->assertSee('Unduh PDF');
        }
    }
}
