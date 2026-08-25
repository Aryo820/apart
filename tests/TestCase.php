<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Apply the testing environment before the application boots.
     *
     * PHPUnit's <env> overrides are unreliable on some Windows setups
     * (variables_order without E/S means they never reach $_ENV), so the
     * values set here are the single source of truth for tests. They win
     * over .env because Dotenv never overwrites an existing variable.
     */
    protected function setUp(): void
    {
        /*
         * Suite default tetap SQLite :memory:. Menyetel TEST_DB_DRIVER=mysql
         * menjalankan seluruh suite pada MySQL (dipakai job CI "test-mysql")
         * — penting karena perilaku locking/race hanya nyata di sana, bukan
         * di SQLite yang menuliskan secara serialisasi penuh.
         *
         * Dibaca lewat getenv() dengan cadangan $_ENV: pada beberapa setup
         * Windows variables_order tidak memuat "E", sehingga $_ENV kosong dan
         * env dari CLI hanya terlihat lewat getenv().
         */
        $driver = self::envValue('TEST_DB_DRIVER', 'sqlite');

        $overrides = [
            'APP_ENV' => 'testing',
            'APP_MAINTENANCE_DRIVER' => 'file',
            'BCRYPT_ROUNDS' => '4',
            'BROADCAST_CONNECTION' => 'null',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => $driver,
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ];

        if ($driver === 'sqlite') {
            $overrides['DB_DATABASE'] = ':memory:';
        }

        if ($driver === 'mysql') {
            $overrides += [
                'DB_HOST' => self::envValue('TEST_DB_HOST', '127.0.0.1'),
                'DB_PORT' => self::envValue('TEST_DB_PORT', '3306'),
                'DB_DATABASE' => self::envValue('TEST_DB_DATABASE', 'apart_test'),
                'DB_USERNAME' => self::envValue('TEST_DB_USERNAME', 'root'),
                'DB_PASSWORD' => self::envValue('TEST_DB_PASSWORD', ''),
            ];
        }

        foreach ($overrides as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        parent::setUp();
    }

    /**
     * Baca satu variabel env untuk konfigurasi test: $_ENV lebih dulu (sumber
     * phpunit.xml), lalu getenv() (sumber CLI pada Windows tanpa "E" di
     * variables_order), baru default-nya.
     */
    private static function envValue(string $key, string $default): string
    {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string) $_ENV[$key];
        }

        $fromGetenv = getenv($key);

        return $fromGetenv === false || $fromGetenv === '' ? $default : (string) $fromGetenv;
    }
}
