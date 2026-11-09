<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Tagihan baru terbit (bulanan atau manual). Dikirim setelah transaksi penerbitan berhasil. */
class InvoiceIssued implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Invoice $invoice) {}
}
