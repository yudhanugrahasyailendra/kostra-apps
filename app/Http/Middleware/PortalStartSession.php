<?php

namespace App\Http\Middleware;

use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

/**
 * Sesi untuk portal publik. Sama dengan StartSession bawaan, kecuali URL halaman tidak disimpan sebagai
 * "previous URL": di portal URL itu memuat token asli, dan token asli tidak boleh tersimpan di basis data
 * (tabel sessions). Pengalihan setelah galat validasi dibuat eksplisit, tidak memakai back().
 */
class PortalStartSession extends StartSession
{
    /**
     * @param  Session  $session
     */
    protected function storeCurrentUrl(Request $request, $session)
    {
        // Sengaja kosong.
    }
}
