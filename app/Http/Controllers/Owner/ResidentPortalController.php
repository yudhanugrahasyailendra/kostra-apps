<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Portal\RevokePortalTokens;
use App\Actions\Portal\SendPortalLink;
use App\Enums\MessageSkipReason;
use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ResidentPortalRequest;
use App\Models\PortalToken;
use App\Models\Resident;
use Illuminate\Http\RedirectResponse;

class ResidentPortalController extends Controller
{
    public function send(ResidentPortalRequest $request, Resident $resident, SendPortalLink $send): RedirectResponse
    {
        $log = $send($resident);

        $message = match (true) {
            $log === null => 'Tautan tidak dikirim: template "Tautan portal penghuni" dinonaktifkan atau akun tidak aktif.',
            $log->status === MessageStatus::Skipped && $log->skip_reason === MessageSkipReason::OptedOut => 'Tautan tidak dikirim: penghuni berhenti menerima pesan otomatis.',
            $log->status === MessageStatus::Failed => 'Tautan tidak dikirim: nomor WhatsApp penghuni tidak valid. Perbaiki nomornya lalu coba lagi.',
            default => 'Tautan portal diantrekan dan akan dikirim lewat WhatsApp.',
        };

        return redirect()->route('owner.residents.show', $resident)->with('status', $message);
    }

    public function revoke(ResidentPortalRequest $request, Resident $resident, PortalToken $portalToken, RevokePortalTokens $revoke): RedirectResponse
    {
        // Tautan harus milik penghuni di URL; kalau tidak, diperlakukan seperti tidak ada.
        abort_unless($portalToken->resident_id === $resident->getKey(), 404);

        $revoke->one($portalToken);

        return redirect()->route('owner.residents.show', $resident)->with('status', 'Tautan portal dicabut. Akses langsung ditutup.');
    }

    public function revokeAll(ResidentPortalRequest $request, Resident $resident, RevokePortalTokens $revoke): RedirectResponse
    {
        $count = $revoke->all($resident);

        return redirect()->route('owner.residents.show', $resident)
            ->with('status', $count > 0 ? "{$count} tautan portal dicabut. Akses langsung ditutup." : 'Tidak ada tautan aktif.');
    }
}
