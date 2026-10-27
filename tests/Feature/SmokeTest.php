<?php

namespace Tests\Feature;

use Tests\TestCase;

class SmokeTest extends TestCase
{
    public function test_public_pages_respond(): void
    {
        $this->get('/')->assertOk();
        $this->get(route('login'))->assertOk()->assertSee('Masuk');
        $this->get(route('register'))->assertOk();
        $this->get(route('password.request'))->assertOk();
    }
}
