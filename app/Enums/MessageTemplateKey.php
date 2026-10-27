<?php

namespace App\Enums;

enum MessageTemplateKey: string
{
    case InvoiceIssued = 'invoice_issued';
    case ReminderBeforeDue = 'reminder_before_due';
    case ReminderDueToday = 'reminder_due_today';
    case ReminderOverdue = 'reminder_overdue';
    case Receipt = 'receipt';
    case LeaseExpiring = 'lease_expiring';
    // Penyimpangan kecil dari docs/database.md: listener ComplaintStatusChanged (Fase 8) butuh template sendiri.
    case ComplaintStatus = 'complaint_status';

    public function label(): string
    {
        return match ($this) {
            self::InvoiceIssued => 'Tagihan terbit',
            self::ReminderBeforeDue => 'Pengingat sebelum jatuh tempo',
            self::ReminderDueToday => 'Pengingat hari jatuh tempo',
            self::ReminderOverdue => 'Pengingat tunggakan',
            self::Receipt => 'Kuitansi pembayaran',
            self::LeaseExpiring => 'Kontrak segera habis',
            self::ComplaintStatus => 'Perubahan status komplain',
        };
    }

    /**
     * Template bawaan sistem (diisi seeder). Isi hanya boleh memakai variabel MessageTemplateRenderer::VARIABLES.
     */
    public function defaultBody(): string
    {
        return match ($this) {
            self::InvoiceIssued => 'Halo {{nama}}, tagihan kos Anda sebesar {{jumlah}} sudah terbit dan jatuh tempo pada {{jatuh_tempo}}. Detail: {{link}}',
            self::ReminderBeforeDue => 'Halo {{nama}}, pengingat: tagihan kos sebesar {{jumlah}} jatuh tempo pada {{jatuh_tempo}}. Detail: {{link}}',
            self::ReminderDueToday => 'Halo {{nama}}, tagihan kos sebesar {{jumlah}} jatuh tempo hari ini ({{jatuh_tempo}}). Detail: {{link}}',
            self::ReminderOverdue => 'Halo {{nama}}, tagihan kos sebesar {{jumlah}} sudah lewat jatuh tempo ({{jatuh_tempo}}). Mohon segera diselesaikan. Detail: {{link}}',
            self::Receipt => 'Halo {{nama}}, pembayaran sebesar {{jumlah}} sudah kami terima. Terima kasih. Kuitansi: {{link}}',
            self::LeaseExpiring => 'Halo {{nama}}, kontrak sewa kamar Anda akan berakhir pada {{jatuh_tempo}}. Hubungi pengelola bila ingin memperpanjang. {{link}}',
            self::ComplaintStatus => 'Halo {{nama}}, status keluhan Anda kini: {{status}}. Detail: {{link}}',
        };
    }
}
