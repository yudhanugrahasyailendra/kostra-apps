<?php

namespace App\Services\Portal;

use App\Actions\Portal\IssuePortalToken;
use App\Contracts\PortalLink;
use App\Models\Resident;

/**
 * Tautan portal bertoken. Setiap pemanggilan membuat token baru, jadi dipanggil hanya saat pesan benar-benar
 * dikirim (lihat PortalLinkResolver), bukan saat isi pesan disusun.
 */
class TokenPortalLink implements PortalLink
{
    public function __construct(private readonly IssuePortalToken $issue) {}

    public function forResident(Resident $resident): string
    {
        [, $raw] = ($this->issue)($resident);

        return route('portal.home', ['token' => $raw]);
    }
}
