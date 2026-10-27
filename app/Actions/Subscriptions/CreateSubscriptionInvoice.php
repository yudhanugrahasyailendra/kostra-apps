<?php

namespace App\Actions\Subscriptions;

use App\Enums\SubscriptionInvoiceStatus;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateSubscriptionInvoice
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * Tagihan langganan untuk satu periode. Jumlahnya diambil dari harga paket saat tagihan dibuat dan tidak
     * berubah lagi (mengubah harga paket hanya berlaku untuk tagihan berikutnya). Nomor unik se-platform
     * (SUB-YYYYMM-000123) diturunkan dari id baris, jadi tidak butuh penghitung yang dikunci.
     *
     * Dijalankan dalam konteks tenant pemilik langganan.
     */
    public function __invoke(Subscription $subscription, CarbonInterface $periodStart, CarbonInterface $dueDate): SubscriptionInvoice
    {
        $plan = $subscription->plan;
        $period = $subscription->billing_period;

        $amount = match (true) {
            $period->months() === 12 => $plan->price_yearly,
            default => $plan->price_monthly,
        };

        if ($amount === null || $amount <= 0) {
            throw ValidationException::withMessages(['plan' => "Paket {$plan->name} tidak tersedia untuk penagihan {$period->label()}."]);
        }

        $end = $periodStart->copy()->addMonthsNoOverflow($period->months())->subDay();

        return DB::transaction(function () use ($subscription, $periodStart, $end, $dueDate, $amount) {
            $invoice = SubscriptionInvoice::create([
                'subscription_id' => $subscription->getKey(),
                // Sementara (28 karakter, muat di kolom 30); diganti nomor final dari id segera setelah baris ada.
                'number' => 'TMP-'.Str::random(24),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $end->toDateString(),
                'amount' => $amount,
                'status' => SubscriptionInvoiceStatus::Pending,
                'due_date' => $dueDate->toDateString(),
            ]);

            $invoice->update(['number' => sprintf('SUB-%s-%06d', $periodStart->format('Ym'), $invoice->getKey())]);

            return $invoice;
        });
    }
}
