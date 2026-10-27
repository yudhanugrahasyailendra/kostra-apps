<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Alamat utama: tamu diarahkan ke halaman masuk, pengguna yang sudah masuk ke panelnya.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        return redirect($user->isSuperAdmin() ? '/admin' : '/owner');
    }
}
