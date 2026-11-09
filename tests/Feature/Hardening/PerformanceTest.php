<?php

namespace Tests\Feature\Hardening;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Demo\DemoTenantBuilder;
use Database\Seeders\MessageTemplateSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * NFR-05: dashboard dan daftar utama untuk tenant 100 kamar dengan 12 bulan tagihan dan pembayaran.
 * Batas jumlah query menangkap N+1 (jumlahnya tidak boleh tumbuh bersama jumlah baris); waktu dicatat
 * sebagai batas kasar karena mesin CI berbeda-beda.
 */
#[Group('performance')]
class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = [
        'dashboard' => '/app',
        'kamar' => '/app/kamar',
        'penghuni' => '/app/penghuni',
        'kontrak' => '/app/kontrak',
        'tagihan' => '/app/tagihan',
        'pembayaran' => '/app/pembayaran',
        'pengeluaran' => '/app/pengeluaran',
        'komplain' => '/app/komplain',
        'pesan' => '/app/pesan',
        'log-audit' => '/app/log-audit',
        'laporan' => '/app/laporan',
    ];

    public function test_halaman_utama_tenant_100_kamar_tanpa_n_plus_1_dan_cepat(): void
    {
        $this->seed([PlanSeeder::class, MessageTemplateSeeder::class]);

        $result = app(DemoTenantBuilder::class)->build(['rooms' => 100, 'months' => 12, 'email' => 'perf@kostra.test', 'password' => 'rahasia-perf-123', 'occupancy' => 90]);
        $owner = User::query()->withoutGlobalScopes()->findOrFail($result['owner']->id);
        $this->assertGreaterThan(900, $result['invoices']);

        $report = [];
        foreach (self::PAGES as $name => $uri) {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $start = microtime(true);
            $this->actingAs($owner)->get($uri)->assertOk();
            $ms = (int) ((microtime(true) - $start) * 1000);
            $queries = count(DB::getQueryLog());

            $report[$name] = ['queries' => $queries, 'ms' => $ms];
        }

        fwrite(STDERR, "\n".json_encode($report, JSON_PRETTY_PRINT)."\n");

        foreach ($report as $name => $row) {
            $this->assertLessThan(60, $row['queries'], "Halaman $name memakai {$row['queries']} query pada 100 kamar (dugaan N+1).");
            $this->assertLessThan(2000, $row['ms'], "Halaman $name memakan {$row['ms']} ms (NFR-05: kurang dari 2 detik).");
        }
    }
}
