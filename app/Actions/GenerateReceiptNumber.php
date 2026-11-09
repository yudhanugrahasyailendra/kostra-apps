<?php

namespace App\Actions;

/**
 * Nomor kuitansi `KWT-2026-11-0001` per bulan pencatatan. Mekanisme kunci dan transaksi sama dengan
 * nomor tagihan; hanya kuncinya (`receipt`) dan prefiksnya yang berbeda.
 */
class GenerateReceiptNumber extends GenerateInvoiceNumber
{
    public const RECEIPT_KEY = 'receipt';

    public const PREFIX = 'KWT';

    public function handle(string $period, ?string $prefix = null): string
    {
        return parent::handle($period, $prefix ?? self::PREFIX);
    }

    protected function key(): string
    {
        return self::RECEIPT_KEY;
    }
}
