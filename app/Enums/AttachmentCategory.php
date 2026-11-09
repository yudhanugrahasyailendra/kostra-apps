<?php

namespace App\Enums;

enum AttachmentCategory: string
{
    case Ktp = 'ktp';
    case Photo = 'photo';
    case TransferProof = 'transfer_proof';
    case ComplaintPhoto = 'complaint_photo';
    case ExpenseReceipt = 'expense_receipt';
    case RoomCondition = 'room_condition';
    case QrisImage = 'qris_image';

    public function label(): string
    {
        return match ($this) {
            self::Ktp => 'Foto KTP',
            self::Photo => 'Foto penghuni',
            self::TransferProof => 'Bukti transfer',
            self::ComplaintPhoto => 'Foto komplain',
            self::ExpenseReceipt => 'Nota pengeluaran',
            self::RoomCondition => 'Kondisi kamar',
            self::QrisImage => 'Gambar QRIS',
        };
    }

    /** Berkas kategori ini dienkripsi dan aksesnya dicatat di audit_logs. */
    public function isSensitive(): bool
    {
        return $this === self::Ktp;
    }

    /** @return list<self> Kategori yang boleh dilampirkan ke penghuni. */
    public static function forResident(): array
    {
        return [self::Ktp, self::Photo];
    }
}
