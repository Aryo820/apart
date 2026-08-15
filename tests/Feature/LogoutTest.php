<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this
            ->actingAs($user)
            ->post(route('logout'));

        $response
            ->assertRedirect('/')
            ->assertSessionHas('success', 'Anda telah keluar.');

        $this->assertGuest();
    }
}
