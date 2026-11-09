<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentAllocation;
use App\Models\Resident;
use App\Models\User;
use App\Services\Billing\BillingClock;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordPayment
{
    public function __construct(
        private RecalculateInvoice $recalculate,
        private GenerateReceiptNumber $receiptNumber,
    ) {}

    /**
     * Catat uang yang sudah diterima di luar sistem dan alokasikan ke satu atau beberapa tagihan.
     * Semuanya dalam satu transaksi: baris tagihan dikunci (urut id, supaya dua pencatatan
     * bersamaan tidak saling kunci), alokasi diperiksa terhadap sisa tagihan yang terkunci, lalu
     * paid_total dan status dihitung ulang. Kelebihan bayar ditolak, jadi jumlah alokasi harus sama
     * dengan jumlah pembayaran.
     *
     * @param  array<int, int>  $allocations  invoice_id => rupiah yang dialokasikan
     */
    public function handle(
        Resident $resident,
        int $amount,
        CarbonInterface $paidOn,
        PaymentMethod $method,
        array $allocations,
        User $by,
        ?int $paymentAccountId = null,
        ?string $reference = null,
        ?string $notes = null,
    ): Payment {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Jumlah pembayaran harus lebih dari Rp 0.']);
        }

        if ($allocations === []) {
            throw ValidationException::withMessages(['allocations' => 'Pilih minimal satu tagihan yang dibayar.']);
        }

        foreach ($allocations as $allocated) {
            if (! is_int($allocated) || $allocated <= 0) {
                throw ValidationException::withMessages(['allocations' => 'Jumlah tiap alokasi harus lebih dari Rp 0.']);
            }
        }

        $today = BillingClock::today();

        if ($paidOn->toDateString() > $today->toDateString()) {
            throw ValidationException::withMessages(['paid_on' => 'Tanggal pembayaran tidak boleh di masa depan.']);
        }

        $allocated = array_sum($allocations);

        if ($allocated > $amount) {
            throw ValidationException::withMessages(['amount' => 'Jumlah alokasi melebihi jumlah pembayaran.']);
        }

        if ($allocated < $amount) {
            throw ValidationException::withMessages(['amount' => 'Kelebihan bayar tidak diterima. Jumlah pembayaran harus sama dengan jumlah yang dialokasikan ke tagihan.']);
        }

        if ($paymentAccountId !== null && ! PaymentAccount::query()->active()->whereKey($paymentAccountId)->exists()) {
            throw ValidationException::withMessages(['payment_account_id' => 'Rekening tujuan tidak ditemukan atau tidak aktif.']);
        }

        return DB::transaction(function () use ($resident, $amount, $paidOn, $method, $allocations, $by, $paymentAccountId, $reference, $notes, $today) {
            $ids = array_keys($allocations);
            sort($ids);

            // Hanya tagihan tenant aktif yang terlihat (TenantScope), jadi id milik tenant lain dianggap tidak ada.
            $invoices = Invoice::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            if ($invoices->count() !== count($ids)) {
                throw ValidationException::withMessages(['allocations' => 'Ada tagihan yang tidak ditemukan.']);
            }

            foreach ($invoices as $invoice) {
                if ((int) $invoice->resident_id !== (int) $resident->id) {
                    throw ValidationException::withMessages(['allocations' => 'Tagihan '.$invoice->number.' bukan milik penghuni ini.']);
                }

                if (! in_array($invoice->status, InvoiceStatus::open(), true)) {
                    throw ValidationException::withMessages(['allocations' => 'Tagihan '.$invoice->number.' berstatus '.mb_strtolower($invoice->status->label()).' dan tidak dapat menerima pembayaran.']);
                }

                if ($allocations[$invoice->id] > $invoice->outstanding()) {
                    throw ValidationException::withMessages(['allocations' => 'Alokasi untuk tagihan '.$invoice->number.' melebihi sisa tagihan.']);
                }
            }

            $payment = Payment::create([
                'resident_id' => $resident->id,
                'payment_account_id' => $paymentAccountId,
                'amount' => $amount,
                'paid_on' => $paidOn->toDateString(),
                'method' => $method,
                'reference' => $reference,
                'status' => PaymentStatus::Active,
                'recorded_by' => $by->id,
                'receipt_number' => $this->receiptNumber->handle($today->format('Y-m')),
                'receipt_issued_at' => now(),
                'notes' => $notes,
            ]);

            foreach ($allocations as $invoiceId => $part) {
                PaymentAllocation::create(['payment_id' => $payment->id, 'invoice_id' => $invoiceId, 'amount' => $part]);
            }

            foreach ($invoices as $invoice) {
                $this->recalculate->handle($invoice);
            }

            return $payment;
        });
    }
}
