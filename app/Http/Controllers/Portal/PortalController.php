<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\Resident;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

/**
 * Dasar controller portal. Satu-satunya sumber identitas adalah penghuni dari token (ResolvePortalToken);
 * id dari URL tidak pernah dipercaya tanpa dicari lewat penghuni itu (cegah IDOR).
 */
abstract class PortalController extends Controller
{
    protected function resident(Request $request): Resident
    {
        return $request->attributes->get('portal_resident');
    }

    protected function tenant(): Tenant
    {
        return TenantContext::instance()->tenant();
    }

    /** Kontrak aktif penghuni ini (sebagai penyewa utama atau penghuni tambahan), atau null. */
    protected function activeLease(Resident $resident): ?Lease
    {
        // Dipakai kerangka dan isi halaman; cukup satu query per request. Disimpan di request, bukan di
        // properti controller: instance controller bisa dipakai ulang antar request (mis. Octane), dan
        // kontrak satu penghuni tidak boleh terbawa ke penghuni lain.
        $attributes = request()->attributes;

        if ($attributes->has('portal_lease')) {
            return $attributes->get('portal_lease');
        }

        $lease = Lease::query()->active()
            ->where(fn ($q) => $q->where('primary_resident_id', $resident->id)
                ->orWhereHas('leaseResidents', fn ($lr) => $lr->where('resident_id', $resident->id)))
            ->with(['room.type', 'property'])
            ->orderByDesc('start_date')->orderByDesc('id')
            ->first();

        $attributes->set('portal_lease', $lease);

        return $lease;
    }

    /** Data kerangka (prop `shell`) yang sama di setiap halaman portal. */
    protected function shell(Request $request): array
    {
        $resident = $this->resident($request);
        $lease = $this->activeLease($resident);

        return ['shell' => [
            'resident' => ['name' => $resident->name],
            'business_name' => $this->tenant()->name,
            'room_number' => $lease?->room?->number,
            'property_name' => $lease?->property?->name,
            'read_only' => $this->tenant()->isReadOnly(),
        ]];
    }
}
