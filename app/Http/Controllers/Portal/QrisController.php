<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Attachments\ServeAttachment;
use App\Models\PaymentAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QrisController extends PortalController
{
    /**
     * Gambar QRIS statis rekening aktif tenant (informasi yang sama dengan Pengaturan Pembayaran pemilik).
     * Hanya gambar QRIS rekening itu yang disajikan; lampiran lain tidak bisa dijangkau lewat sini.
     */
    public function __invoke(Request $request, string $token, int $account, ServeAttachment $serve): Response
    {
        // Sama dengan rekening yang ditampilkan di tagihan: umum, atau khusus properti tempat penghuni menyewa.
        $propertyIds = $this->resident($request)->leases()->pluck('leases.property_id');

        $account = PaymentAccount::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('property_id')->orWhereIn('property_id', $propertyIds))
            ->with('qrisImage')
            ->find($account) ?? abort(404);

        return $serve($account->qrisImage ?? abort(404));
    }
}
