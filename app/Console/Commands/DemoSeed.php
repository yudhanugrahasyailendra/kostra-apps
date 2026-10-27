<?php

namespace App\Console\Commands;

use App\Actions\Auth\RegisterOwner;
use App\Actions\Billing\GenerateMonthlyInvoices;
use App\Actions\Complaints\CreateComplaint;
use App\Actions\Finance\SaveExpense;
use App\Actions\Leases\CheckInResident;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\SavePaymentAccount;
use App\Actions\Subscriptions\SetTenantPlan;
use App\Enums\BillingPeriod;
use App\Enums\InvoiceStatus;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Resident;
use App\Models\Room;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoSeed extends Command
{
    public const OWNER_EMAIL = 'demo@kostra.test';

    protected $signature = 'demo:seed {--force : Izinkan di lingkungan production} {--password= : Kata sandi pemilik demo (bawaan: acak)}';

    protected $description = 'Membuat satu tenant demo dengan data realistis (kamar, penghuni, tagihan 3 bulan, pembayaran, pengeluaran, keluhan). Pesan WhatsApp memakai penyedia palsu.';

    private const NAMES = ['Rizky Pratama', 'Dewi Lestari', 'Andi Mappaseng', 'Siti Nurhaliza', 'Budi Santoso', 'Rina Kartika', 'Agus Salim', 'Maya Putri', 'Fajar Nugraha', 'Nur Aisyah'];

    public function handle(TenantContext $context): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('demo:seed menolak berjalan di production tanpa --force.');

            return self::FAILURE;
        }

        // Pemeriksaan lintas tenant: email pemilik unik untuk seluruh platform.
        if ($context->withoutTenant(fn () => User::withTrashed()->where('email', self::OWNER_EMAIL)->exists())) {
            $this->error('Tenant demo sudah ada ('.self::OWNER_EMAIL.'). Tidak ada yang diubah.');

            return self::FAILURE;
        }

        if (! Plan::query()->where('key', 'business')->exists()) {
            $this->error('Paket belum ada. Jalankan: php artisan db:seed --class=PlanSeeder');

            return self::FAILURE;
        }

        // Tidak pernah mengirim pesan sungguhan dari data demo.
        config(['kostra.whatsapp.provider' => 'fake']);

        $password = (string) ($this->option('password') ?: bin2hex(random_bytes(6)));

        DB::transaction(function () use ($context, $password) {
            $owner = app(RegisterOwner::class)([
                'name' => 'Pemilik Demo', 'business_name' => 'Kos Demo Melati',
                'email' => self::OWNER_EMAIL, 'phone' => null, 'password' => $password,
            ]);
            $tenant = $owner->tenant;

            app(SetTenantPlan::class)($tenant, Plan::query()->where('key', 'business')->firstOrFail(), BillingPeriod::Monthly);

            $context->run($tenant, function () use ($tenant, $owner) {
                $this->seedTenant($tenant, $owner);
            });
        });

        $this->info('Tenant demo dibuat.');
        $this->line('Masuk: '.self::OWNER_EMAIL.' / '.$password);

        return self::SUCCESS;
    }

    private function seedTenant($tenant, User $owner): void
    {
        $today = $tenant->today();

        $property = Property::create(['name' => 'Kos Melati', 'address' => 'Jl. Contoh No. 1', 'city' => 'Makassar', 'floors' => 2, 'is_active' => true]);

        $rooms = collect(range(1, 12))->map(fn (int $n) => Room::create([
            'property_id' => $property->id, 'number' => (string) (100 + $n), 'floor' => $n <= 6 ? 1 : 2,
            'price' => $n <= 6 ? 1_200_000 : 1_500_000, 'status' => 'vacant',
        ]));

        $residents = collect(self::NAMES)->map(fn (string $name, int $i) => Resident::create([
            'name' => $name, 'phone' => '+62812000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'occupation' => $i % 2 ? 'Karyawan' : 'Mahasiswa', 'consented_at' => now(), 'is_active' => true,
        ]));

        $start = $today->startOfMonth()->subMonths(3)->toDateString();
        foreach ($residents as $i => $resident) {
            app(CheckInResident::class)([
                'room_id' => $rooms[$i]->id, 'primary_resident_id' => $resident->id,
                'start_date' => $start, 'end_date' => $today->addMonths(9)->toDateString(),
                'rent_amount' => $rooms[$i]->price, 'deposit_amount' => 500_000, 'deposit_received_on' => $start,
            ]);
        }

        $generate = app(GenerateMonthlyInvoices::class);
        foreach ([3, 2, 1, 0] as $back) {
            $generate($today->startOfMonth()->subMonths($back)->format('Y-m'));
        }

        // Bulan-bulan lalu lunas untuk sebagian besar penghuni; bulan berjalan sebagian saja.
        $paid = 0;
        Invoice::query()->where('status', '!=', InvoiceStatus::Void->value)->orderBy('id')->get()->each(function (Invoice $invoice, int $i) use ($owner, $today, &$paid) {
            $isCurrent = $invoice->period === $today->format('Y-m');

            if ($i % 5 === 4 || ($isCurrent && $i % 2 === 0)) {
                return; // sengaja menunggak / belum bayar
            }

            app(RecordPayment::class)([
                'resident_id' => $invoice->resident_id, 'paid_on' => $today->toDateString(), 'method' => $i % 2 ? 'transfer' : 'cash',
                'allocations' => [['invoice_id' => $invoice->id, 'amount' => $invoice->total - $invoice->paid_total]],
            ], $owner);
            $paid++;
        });

        $category = ExpenseCategory::query()->orderBy('id')->firstOrFail();
        foreach ([850_000, 920_000, 780_000] as $n => $amount) {
            app(SaveExpense::class)([
                'property_id' => $property->id, 'expense_category_id' => $category->id, 'amount' => $amount,
                'spent_on' => $today->startOfMonth()->subMonths($n)->addDays(4)->toDateString(), 'description' => 'Tagihan bulanan',
            ], $owner);
        }

        app(SavePaymentAccount::class)(['type' => 'bank', 'bank_name' => 'BCA', 'account_number' => '1234567890', 'account_name' => 'Pemilik Demo', 'is_default' => true, 'is_active' => true]);

        app(CreateComplaint::class)([
            'resident_id' => $residents[0]->id, 'category' => 'water', 'title' => 'Keran kamar mandi bocor', 'description' => 'Bocor sejak kemarin.',
        ], null, $owner);

        $this->line("Data: {$rooms->count()} kamar, {$residents->count()} penghuni, {$paid} pembayaran.");
    }
}
