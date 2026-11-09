<?php

namespace App\Actions;

use App\Models\BillingSetting;
use App\Models\NumberSequence;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GenerateInvoiceNumber
{
    public const KEY = 'invoice';

    /**
     * Nomor berikutnya untuk periode `YYYY-MM`, format `INV-2026-10-0001` (prefiks dari billing_settings).
     * Baris urutan dikunci (`lockForUpdate`) di dalam transaksi, jadi nomor tidak ganda dan tidak
     * berlubang. Harus dipanggil di dalam transaksi pemanggil agar nomor ikut dibatalkan bila
     * penerbitan tagihan gagal (tanpa lubang).
     */
    public function handle(string $period, ?string $prefix = null): string
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('GenerateInvoiceNumber harus dipanggil di dalam transaksi.');
        }

        $prefix ??= BillingSetting::current()->invoice_prefix;

        $sequence = $this->lockedSequence($period);
        $sequence->last_number++;
        $sequence->save();

        return sprintf('%s-%s-%04d', $prefix, $period, $sequence->last_number);
    }

    /** Kunci urutan di number_sequences; subkelas untuk dokumen lain menggantinya. */
    protected function key(): string
    {
        return self::KEY;
    }

    private function lockedSequence(string $period): NumberSequence
    {
        $find = fn () => NumberSequence::query()
            ->where('key', $this->key())->where('period', $period)
            ->lockForUpdate()->first();

        if ($sequence = $find()) {
            return $sequence;
        }

        try {
            // Savepoint: bentrok unique tidak boleh merusak transaksi pemanggil.
            return DB::transaction(fn () => NumberSequence::create(['key' => $this->key(), 'period' => $period, 'last_number' => 0]));
        } catch (UniqueConstraintViolationException) {
            // Proses lain membuat barisnya lebih dulu; kunci baris miliknya.
            return $find() ?? throw new RuntimeException('Urutan nomor tidak dapat dikunci.');
        }
    }
}
