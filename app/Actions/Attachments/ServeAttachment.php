<?php

namespace App\Actions\Attachments;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\AuditEvent;
use App\Models\Attachment;
use Illuminate\Http\Response;

class ServeAttachment
{
    public function __construct(
        private readonly ReadAttachment $read,
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * Respons berkas. Akses ke berkas sensitif dicatat di audit_logs.
     */
    public function __invoke(Attachment $attachment): Response
    {
        $contents = ($this->read)($attachment);

        if ($attachment->is_sensitive) {
            ($this->audit)(AuditEvent::ViewedSensitive, $attachment);
        }

        $headers = [
            'Content-Type' => $attachment->mime,
            // Nama generik: nama berkas asli bisa memuat nama orang.
            // PDF diunduh (bisa memuat skrip aktif); gambar boleh tampil langsung.
            'Content-Disposition' => ($attachment->mime === 'application/pdf' ? 'attachment' : 'inline')
                .'; filename="lampiran-'.$attachment->getKey().'.'.(StoreAttachment::ALLOWED[$attachment->mime] ?? 'bin').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
            'Referrer-Policy' => 'no-referrer',
        ];

        if ($attachment->is_sensitive) {
            $headers['Cache-Control'] = 'private, no-store, max-age=0';
        } else {
            $headers['Cache-Control'] = 'private, max-age=60';
        }

        return response($contents, 200, $headers);
    }
}
