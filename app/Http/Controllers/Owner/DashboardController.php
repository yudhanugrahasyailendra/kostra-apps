<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        // Kerangka; angka dashboard menyusul di Fase 7.
        return Inertia::render('Owner/Dashboard');
    }
}
