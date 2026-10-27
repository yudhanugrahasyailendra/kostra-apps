<?php

namespace App\Services\Messaging;

use App\Contracts\PortalLink;
use App\Models\Resident;

/**
 * Pengganti sementara sampai portal penghuni ada (Fase 10): mengarah ke alamat aplikasi, tanpa token.
 */
class AppUrlPortalLink implements PortalLink
{
    public function forResident(Resident $resident): string
    {
        return rtrim((string) config('app.url'), '/');
    }
}
