<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Harga memakai batas bawah rentang PRD bagian 1 dan hanya penanda sampai
     * harga final diputuskan. Kuota pesan 0 sampai tarif Meta diverifikasi.
     */
    public function run(): void
    {
        $plans = [
            ['key' => 'free', 'name' => 'Gratis', 'price_monthly' => 0, 'price_yearly' => null, 'features' => ['rooms.manual_billing']],
            ['key' => 'starter', 'name' => 'Starter', 'price_monthly' => 49000, 'price_yearly' => null, 'features' => ['billing.auto', 'whatsapp.reminders', 'reports.basic']],
            ['key' => 'pro', 'name' => 'Pro', 'price_monthly' => 129000, 'price_yearly' => null, 'features' => ['billing.auto', 'whatsapp.reminders', 'reports.basic', 'reports.advanced', 'users.multi']],
            ['key' => 'business', 'name' => 'Business', 'price_monthly' => 299000, 'price_yearly' => null, 'features' => ['billing.auto', 'whatsapp.reminders', 'reports.basic', 'reports.advanced', 'users.multi', 'support.priority', 'export.advanced']],
        ];

        foreach ($plans as $index => $plan) {
            $limits = config('kostra.plans.'.$plan['key']);

            // Tahunan = 10 x bulanan (bayar 10 bulan, pakai 12); Gratis tidak punya harga tahunan.
            $plan['price_yearly'] = $plan['price_monthly'] > 0 ? $plan['price_monthly'] * 10 : null;

            Plan::query()->updateOrCreate(['key' => $plan['key']], $plan + [
                'max_properties' => $limits['max_properties'],
                'max_rooms' => $limits['max_rooms'],
                'max_users' => $plan['key'] === 'free' || $plan['key'] === 'starter' ? 1 : null,
                'monthly_message_quota' => 0,
                'is_active' => true,
                'sort_order' => $index,
            ]);
        }
    }
}
