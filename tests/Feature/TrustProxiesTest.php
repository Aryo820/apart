<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aplikasi berjalan di balik reverse proxy: throttle berbasis IP harus
 * memakai IP hasil resolusi X-Forwarded-For, bukan IP proxy. Kalau tidak,
 * satu aktor bisa menjenuhkan bucket login global dan mengunci autentikasi
 * semua pengguna.
 */
class TrustProxiesTest extends TestCase
{
    use RefreshDatabase;

    private const PROXY_A = '203.0.113.10';

    private const PROXY_B = '198.51.100.22';

    public function test_forwarded_clients_have_distinct_rate_limit_buckets(): void
    {
        $credentials = [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ];

        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', $credentials, ['X-Forwarded-For' => self::PROXY_A])
                ->assertRedirect();
        }

        // Bucket tamu dari PROXY_A sudah penuh...
        $this->post('/login', $credentials, ['X-Forwarded-For' => self::PROXY_A])
            ->assertStatus(429);

        // ...tetapi tamu lain yang lewat proxy yang sama tidak ikut terkunci.
        $this->post('/login', $credentials, ['X-Forwarded-For' => self::PROXY_B])
            ->assertRedirect();
    }
}
