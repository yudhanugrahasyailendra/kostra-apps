<?php

namespace App\Services\Portal;

use App\Contracts\PortalLink;
use App\Models\Resident;

/**
 * Isi pesan yang tersimpan di message_logs memuat PLACEHOLDER, bukan tautan asli: token asli hanya boleh ada
 * di tautan yang dikirim ke penghuni. Placeholder diganti tautan bertoken pada saat pengiriman.
 */
class PortalLinkResolver
{
    /** Pengganti variabel {{link}} saat isi pesan disusun. Tidak memuat kurung kurawal supaya tidak dikira variabel. */
    public const PLACEHOLDER = '[[tautan-portal]]';

    /** Teks yang ditampilkan di log pesan sebagai ganti tautan pribadi. */
    public const DISPLAY = '(tautan portal pribadi)';

    public function __construct(private readonly PortalLink $link) {}

    public function resolve(string $body, Resident $resident): string
    {
        if (! str_contains($body, self::PLACEHOLDER)) {
            return $body;
        }

        return str_replace(self::PLACEHOLDER, $this->link->forResident($resident), $body);
    }

    public static function mask(string $body): string
    {
        return str_replace(self::PLACEHOLDER, self::DISPLAY, $body);
    }
}
