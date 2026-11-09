<?php

namespace App\Http\Controllers\Owner;

use App\Actions\RevokePortalLinks;
use App\Actions\SendPortalLink;
use App\Http\Controllers\Controller;
use App\Models\Resident;
use Illuminate\Http\RedirectResponse;

/**
 * Tautan portal dari profil penghuni. Token asli tidak pernah dikirim ke halaman: tautan dibuat dan
 * langsung dikirim lewat WhatsApp (Fase 9), jadi satu-satunya salinan ada di pesan itu.
 */
class ResidentPortalController extends Controller
{
    public function store(Resident $resident, SendPortalLink $send): RedirectResponse
    {
        $this->authorize('update', $resident);

        $send->handle($resident);

        return back()->with('success', 'Tautan portal baru diantrekan untuk dikirim lewat WhatsApp.');
    }

    public function revoke(Resident $resident, RevokePortalLinks $revoke): RedirectResponse
    {
        $this->authorize('update', $resident);

        $count = $revoke->handle($resident);

        return back()->with('success', $count > 0 ? 'Semua tautan portal penghuni ini dicabut.' : 'Tidak ada tautan aktif untuk dicabut.');
    }
}
