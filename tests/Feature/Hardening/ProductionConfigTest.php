<?php

namespace Tests\Feature\Hardening;

use App\Logging\RedactPersonalData;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProductionConfigTest extends TestCase
{
    public function test_redaksi_menyamarkan_token_email_dan_telepon(): void
    {
        $token = str_repeat('ab12', 16);
        $text = "Gagal kirim ke +6281234567890 dan 081234567890, email budi@contoh.test, tautan https://kostra.id/p/$token hash $token";

        $out = RedactPersonalData::redact($text);

        $this->assertStringNotContainsString('1234567890', $out);
        $this->assertStringNotContainsString('budi@contoh.test', $out);
        $this->assertStringNotContainsString($token, $out);
        $this->assertStringContainsString('[telepon]', $out);
        $this->assertStringContainsString('/p/[tautan-rahasia]', $out);
    }

    public function test_redaksi_tidak_mengubah_angka_biasa(): void
    {
        $this->assertSame('Tagihan Rp 1850000 untuk 12 kamar, id 4821', RedactPersonalData::redact('Tagihan Rp 1850000 untuk 12 kamar, id 4821'));
    }

    public function test_kanal_log_menyamarkan_pesan_dan_jejak_galat_sql(): void
    {
        $path = storage_path('logs/redact-test.log');
        @unlink($path);
        config(['logging.channels.redact_test' => [
            'driver' => 'single',
            'path' => $path,
            'tap' => [RedactPersonalData::class],
        ]]);

        $exception = new \RuntimeException('SQLSTATE[23000]: Duplicate entry for key (bindings: [+6281299998888, siti@contoh.test])');
        Log::channel('redact_test')->error('Galat '.$exception->getMessage(), ['exception' => $exception]);

        $content = (string) file_get_contents($path);
        @unlink($path);

        $this->assertStringContainsString('SQLSTATE[23000]', $content);
        $this->assertStringNotContainsString('+6281299998888', $content);
        $this->assertStringNotContainsString('siti@contoh.test', $content);
    }

    public function test_cookie_sesi_aman_secara_bawaan_di_production(): void
    {
        $original = $_ENV['APP_ENV'] ?? null;
        $_ENV['APP_ENV'] = 'production';
        putenv('APP_ENV=production');
        unset($_ENV['SESSION_SECURE_COOKIE'], $_SERVER['SESSION_SECURE_COOKIE']);
        putenv('SESSION_SECURE_COOKIE');

        $config = require base_path('config/session.php');

        $this->assertTrue($config['secure']);
        $this->assertTrue($config['http_only']);
        $this->assertSame('lax', $config['same_site']);

        $_ENV['APP_ENV'] = $original ?? 'testing';
        putenv('APP_ENV='.($original ?? 'testing'));
    }

    public function test_halaman_galat_tanpa_debug_tidak_membocorkan_jejak_tumpukan(): void
    {
        config(['app.debug' => false]);
        $this->get('/rute-yang-tidak-ada')->assertNotFound()->assertDontSee('vendor/laravel');
    }
}
