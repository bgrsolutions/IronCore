<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
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

    public function test_admin_can_access_core_create_pages(): void
    {
        $this->seed(DatabaseSeeder::class);

        /** @var User $admin */
        $admin = User::query()->where('email', 'admin@ironcore.local')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/customers/create')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/admin/products/create')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/admin/repairs/create')
            ->assertOk();
    }
}
