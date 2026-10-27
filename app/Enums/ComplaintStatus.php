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

    public function tone(): string
    {
        return match ($this) {
            self::New => 'warn',
            self::InProgress => 'neutral',
            self::Done => 'ok',
            self::Rejected => 'danger',
        };
    }

    /**
     * Tiket yang sudah selesai atau ditolak ditutup dan tidak dibuka lagi.
     */
    public function isClosed(): bool
    {
        return in_array($this, [self::Done, self::Rejected], true);
    }

    /**
     * @return list<self>
     */
    public function nextStatuses(): array
    {
        return match ($this) {
            self::New => [self::InProgress, self::Done, self::Rejected],
            self::InProgress => [self::Done, self::Rejected],
            self::Done, self::Rejected => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->nextStatuses(), true);
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::New->value, self::InProgress->value];
    }

    /**
     * @return list<string>
     */
    public static function closedValues(): array
    {
        return [self::Done->value, self::Rejected->value];
    }
}
