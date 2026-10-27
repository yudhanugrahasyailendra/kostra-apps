<?php

namespace App\Actions\Billing;

use App\Actions\Concerns\ResolvesTenant;
use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Events\InvoiceIssued;
use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Support\Dates;
use App\Support\InvoiceMath;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use ValueError;

class GenerateMonthlyInvoices
{
    use ResolvesTenant;

    public function __construct(
        private readonly GenerateInvoiceNumber $numbers,
        private readonly RecalculateInvoice $recalculate,
    ) {}

    /**
     * Menerbitkan tagihan bulanan untuk tenant aktif. Idempoten: unique(lease_id, period) di database
     * dan pemeriksaan di dalam transaksi membuat penjalanan ulang tidak menghasilkan tagihan ganda.
     *
     * Tanpa $period (otomatis): periode = bulan berjalan menurut zona waktu tenant, dan hanya berjalan
     * bila hari ini sudah mencapai tanggal terbit (issue_day), sehingga hari yang terlewat tersusul.
     * Dengan $period (YYYY-MM, mis. pengisian ulang data lama): tanpa batas tanggal terbit.
     */
    public function __invoke(?string $period = null): GenerationResult
    {
        $tenant = $this->currentTenant();
        $settings = BillingSetting::current();
        $today = $tenant->today();
        $result = new GenerationResult;

        $automatic = $period === null;
        $period ??= $today->format('Y-m');

        if ($automatic && $today->day < $settings->issue_day) {
            return $result;
        }

        $monthStart = Dates::month($period, $tenant->timezone);

        if ($monthStart === null) {
            throw new ValueError('Periode harus berformat YYYY-MM.');
        }

        $monthStart = $monthStart->startOfMonth()->startOfDay();
        $monthEnd = $monthStart->endOfMonth()->startOfDay();
        $isCurrentMonth = $period === $today->format('Y-m');

        Lease::query()
            ->active()
            ->where('start_date', '<=', $monthEnd->toDateString())
            ->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', $monthStart->toDateString()))
            ->with('room:id,number')
            ->chunkById(100, function ($leases) use ($settings, $period, $monthStart, $today, $isCurrentMonth, $result) {
                foreach ($leases as $lease) {
                    try {
                        $invoice = $this->issueFor($lease, $settings, $period, $monthStart, $today, $isCurrentMonth);
                    } catch (Throwable $e) {
                        // Satu kontrak gagal tidak boleh menghentikan yang lain. Hanya id dan kelas galat yang dicatat:
                        // pesan galat database memuat nilai binding (nama penghuni, catatan).
                        Log::error('Penerbitan tagihan gagal untuk satu kontrak.', ['exception' => $e::class, 'lease_id' => $lease->getKey()]);
                        $result->failed++;
                        $result->failures[] = ['lease_id' => $lease->getKey(), 'error' => $e::class];

                        continue;
                    }

                    if ($invoice === null) {
                        $result->skipped++;

                        continue;
                    }

                    $result->issued++;
                    event(new InvoiceIssued($invoice->getKey(), $invoice->tenant_id));
                }
            });

        return $result;
    }

    private function issueFor(
        Lease $lease,
        BillingSetting $settings,
        string $period,
        CarbonImmutable $monthStart,
        CarbonImmutable $today,
        bool $isCurrentMonth,
    ): ?Invoice {
        $range = InvoiceMath::billableRange(
            CarbonImmutable::parse($lease->start_date->toDateString()),
            $lease->end_date ? CarbonImmutable::parse($lease->end_date->toDateString()) : null,
            CarbonImmutable::parse($monthStart->toDateString()),
            $settings->prorate_first_month,
            $settings->prorate_last_month,
        );

        if ($range === null) {
            return null;
        }

        if ($this->predecessorAlreadyBilled($lease, $period, $range['start']->toDateString())) {
            return null;
        }

        $amount = InvoiceMath::prorate($lease->rent_amount, $range['days'], $range['days_in_month']);
        $dueDay = $lease->due_day ?? $settings->due_day;
        $due = $monthStart->day(min($dueDay, $monthStart->daysInMonth));

        // Tagihan yang baru terbit pada bulan berjalan tidak boleh lahir sudah terlambat.
        if ($isCurrentMonth && $due->lt($today)) {
            $due = $today;
        }

        $label = $monthStart->locale('id')->translatedFormat('F Y');
        $description = 'Sewa kamar '.$lease->room->number.' - '.$label
            .($range['prorated'] ? " (pro rata {$range['days']} dari {$range['days_in_month']} hari)" : '');

        try {
            return DB::transaction(function () use ($lease, $settings, $period, $range, $amount, $due, $today, $description) {
                if (Invoice::query()->where('lease_id', $lease->getKey())->where('period', $period)->exists()) {
                    return null;
                }

                $invoice = Invoice::create([
                    'property_id' => $lease->property_id,
                    'lease_id' => $lease->getKey(),
                    'resident_id' => $lease->primary_resident_id,
                    'number' => ($this->numbers)('invoice', $settings->invoice_prefix, $today),
                    'type' => InvoiceType::Recurring,
                    'period' => $period,
                    'period_start' => $range['start']->toDateString(),
                    'period_end' => $range['end']->toDateString(),
                    'issue_date' => $today->toDateString(),
                    'due_date' => $due->toDateString(),
                    'status' => InvoiceStatus::Issued,
                    'issued_at' => now(),
                    'created_by' => null,
                ]);

                InvoiceItem::create([
                    'invoice_id' => $invoice->getKey(),
                    'type' => InvoiceItemType::Rent,
                    'description' => $description,
                    'quantity' => '1.00',
                    'unit_price' => $amount,
                    'amount' => $amount,
                    'sort_order' => 0,
                ]);

                return ($this->recalculate)($invoice);
            });
        } catch (UniqueConstraintViolationException $e) {
            // Dua proses menerbitkan kontrak dan periode yang sama: transaksi (dan nomornya) batal, lewati.
            if (str_contains($e->getMessage(), 'invoices_lease_id_period_unique')) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Bulan perpanjangan: bila tagihan kontrak sebelumnya untuk periode ini sudah mencakup tanggal mulai
     * tagih kontrak ini, jangan tagih lagi (menghindari tagihan ganda pada bulan yang sama).
     */
    private function predecessorAlreadyBilled(Lease $lease, string $period, string $rangeStart): bool
    {
        $ancestorId = $lease->renewed_from_lease_id;

        for ($depth = 0; $ancestorId !== null && $depth < 5; $depth++) {
            $billed = Invoice::query()
                ->where('lease_id', $ancestorId)
                ->where('period', $period)
                ->where('status', '!=', InvoiceStatus::Void->value)
                ->first(['period_end']);

            if ($billed !== null && $billed->period_end !== null && $billed->period_end->toDateString() >= $rangeStart) {
                return true;
            }

            $ancestorId = Lease::query()->whereKey($ancestorId)->value('renewed_from_lease_id');
        }

        return false;
    }
}
