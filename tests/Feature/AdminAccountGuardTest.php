<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Policies\UserPolicy;
use Filament\Support\Exceptions\Halt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dua kecelakaan akun yang meninggalkan panel tanpa admin — hapus/demote
 * diri sendiri, atau sapu bersih semua admin — harus ditolak di lapisan
 * policy DAN di guard form, bukan hanya disembunyikan dari UI.
 */
class AdminAccountGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = (new UserPolicy)->delete($admin, $admin);

        $this->assertFalse($response->allowed());
    }

    public function test_other_admins_and_plain_users_still_deletable(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $plainUser = User::factory()->create(['role' => 'user']);

        $this->assertTrue((new UserPolicy)->delete($actor, $otherAdmin)->allowed());
        $this->assertTrue((new UserPolicy)->delete($actor, $plainUser)->allowed());
    }

    public function test_edit_guard_rejects_self_demotion(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertThrows(
            fn () => UserResource::guardAccountIntegrity(
                ['role' => UserRole::User->value],
                $admin,
                $admin,
            ),
            Halt::class,
        );
    }

    public function test_edit_guard_allows_demoting_other_admin(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'admin']);

        // Menurunkan admin KEDUA menyisakan aktor sebagai satu-satunya admin:
        // panel tetap dipegang seseorang, jadi ini sah.
        $data = UserResource::guardAccountIntegrity(
            ['role' => UserRole::User->value],
            $target,
            $actor,
        );

        $this->assertSame(UserRole::User->value, $data['role']);
    }

    public function test_edit_guard_passes_payload_without_role_change(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'user']);

        $data = UserResource::guardAccountIntegrity(
            ['name' => 'Nama Baru', 'phone' => '081234567890'],
            $target,
            $actor,
        );

        $this->assertSame('Nama Baru', $data['name']);
    }
}
