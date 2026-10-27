<?php

namespace App\Services\Messaging;

use App\Models\Complaint;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Resident;
use App\Services\Portal\PortalLinkResolver;
use App\Support\Rupiah;
use Carbon\CarbonInterface;

/**
 * Menyusun penerima dan nilai variabel template untuk tiap jenis pemicu. Semua nilai berupa teks siap tampil
 * (pemformatan rupiah dan tanggal hanya terjadi di sini, di lapisan penyaji pesan).
 */
class MessageSubjects
{
    /**
     * @return array{resident: ?Resident, values: array<string, string>}
     */
    public function invoice(Invoice $invoice): array
    {
        $resident = $invoice->resident;

        return [
            'resident' => $resident,
            'values' => $this->values($resident, Rupiah::format($invoice->remaining()), $invoice->due_date),
        ];
    }

    /**
     * @return array{resident: ?Resident, values: array<string, string>}
     */
    public function payment(Payment $payment): array
    {
        $resident = $payment->resident;

        return [
            'resident' => $resident,
            'values' => $this->values($resident, Rupiah::format($payment->amount), $payment->paid_on),
        ];
    }

    /**
     * @return array{resident: ?Resident, values: array<string, string>}
     */
    public function lease(Lease $lease): array
    {
        $resident = $lease->primaryResident;

        return [
            'resident' => $resident,
            'values' => $this->values($resident, Rupiah::format($lease->rent_amount), $lease->end_date),
        ];
    }

    /**
     * @return array{resident: ?Resident, values: array<string, string>}
     */
    public function complaint(Complaint $complaint): array
    {
        $resident = $complaint->resident;

        return [
            'resident' => $resident,
            'values' => $this->values($resident, '', null, $complaint->status->label()),
        ];
    }

    /**
     * Pesan tautan portal: hanya nama dan tautan.
     *
     * @return array<string, string>
     */
    public function portal(Resident $resident): array
    {
        return $this->values($resident, '', null);
    }

    /**
     * @return array<string, string>
     */
    private function values(?Resident $resident, string $amount, ?CarbonInterface $date, string $status = ''): array
    {
        return [
            'nama' => $resident?->name ?? '',
            'jumlah' => $amount,
            'jatuh_tempo' => $date?->locale('id')->translatedFormat('j F Y') ?? '',
            // Tautan asli (bertoken) dibuat saat pengiriman; log hanya menyimpan placeholder.
            'link' => $resident ? PortalLinkResolver::PLACEHOLDER : '',
            'status' => $status,
        ];
    }
}
