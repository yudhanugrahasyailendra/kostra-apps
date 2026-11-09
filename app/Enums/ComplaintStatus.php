<?php

namespace App\Enums;

enum ComplaintStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::InProgress => 'Diproses',
            self::Done => 'Selesai',
            self::Rejected => 'Ditolak',
        };
    }

    /** Nada StatusBadge di sisi tampilan. */
    public function tone(): string
    {
        return match ($this) {
            self::New => 'menunggu',
            self::InProgress => 'kosong',
            self::Done => 'lunas',
            self::Rejected => 'jatuh-tempo',
        };
    }

    /** Tiket selesai atau ditolak sudah ditutup dan tidak berubah lagi. */
    public function isClosed(): bool
    {
        return in_array($this, [self::Done, self::Rejected], true);
    }

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::New => [self::InProgress, self::Done, self::Rejected],
            self::InProgress => [self::Done, self::Rejected],
            self::Done, self::Rejected => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }

    /** @return list<self> */
    public static function open(): array
    {
        return [self::New, self::InProgress];
    }
}
