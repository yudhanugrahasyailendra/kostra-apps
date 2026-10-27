<?php

namespace App\Actions\Billing;

use App\Actions\Concerns\ResolvesTenant;
use App\Models\NumberSequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class GenerateInvoiceNumber
{
    use ResolvesTenant;

    /**
     * Nomor dokumen tanpa celah dan tanpa duplikat: baris number_sequences dikunci (lockForUpdate)
     * dan dinaikkan di dalam transaksi pemanggil. Bila transaksi batal, kenaikan ikut batal,
     * jadi nomor tidak pernah berlubang. Format: PREFIX-YYYY-MM-0001 per tenant dan per bulan.
     */
    public function __invoke(string $key, string $prefix, CarbonInterface $date): string
    {
        $sequence = $this->lockSequence($key, $date);

        $next = $sequence->last_number + 1;
        $sequence->update(['last_number' => $next]);

        return sprintf('%s-%s-%s', $prefix, $date->format('Y-m'), str_pad((string) $next, 4, '0', STR_PAD_LEFT));
    }

    /**
     * Membuat baris urutan bila belum ada, lalu mengunci barisnya (SELECT ... FOR UPDATE) sampai transaksi
     * pemanggil selesai. Penguncian harus terjadi SEBELUM membaca last_number; bila tidak, dua proses
     * bisa membaca nilai yang sama dan menulis nomor kembar. Dipisah supaya bisa diuji sendiri.
     */
    public function lockSequence(string $key, CarbonInterface $date): NumberSequence
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Nomor dokumen harus diminta di dalam DB::transaction bersama dokumennya.');
        }

        $tenant = $this->currentTenant();
        $period = $date->format('Y-m');

        // Baris dibuat bila belum ada. insertOrIgnore aman terhadap dua proses yang membuatnya bersamaan.
        NumberSequence::query()->insertOrIgnore([[
            'tenant_id' => $tenant->getKey(),
            'key' => $key,
            'period' => $period,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]]);

        return NumberSequence::query()
            ->where('key', $key)
            ->where('period', $period)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
