<?php

namespace App\Actions;

use App\Enums\SubscriptionInvoiceStatus;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class IssueSubscriptionInvoice
{
    /**
     * Terbitkan tagihan langganan untuk satu periode. Idempoten: periode yang sudah punya tagihan
     * (selain void) mengembalikan tagihan itu, jadi penjadwal aman dijalankan berulang. Paket gratis
     * tidak ditagih. Harus dipanggil di dalam konteks tenant dan transaksi.
     */
    public function handle(Subscription $subscription, CarbonImmutable $periodStart, CarbonImmutable $dueDate): ?SubscriptionInvoice
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('IssueSubscriptionInvoice harus dipanggil di dalam transaksi.');
        }

        $plan = $subscription->plan;

        if ($plan->isFree()) {
            return null;
        }

        $existing = SubscriptionInvoice::query()
            ->where('subscription_id', $subscription->id)
            ->whereDate('period_start', $periodStart->toDateString())
            ->where('status', '!=', SubscriptionInvoiceStatus::Void->value)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $amount = $plan->priceFor($subscription->billing_period);

        if ($amount === null) {
            throw ValidationException::withMessages(['plan' => "Paket {$plan->name} tidak dijual dengan periode {$subscription->billing_period->label()}."]);
        }

        $invoice = new SubscriptionInvoice([
            'subscription_id' => $subscription->id,
            // Nomor sementara; nomor akhir dibentuk dari id baris di bawah (unik global).
            'number' => 'TMP-'.Str::random(20),
            'period_start' => $periodStart->toDateString(),
            'period_end' => $subscription->billing_period->endFrom($periodStart)->toDateString(),
            'amount' => $amount,
            'status' => SubscriptionInvoiceStatus::Pending,
            'due_date' => $dueDate->toDateString(),
        ]);
        $invoice->save();

        // KST-2026-11-000123: bulan periode dan id baris. Id tidak pernah ganda, jadi nomor juga tidak.
        $invoice->number = sprintf('KST-%s-%06d', $periodStart->format('Y-m'), $invoice->id);
        $invoice->save();

        return $invoice;
    }
}
