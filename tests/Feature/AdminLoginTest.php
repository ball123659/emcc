<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/backend')->assertRedirect('/backend/login');
        $this->get('/backend/login')->assertOk()->assertSee('ชื่อผู้ใช้');
    }

    public function test_user_can_login_with_username(): void
    {
        $user = User::factory()->create(['username' => 'staff01', 'password' => 'secret123']);

        Livewire::test(Login::class)
            ->set('data.username', ' STAFF01 ')
            ->set('data.password', 'secret123')
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
        $this->get('/backend')->assertOk();
    }

    public function test_wrong_password_shows_thai_error(): void
    {
        User::factory()->create(['username' => 'staff01', 'password' => 'secret123']);

        Livewire::test(Login::class)
            ->set('data.username', 'staff01')
            ->set('data.password', 'wrong')
            ->call('authenticate')
            ->assertHasErrors(['data.username']);

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_access_panel(): void
    {
        User::factory()->inactive()->create(['username' => 'staff02', 'password' => 'secret123']);

        Livewire::test(Login::class)
            ->set('data.username', 'staff02')
            ->set('data.password', 'secret123')
            ->call('authenticate')
            ->assertHasErrors(['data.username']);

        $this->assertGuest();
    }
}
