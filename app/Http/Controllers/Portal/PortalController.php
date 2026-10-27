<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Resident;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Dasar semua controller portal. Penghuni dan tenant berasal dari token yang divalidasi ResolvePortalToken,
 * tidak pernah dari input. Query data memakai penghuni itu sebagai batas kepemilikan.
 */
abstract class PortalController extends Controller
{
    protected function resident(Request $request): Resident
    {
        return $request->attributes->get('portal_resident');
    }

    protected function tenant(Request $request): Tenant
    {
        return $request->attributes->get('portal_tenant');
    }

    /**
     * Menjalankan Action; bila menolak dengan galat validasi, penghuni dikembalikan ke halaman $url (bukan lewat
     * back(): sesi portal tidak menyimpan URL sebelumnya, dan Referer dimatikan).
     */
    protected function orRedirect(string $url, callable $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $e) {
            throw $e->redirectTo($url);
        }
    }
}
