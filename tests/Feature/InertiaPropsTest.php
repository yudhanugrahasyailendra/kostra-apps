<?php

namespace Tests\Feature;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InertiaPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_utama_merender_komponen_welcome(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Welcome')
                ->has('message'));
    }

    public function test_props_dari_api_resource_tidak_memuat_kolom_terlarang(): void
    {
        $user = User::factory()->superAdmin()->create();

        Route::middleware('web')->get('/_uji/props', fn () => Inertia::render('Welcome', [
            'message' => 'uji',
            // resolve() menghasilkan array polos tanpa pembungkus "data".
            'user' => (new UserResource($user))->resolve(),
        ]));

        $this->get('/_uji/props')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Welcome')
                ->where('user.id', $user->id)
                ->where('user.name', $user->name)
                ->where('user.email', $user->email)
                ->missing('user.password')
                ->missing('user.remember_token')
                ->missing('user.token_hash')
                ->missing('user.tenant_id'));
    }
}
