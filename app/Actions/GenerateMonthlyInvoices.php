<?php

namespace App\Actions;

use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\Tenant;
use App\Services\Billing\BillingClock;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class GenerateMonthlyInvoices
{
    public function __construct(private RecalculateInvoice $recalculate, private GenerateInvoiceNumber $numbers) {}

    /**
     * Terbitkan tagihan bulan berjalan untuk setiap kontrak aktif milik satu tenant. Aman dijalankan
     * berkali-kali: kontrak yang sudah punya tagihan periode itu dilewati, dan unique (lease_id, period)
     * di database menjadi pengaman terakhir. Kontrak yang baru check-in setelah tanggal terbit
     * mendapat tagihannya pada putaran berikutnya. Tagihan Rp 0 tidak diterbitkan.
     *
     * @return array{period: string, created: int, skipped: int}
     */
    public function handle(Tenant $tenant, ?CarbonInterface $today = null): array
    {
        return TenantContext::instance()->runAs($tenant, function () use ($tenant, $today) {
            $today = CarbonImmutable::parse(($today ?? BillingClock::today($tenant))->toDateString());
            $period = $today->format('Y-m');
            $result = ['period' => $period, 'created' => 0, 'skipped' => 0];

            $settings = BillingSetting::current();
            $monthStart = $today->startOfMonth();
            $monthEnd = $today->endOfMonth()->startOfDay();

            // Hari terbit yang lebih besar dari panjang bulan dimundurkan ke akhir bulan.
            if ($today->day < min($settings->issue_day, $today->daysInMonth)) {
                return $result;
            }

            Lease::query()
                ->active()
                ->whereDate('start_date', '<=', $monthEnd->toDateString())
                ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $monthStart->toDateString()))
                ->whereDoesntHave('invoices', fn ($q) => $q->where('period', $period))
                ->with('room')
                // chunkById (bukan chunk/each): baris yang selesai keluar dari filter, offset akan melompati kontrak.
                ->chunkById(100, function ($leases) use ($settings, $today, $period, $monthStart, $monthEnd, &$result) {
                    foreach ($leases as $lease) {
                        try {
                            $created = DB::transaction(fn () => $this->issueFor($lease, $settings, $today, $period, $monthStart, $monthEnd));
                        } catch (UniqueConstraintViolationException) {
                            // Proses lain menerbitkan tagihan kontrak ini lebih dulu: bukan galat.
                            $created = false;
                        }

                        $created ? $result['created']++ : $result['skipped']++;
                    }
                });

            return $result;
        });
    }

    private function issueFor(Lease $lease, BillingSetting $settings, CarbonImmutable $today, string $period, CarbonImmutable $monthStart, CarbonImmutable $monthEnd): bool
    {
        [$from, $to, $days] = $this->billedRange($lease, $settings, $monthStart, $monthEnd);
        $amount = self::prorate($lease->rent_amount, $days, $monthStart->daysInMonth);

        if ($amount === 0) {
            return false;
        }

        $dueDay = min($lease->due_day ?? $settings->due_day, $monthStart->daysInMonth);
        $due = $monthStart->setDay($dueDay);

        $invoice = Invoice::create([
            'property_id' => $lease->property_id,
            'lease_id' => $lease->id,
            'resident_id' => $lease->primary_resident_id,
            'number' => $this->numbers->handle($period, $settings->invoice_prefix),
            'type' => InvoiceType::Recurring,
            'period' => $period,
            'period_start' => $from->toDateString(),
            'period_end' => $to->toDateString(),
            'issue_date' => $today->toDateString(),
            // Jatuh tempo tidak pernah sebelum tanggal terbit.
            'due_date' => ($due->lt($today) ? $today : $due)->toDateString(),
            'status' => InvoiceStatus::Issued,
            'issued_at' => now(),
        ]);

        $label = 'Sewa Kamar '.$lease->room->number.' - '.$monthStart->locale('id')->isoFormat('MMMM Y');

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceItemType::Rent,
            'description' => $days < $monthStart->daysInMonth ? "$label (pro rata $days hari)" : $label,
            'quantity' => 1,
            'unit_price' => $amount,
            'amount' => $amount,
            'sort_order' => 0,
        ]);

        $this->recalculate->handle($invoice);

        return true;
    }

    /**
     * Rentang tanggal yang ditagih dan jumlah harinya. Pro rata hanya berlaku bila diaktifkan di
     * pengaturan; bila mati, bulan pertama dan terakhir ditagih penuh.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: int}
     */
    private function billedRange(Lease $lease, BillingSetting $settings, CarbonImmutable $monthStart, CarbonImmutable $monthEnd): array
    {
        $from = $monthStart;
        $to = $monthEnd;

        $leaseStart = CarbonImmutable::parse($lease->start_date->toDateString());
        if ($settings->prorate_first_month && $leaseStart->gt($monthStart)) {
            $from = $leaseStart;
        }

        if ($settings->prorate_last_month && $lease->end_date !== null) {
            $leaseEnd = CarbonImmutable::parse($lease->end_date->toDateString());
            if ($leaseEnd->lt($monthEnd)) {
                $to = $leaseEnd;
            }
        }

        return [$from, $to, (int) $from->diffInDays($to) + 1];
    }

    /** rent x hari / hari dalam bulan, dibulatkan ke rupiah terdekat (setengah naik), tanpa float. */
    public static function prorate(int $rent, int $days, int $daysInMonth): int
    {
        if ($days >= $daysInMonth) {
            return $rent;
        }

        return intdiv($rent * $days * 2 + $daysInMonth, 2 * $daysInMonth);
    }
}
