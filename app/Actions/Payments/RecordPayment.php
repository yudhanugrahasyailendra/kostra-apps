<?php

namespace App\Actions\Payments;

use App\Actions\Billing\GenerateInvoiceNumber;
use App\Actions\Billing\RecalculateInvoice;
use App\Actions\Concerns\ResolvesTenant;
use App\Contracts\InvoicePaidTotal;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\PaymentRecorded;
use App\Models\Invoice;
use App\Models\LeaseResident;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentAllocation;
use App\Models\Resident;
use App\Models\User;
use App\Support\Dates;
use App\Support\Rupiah;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class RecordPayment
{
    use ResolvesTenant;

    public const MAX_ALLOCATIONS = 30;

    public const MAX_AMOUNT = 10_000_000_000;

    public function __construct(
        private readonly GenerateInvoiceNumber $numbers,
        private readonly RecalculateInvoice $recalculate,
        private readonly InvoicePaidTotal $paidTotal,
    ) {}

    /**
     * Mencatat uang yang diterima pemilik di luar sistem (Kostra tidak menahan dana). Satu transaksi:
     * kunci tagihan terkait, buat pembayaran, alokasi, nomor kuitansi, lalu hitung ulang tagihan.
     *
     * Aturan uang: jumlah pembayaran = jumlah seluruh alokasinya (kelebihan bayar ditolak, tidak ada saldo
     * penghuni); alokasi tidak melebihi sisa tagihan; hanya tagihan terbuka yang bisa dialokasikan.
     *
     * @param  array<string, mixed>  $data  resident_id, paid_on (Y-m-d), method, allocations: list<{invoice_id, amount}>,
     *                                      [payment_account_id, reference, notes]
     */
    public function __invoke(array $data, User $actor): Payment
    {
        $payment = DB::transaction(fn () => $this->persist($data, $actor));

        event(new PaymentRecorded($payment->getKey(), $payment->tenant_id));

        return $payment;
    }

    /**
     * Isi pencatatan tanpa transaksi dan tanpa event; hanya untuk dipanggil dari dalam DB::transaction
     * (mis. persetujuan bukti transfer, yang mengirim event setelah transaksinya sendiri selesai).
     *
     * @param  array<string, mixed>  $data
     */
    public function persist(array $data, User $actor): Payment
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Pembayaran harus dicatat di dalam DB::transaction.');
        }

        $tenant = $this->currentTenant();
        $today = $tenant->today();

        $paidOn = Dates::date($data['paid_on'] ?? null, $tenant->timezone);

        if ($paidOn === null) {
            throw ValidationException::withMessages(['paid_on' => 'Format tanggal harus YYYY-MM-DD.']);
        }

        if ($paidOn->gt($today) || $paidOn->lt($today->subYear())) {
            throw ValidationException::withMessages(['paid_on' => 'Tanggal bayar tidak boleh di masa depan dan paling lama 1 tahun ke belakang.']);
        }

        $method = PaymentMethod::tryFrom((string) ($data['method'] ?? ''));

        if ($method === null) {
            throw ValidationException::withMessages(['method' => 'Metode pembayaran tidak valid.']);
        }

        $allocations = $this->normalizeAllocations($data['allocations'] ?? []);
        $total = array_sum(array_column($allocations, 'amount'));

        if ($total > self::MAX_AMOUNT) {
            throw ValidationException::withMessages(['allocations' => 'Jumlah pembayaran terlalu besar.']);
        }

        $account = $this->resolveAccount($method, $data['payment_account_id'] ?? null);
        $payer = Resident::query()->find($data['resident_id'] ?? 0)
            ?? throw ValidationException::withMessages(['resident_id' => 'Penghuni tidak ditemukan.']);

        $invoices = $this->lockInvoices(array_column($allocations, 'invoice_id'));

        if ($invoices->count() !== count($allocations)) {
            throw ValidationException::withMessages(['allocations' => 'Ada tagihan yang tidak ditemukan.']);
        }

        foreach ($allocations as ['invoice_id' => $invoiceId, 'amount' => $amount]) {
            $this->assertCanReceive($invoices[$invoiceId], $payer, $amount);
        }

        $payment = Payment::create([
            'resident_id' => $payer->getKey(),
            'payment_account_id' => $account?->getKey(),
            'amount' => $total,
            'paid_on' => $paidOn->toDateString(),
            'method' => $method,
            'reference' => $this->clean($data['reference'] ?? null, 100),
            'status' => PaymentStatus::Active,
            'recorded_by' => $actor->getKey(),
            'receipt_number' => ($this->numbers)('receipt', (string) config('kostra.billing.receipt_prefix'), $today),
            'receipt_issued_at' => now(),
            'notes' => $this->clean($data['notes'] ?? null, 2000),
        ]);

        foreach ($allocations as ['invoice_id' => $invoiceId, 'amount' => $amount]) {
            PaymentAllocation::create([
                'payment_id' => $payment->getKey(),
                'invoice_id' => $invoiceId,
                'amount' => $amount,
            ]);
        }

        foreach ($invoices as $invoice) {
            ($this->recalculate)($invoice);
        }

        return $payment;
    }

    /**
     * Mengunci baris tagihan (SELECT ... FOR UPDATE, urut id) sampai transaksi selesai. Penguncian harus terjadi
     * SEBELUM sisa tagihan dihitung; bila tidak, dua pencatatan bersamaan bisa sama-sama melihat sisa lama
     * dan bersama-sama melewatinya. Dipisah supaya bisa diuji sendiri.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Invoice>
     */
    public function lockInvoices(array $ids): Collection
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Tagihan hanya boleh dikunci di dalam DB::transaction.');
        }

        return Invoice::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    private function assertCanReceive(Invoice $invoice, Resident $payer, int $amount): void
    {
        $key = "allocations.{$invoice->getKey()}";

        if (! in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::Partial, InvoiceStatus::Overdue], true)) {
            throw ValidationException::withMessages([$key => "Tagihan {$invoice->number} berstatus {$invoice->status->label()} dan tidak bisa menerima pembayaran."]);
        }

        $isMember = LeaseResident::query()
            ->where('lease_id', $invoice->lease_id)
            ->where('resident_id', $payer->getKey())
            ->exists();

        if (! $isMember) {
            throw ValidationException::withMessages([$key => "Penghuni ini bukan penghuni kontrak tagihan {$invoice->number}."]);
        }

        // Sisa dihitung segar dari alokasi aktif, di bawah kunci baris tagihan.
        $remaining = $invoice->total - $this->paidTotal->forInvoice($invoice);

        if ($amount > $remaining) {
            throw ValidationException::withMessages([$key => "Pembayaran untuk {$invoice->number} melebihi sisa tagihan (".Rupiah::format(max(0, $remaining)).'). Kelebihan bayar tidak diterima.']);
        }
    }

    /**
     * @return list<array{invoice_id: int, amount: int}>
     */
    private function normalizeAllocations(mixed $raw): array
    {
        if (! is_array($raw) || $raw === [] || count($raw) > self::MAX_ALLOCATIONS) {
            throw ValidationException::withMessages(['allocations' => 'Isi pembayaran untuk 1 sampai '.self::MAX_ALLOCATIONS.' tagihan.']);
        }

        $seen = [];
        $list = [];

        foreach ($raw as $row) {
            $invoiceId = $row['invoice_id'] ?? null;
            $amount = $row['amount'] ?? null;

            if (! is_int($invoiceId) || ! is_int($amount) || $amount < 1) {
                throw ValidationException::withMessages(['allocations' => 'Setiap alokasi harus berisi tagihan dan nominal rupiah bulat lebih dari 0.']);
            }

            if (isset($seen[$invoiceId])) {
                throw ValidationException::withMessages(['allocations' => 'Satu tagihan hanya boleh dialokasikan satu kali per pembayaran.']);
            }

            $seen[$invoiceId] = true;
            $list[] = ['invoice_id' => $invoiceId, 'amount' => $amount];
        }

        return $list;
    }

    private function resolveAccount(PaymentMethod $method, mixed $accountId): ?PaymentAccount
    {
        if ($accountId === null || $accountId === '') {
            return null;
        }

        if ($method === PaymentMethod::Cash) {
            throw ValidationException::withMessages(['payment_account_id' => 'Pembayaran tunai tidak memakai rekening.']);
        }

        $account = PaymentAccount::query()->find($accountId);

        if ($account === null || ! $account->is_active) {
            throw ValidationException::withMessages(['payment_account_id' => 'Rekening tidak ditemukan atau tidak aktif.']);
        }

        return $account;
    }

    private function clean(mixed $value, int $max): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
