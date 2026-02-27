<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_filament_login_page_is_reachable(): void
    {
        $response = $this->get('/admin/login');
        $response->assertStatus(200);
    }
}
