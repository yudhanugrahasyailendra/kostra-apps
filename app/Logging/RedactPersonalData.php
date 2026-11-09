<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\LogRecord;

/**
 * Tap kanal log: menyamarkan data pribadi pada teks log akhir, termasuk jejak tumpukan dan pesan galat
 * SQL (yang memuat nilai binding). AGENTS.md melarang nama, telepon, dan token portal masuk log.
 *
 * Disasarkan pada pola yang terstruktur: token portal dan hash 64 heksadesimal, email, serta nomor telepon
 * Indonesia. Nama orang tidak bisa dikenali dari pola, jadi kode tetap tidak boleh mencatat nama.
 */
class RedactPersonalData
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getLogger()->getHandlers() as $handler) {
            if ($handler instanceof FormattableHandlerInterface) {
                $handler->setFormatter(new class extends LineFormatter
                {
                    public function __construct()
                    {
                        parent::__construct(null, null, true, true);
                    }

                    public function format(LogRecord $record): string
                    {
                        return RedactPersonalData::redact(parent::format($record));
                    }
                });
            }
        }
    }

    public static function redact(string $text): string
    {
        $text = preg_replace('#/p/[A-Za-z0-9_-]{20,}#', '/p/[tautan-rahasia]', $text) ?? $text;
        $text = preg_replace('/\b[a-f0-9]{64}\b/i', '[hash]', $text) ?? $text;
        $text = preg_replace('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(?:\.[A-Za-z0-9\-]+)+/', '[email]', $text) ?? $text;

        return preg_replace('/(?<![\d])(?:\+62|62|0)8\d{8,12}(?![\d])/', '[telepon]', $text) ?? $text;
    }
}
