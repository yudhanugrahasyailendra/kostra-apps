<?php

namespace App\Services\Demo;

use App\Actions\CheckInResident;
use App\Actions\CreateComplaint;
use App\Actions\CreateProperty;
use App\Actions\CreateRoomsInBulk;
use App\Actions\GenerateMonthlyInvoices;
use App\Actions\RecordPayment;
use App\Actions\RegisterOwner;
use App\Actions\SaveExpense;
use App\Actions\SavePaymentAccount;
use App\Actions\StartSubscriptionTrial;
use App\Enums\ComplaintCategory;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentAccountType;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionPeriod;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Plan;
use App\Models\Resident;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Membuat tenant demo (dan data uji performa) lewat Action yang sama dengan aplikasi, jadi semua aturan
 * uang, nomor dokumen, dan alokasi pembayaran ikut berlaku. Data sepenuhnya rekaan: nama dari daftar tetap,
 * telepon berpola 0899-0000-xxxx. Tidak memakai Faker karena paket itu hanya ada di dev.
 *
 * Penyedia WhatsApp harus `fake`: pembuatan tagihan dan pembayaran masa lalu mengantrekan pesan, dan data
 * demo tidak boleh pernah sampai ke nomor sungguhan.
 */
class DemoTenantBuilder
{
    private const FIRST = ['Andi', 'Budi', 'Citra', 'Dewi', 'Eko', 'Fitri', 'Gita', 'Hendra', 'Indah', 'Joko', 'Kiki', 'Lina', 'Made', 'Nanda', 'Oki', 'Putri', 'Rian', 'Sari', 'Tono', 'Wulan'];

    private const LAST = ['Pratama', 'Saputra', 'Wijaya', 'Lestari', 'Hidayat', 'Kusuma', 'Santoso', 'Rahayu', 'Nugroho', 'Permata'];

    private const JOBS = ['Mahasiswa', 'Karyawan swasta', 'Perawat', 'Guru', 'Wiraswasta', 'Staf bank'];

    public function __construct(
        private RegisterOwner $register,
        private StartSubscriptionTrial $trial,
        private CreateProperty $createProperty,
        private CreateRoomsInBulk $createRooms,
        private SavePaymentAccount $saveAccount,
        private CheckInResident $checkIn,
        private GenerateMonthlyInvoices $generate,
        private RecordPayment $recordPayment,
        private SaveExpense $saveExpense,
        private CreateComplaint $createComplaint,
    ) {}

    /**
     * @param  array{rooms?: int, months?: int, email: string, password: string, kos_name?: string, occupancy?: int}  $options
     * @return array{tenant: Tenant, owner: User, rooms: int, leases: int, invoices: int, payments: int}
     */
    public function build(array $options): array
    {
        if (config('kostra.whatsapp.provider') !== 'fake') {
            throw new RuntimeException('Data demo hanya boleh dibuat saat KOSTRA_WHATSAPP_PROVIDER=fake.');
        }

        $rooms = max(1, min(300, (int) ($options['rooms'] ?? 30)));
        $months = max(1, min(24, (int) ($options['months'] ?? 6)));
        $occupancy = max(0, min(100, (int) ($options['occupancy'] ?? 85)));
        $today = CarbonImmutable::now(config('kostra.display_timezone'))->startOfDay();

        $owner = $this->register->handle([
            'kos_name' => $options['kos_name'] ?? 'Kos Demo Kostra',
            'name' => 'Pemilik Demo',
            'email' => $options['email'],
            'password' => $options['password'],
        ]);

        $tenant = Tenant::query()->findOrFail($owner->tenant_id);

        // Paket Pro masa percobaan: batas properti dan kamar cukup untuk demo dan tagihan otomatis aktif.
        $this->trial->handle($tenant, Plan::query()->where('key', 'pro')->firstOrFail(), SubscriptionPeriod::Monthly);
        $tenant->refresh();

        return TenantContext::instance()->runAs($tenant, function () use ($tenant, $owner, $rooms, $months, $occupancy, $today) {
            $property = $this->createProperty->handle([
                'name' => 'Kos Demo Kostra',
                'address' => 'Jl. Contoh No. 1',
                'city' => 'Makassar',
                'floors' => max(1, (int) ceil($rooms / 20)),
                'is_active' => true,
            ]);

            // Pro dibatasi 100 kamar per tenant: sisanya hanya untuk uji performa dengan batas yang dilonggarkan pemanggil.
            $created = 0;
            for ($start = 1; $created < $rooms; $start += 100) {
                $count = min(100, $rooms - $created);
                $this->createRooms->handle([
                    'property_id' => $property->id,
                    'start' => $start,
                    'end' => $start + $count - 1,
                    'prefix' => 'A',
                    'floor' => 1,
                    'price' => 1_500_000,
                ]);
                $created += $count;
            }

            $this->saveAccount->handle([
                'type' => PaymentAccountType::Bank,
                'bank_name' => 'Bank Contoh',
                'account_number' => '0000000000',
                'account_name' => 'Kos Demo Kostra',
                'is_default' => true,
            ]);

            $leaseCount = $this->checkInResidents($property->id, $rooms, $occupancy, $today->subMonths($months - 1)->startOfMonth());
            [$invoiceCount, $paymentCount] = $this->billHistory($tenant, $owner, $months, $today);
            $this->addExpensesAndComplaints($owner, $property->id, $months, $today);

            return [
                'tenant' => $tenant,
                'owner' => $owner,
                'rooms' => $rooms,
                'leases' => $leaseCount,
                'invoices' => $invoiceCount,
                'payments' => $paymentCount,
            ];
        });
    }

    private function checkInResidents(int $propertyId, int $rooms, int $occupancy, CarbonImmutable $historyStart): int
    {
        $occupied = (int) round($rooms * $occupancy / 100);
        $leases = 0;

        Room::query()->where('property_id', $propertyId)->orderBy('id')->limit($occupied)->get()->each(function (Room $room, int $i) use ($historyStart, &$leases) {
            $resident = Resident::create([
                'name' => self::FIRST[$i % count(self::FIRST)].' '.self::LAST[intdiv($i, count(self::FIRST)) % count(self::LAST)],
                'phone' => '+628990000'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'occupation' => self::JOBS[$i % count(self::JOBS)],
                'consented_at' => now(),
                'is_active' => true,
            ]);

            // Sebagian penghuni masuk di tengah riwayat supaya ada pro rata dan kontrak berbeda umur.
            $this->checkIn->handle([
                'room_id' => $room->id,
                'primary_resident_id' => $resident->id,
                'start_date' => $historyStart->addMonths($i % 3 === 0 ? 1 : 0)->toDateString(),
                'duration_months' => null,
                'deposit_amount' => 500_000,
                'deposit_received_at' => $historyStart->toDateString(),
            ]);
            $leases++;
        });

        return $leases;
    }

    /** @return array{0: int, 1: int} jumlah tagihan dan pembayaran */
    private function billHistory(Tenant $tenant, User $owner, int $months, CarbonImmutable $today): array
    {
        $invoices = 0;
        $payments = 0;

        for ($back = $months - 1; $back >= 0; $back--) {
            $month = $today->subMonths($back)->startOfMonth();
            $issueDay = $back === 0 ? $today : $month->setDay(min(1, $month->daysInMonth));

            $this->generate->handle($tenant, $issueDay);

            // Bulan lama hampir lunas; bulan berjalan baru sebagian, sehingga dashboard punya tunggakan nyata.
            $paidShare = match (true) {
                $back === 0 => 40,
                $back === 1 => 85,
                default => 95,
            };

            Invoice::query()
                ->where('period', $month->format('Y-m'))
                ->whereIn('status', InvoiceStatus::open())
                ->with('resident')
                ->orderBy('id')
                ->get()
                ->each(function (Invoice $invoice, int $i) use ($owner, $month, $today, $paidShare, &$payments) {
                    if (($i * 37) % 100 >= $paidShare) {
                        return;
                    }

                    $paidOn = $month->addDays(min(3 + $i % 5, 27));
                    $this->recordPayment->handle(
                        $invoice->resident,
                        (int) $invoice->total,
                        $paidOn->gt($today) ? $today : $paidOn,
                        $i % 3 === 0 ? PaymentMethod::Cash : PaymentMethod::Transfer,
                        [$invoice->id => (int) $invoice->total],
                        $owner,
                    );
                    $payments++;
                });

            $invoices += Invoice::query()->where('period', $month->format('Y-m'))->count();
        }

        return [$invoices, $payments];
    }

    private function addExpensesAndComplaints(User $owner, int $propertyId, int $months, CarbonImmutable $today): void
    {
        $categories = ExpenseCategory::query()->orderBy('sort_order')->limit(3)->pluck('id');

        for ($back = 0; $back < min($months, 6); $back++) {
            foreach ($categories as $i => $categoryId) {
                $this->saveExpense->handle([
                    'expense_category_id' => $categoryId,
                    'property_id' => $propertyId,
                    'amount' => 250_000 + $i * 150_000,
                    'spent_on' => $today->subMonths($back)->startOfMonth()->addDays(10 + $i)->toDateString(),
                    'description' => 'Pengeluaran rutin bulanan',
                ], $owner);
            }
        }

        $leases = Lease::query()->active()->where('property_id', $propertyId)->orderBy('id')->limit(3)->get();

        foreach ($leases as $i => $lease) {
            $this->createComplaint->handle([
                'room_id' => $lease->room_id,
                'resident_id' => $lease->primary_resident_id,
                'category' => [ComplaintCategory::Water, ComplaintCategory::RoomFacility, ComplaintCategory::Cleaning][$i]->value,
                'title' => ['Air tidak mengalir', 'Lampu kamar mati', 'Kebersihan lorong'][$i],
                'description' => 'Mohon ditindaklanjuti. Data contoh untuk demo.',
            ], $owner);
        }
    }
}
