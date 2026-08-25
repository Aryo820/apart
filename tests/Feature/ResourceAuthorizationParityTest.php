<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\ApartmentResource;
use App\Filament\Resources\ApartmentResource\Pages\EditApartment;
use App\Filament\Resources\FacilityResource\Pages\CreateFacility;
use App\Filament\Resources\FacilityResource\Pages\EditFacility;
use App\Filament\Resources\FacilityResource\Pages\ListFacilities;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\Apartment;
use App\Models\Facility;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ResourceAuthorizationParityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($this->admin);
    }

    public function test_admin_can_create_update_and_delete_a_facility_through_filament(): void
    {
        Livewire::test(CreateFacility::class)
            ->fillForm([
                'name' => 'Gym',
                'icon' => 'building-storefront',
                'description' => 'Shared gym',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $facility = Facility::where('name', 'Gym')->firstOrFail();

        Livewire::test(EditFacility::class, ['record' => $facility->getRouteKey()])
            ->fillForm(['name' => 'Fitness Center'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Fitness Center', $facility->fresh()->name);

        Livewire::test(ListFacilities::class)
            ->callAction(TestAction::make('delete')->table($facility));

        $this->assertNull($facility->fresh());
    }

    public function test_facility_bulk_delete_honors_per_record_authorization(): void
    {
        $apartment = $this->apartment();
        $referenced = Facility::create(['name' => 'Wifi']);
        $unreferenced = Facility::create(['name' => 'Sauna']);
        $apartment->facilities()->attach($referenced);

        Livewire::test(ListFacilities::class)
            ->callTableBulkAction('delete', [$referenced, $unreferenced]);

        $this->assertNotNull($referenced->fresh());
        $this->assertNull($unreferenced->fresh());
    }

    public function test_admin_can_create_and_modify_user_roles_through_filament(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Operations User',
                'email' => 'operations@example.test',
                'role' => UserRole::User->value,
                'password' => 'password123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'operations@example.test')->firstOrFail();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['role' => UserRole::Admin->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(UserRole::Admin, $user->fresh()->role);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->callAction(TestAction::make('delete'));

        $this->assertNull($user->fresh());
    }

    public function test_admin_can_update_and_delete_an_unreferenced_apartment_through_filament(): void
    {
        $apartment = $this->apartment();

        Livewire::test(EditApartment::class, ['record' => $apartment->getRouteKey()])
            ->fillForm(['title' => 'Updated Apartment'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Updated Apartment', $apartment->fresh()->title);
        $this->assertTrue(ApartmentResource::canCreate());

        Livewire::test(EditApartment::class, ['record' => $apartment->getRouteKey()])
            ->callAction(TestAction::make('delete'));

        $this->assertNull($apartment->fresh());
    }

    public function test_normal_user_cannot_mount_management_resource_components(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $this->actingAs($user);

        Livewire::test(CreateFacility::class)->assertForbidden();
        Livewire::test(CreateUser::class)->assertForbidden();
        Livewire::test(EditApartment::class, ['record' => $this->apartment()->getRouteKey()])->assertForbidden();
    }

    private function apartment(): Apartment
    {
        return Apartment::create([
            'title' => 'Authorization Apartment',
            'slug' => 'authorization-'.Str::random(8),
            'description' => 'Test apartment',
            'price_per_night' => 500000,
            'address' => 'Jl. Test',
            'city' => 'Jakarta',
            'bedrooms' => 1,
            'bathrooms' => 1,
            'area_sqm' => 30,
            'capacity' => 2,
            'main_image' => 'test.jpg',
            'is_featured' => false,
            'status' => 'available',
        ]);
    }
}
