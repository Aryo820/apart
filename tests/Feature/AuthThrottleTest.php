<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited_after_10_failures(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'correct-password',
            'role' => 'user',
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', [
                'email' => 'user@example.com',
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_register_is_rate_limited_after_5_attempts(): void
    {
        // Email already exists: validation fails (unique) every time, so
        // the user is never authenticated and every attempt counts.
        User::factory()->create(['email' => 'test@example.com']);

        $attempt = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '08123456789',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', $attempt)->assertRedirect();
        }

        $this->post('/register', $attempt)->assertStatus(429);
    }
}
