<?php

namespace App\Http\Resources;

use App\Models\MessageLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Baris log pengiriman. Nomor penerima disamarkan di tengah; isi pesan lengkap tidak dikirim ke daftar.
 * Relasi `resident` dimuat pemanggil; nama template lewat `template_name` yang diisi pemanggil.
 *
 * @mixin MessageLog
 */
class MessageLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'resident_name' => $this->whenLoaded('resident', fn () => $this->resident?->name),
            'recipient' => self::mask($this->recipient),
            'template_name' => $this->resource->getAttribute('template_name'),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_tone' => $this->status->tone(),
            'skip_reason_label' => $this->skip_reason?->label(),
            'error_code' => $this->error_code,
            'attempts' => $this->attempts,
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'can_resend' => $this->status->value === 'failed',
        ];
    }

    public static function mask(string $number): string
    {
        $length = mb_strlen($number);

        return $length <= 7 ? $number : mb_substr($number, 0, 5).str_repeat('*', $length - 9).mb_substr($number, -4);
    }
}
