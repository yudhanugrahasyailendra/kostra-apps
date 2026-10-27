<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tests_run_on_mysql_test_database(): void
    {
        $this->assertSame('mysql', DB::getDriverName());
        $this->assertSame('kostra_test', DB::getDatabaseName());
    }
}
