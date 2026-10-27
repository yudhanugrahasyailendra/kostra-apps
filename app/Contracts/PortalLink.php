<?php

namespace App\Contracts;

use App\Models\Resident;

/**
 * Tautan yang disisipkan ke variabel {{link}}. Fase 10 mengganti implementasinya dengan tautan portal
 * bertoken (hanya hash SHA-256 yang disimpan; token asli hanya ada di tautan).
 */
interface PortalLink
{
    public function forResident(Resident $resident): string;
}
