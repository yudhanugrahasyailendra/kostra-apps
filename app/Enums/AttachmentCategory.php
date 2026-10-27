<?php

namespace App\Enums;

enum AttachmentCategory: string
{
    case Ktp = 'ktp';
    case Photo = 'photo';
    // Penyimpangan kecil dari docs/database.md: PRD menyebut "dokumen lain" untuk penghuni.
    case Document = 'document';
    case TransferProof = 'transfer_proof';
    case ComplaintPhoto = 'complaint_photo';
    case ExpenseReceipt = 'expense_receipt';
    case RoomCondition = 'room_condition';

    public function label(): string
    {
        return match ($this) {
            self::Ktp => 'Foto KTP',
            self::Photo => 'Foto',
            self::Document => 'Dokumen lain',
            self::TransferProof => 'Bukti transfer',
            self::ComplaintPhoto => 'Foto komplain',
            self::ExpenseReceipt => 'Nota pengeluaran',
            self::RoomCondition => 'Kondisi kamar',
        };
    }

    /**
     * Berkas sensitif dienkripsi saat disimpan dan aksesnya dicatat.
     */
    public function isSensitive(): bool
    {
        return $this === self::Ktp;
    }

    /**
     * Lampiran keuangan tidak boleh dihapus (data keuangan hanya di-void).
     */
    public function isFinancial(): bool
    {
        return in_array($this, [self::TransferProof, self::ExpenseReceipt], true);
    }

    /**
     * Hanya satu berkas aktif per induk dan kategori; unggahan baru menggantikan yang lama.
     */
    public function isSingle(): bool
    {
        return in_array($this, [self::Ktp, self::Photo], true);
    }

    /**
     * Kategori yang boleh diunggah dari halaman penghuni.
     *
     * @return list<string>
     */
    public static function residentValues(): array
    {
        return [self::Ktp->value, self::Photo->value, self::Document->value];
    }
}
