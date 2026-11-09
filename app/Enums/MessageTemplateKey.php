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
    // Tidak ada di daftar database.md; dipakai listener ComplaintStatusChanged (NTF dan KMP-02).
    case ComplaintStatus = 'complaint_status';
    // Tautan portal penghuni dikirim pemilik dari profil penghuni (Fase 10).
    case PortalLink = 'portal_link';

    public function label(): string
    {
        return match ($this) {
            self::InvoiceIssued => 'Tagihan terbit',
            self::ReminderBeforeDue => 'Pengingat sebelum jatuh tempo',
            self::ReminderDueToday => 'Pengingat hari jatuh tempo',
            self::ReminderOverdue => 'Pengingat tunggakan',
            self::Receipt => 'Kuitansi pembayaran',
            self::LeaseExpiring => 'Kontrak segera habis',
            self::ComplaintStatus => 'Status komplain berubah',
            self::PortalLink => 'Tautan portal penghuni',
        };
    }

    /** Template pengingat menurut offset hari terhadap jatuh tempo: sebelum, tepat, atau sesudah. */
    public static function forReminderOffset(int $offset): self
    {
        return match (true) {
            $offset < 0 => self::ReminderBeforeDue,
            $offset === 0 => self::ReminderDueToday,
            default => self::ReminderOverdue,
        };
    }
}
