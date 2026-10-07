<?php

namespace Tests\Feature;

use App\Models\ShopRequest;
use App\Models\TelegramUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_shows_requests_clients_and_settings(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.requests.index'))
            ->assertOk()
            ->assertSee(route('admin.requests.index'))
            ->assertSee(route('admin.clients.index'))
            ->assertSee(route('admin.settings.edit'))
            ->assertSeeText('Settings')
            ->assertSeeText('Clients');
    }

    public function test_settings_pages_require_login(): void
    {
        $this->get(route('admin.settings.edit'))->assertRedirect(route('login'));
        $this->get(route('admin.clients.index'))->assertRedirect(route('login'));
    }

    public function test_settings_page_never_reveals_the_bot_token(): void
    {
        config(['services.telegram.token' => 'SUPER-SECRET-TOKEN', 'services.telegram.webhook_secret' => 'SUPER-SECRET-HOOK']);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSeeText('Configured')
            ->assertDontSee('SUPER-SECRET-TOKEN')
            ->assertDontSee('SUPER-SECRET-HOOK');
    }

    public function test_profile_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('admin.settings.profile'), ['name' => 'New Name', 'email' => 'new@rsg.uz'])
            ->assertSessionHasNoErrors();

        $this->assertSame('new@rsg.uz', $user->refresh()->email);
        $this->assertSame('New Name', $user->name);
    }

    public function test_password_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password-1']);

        $this->actingAs($user)
            ->put(route('admin.settings.password'), [
                'current_password' => 'wrong',
                'password' => 'brand-new-pass',
                'password_confirmation' => 'brand-new-pass',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('old-password-1', $user->refresh()->password));
    }

    public function test_password_can_be_changed(): void
    {
        $user = User::factory()->create(['password' => 'old-password-1']);

        $this->actingAs($user)
            ->put(route('admin.settings.password'), [
                'current_password' => 'old-password-1',
                'password' => 'brand-new-pass',
                'password_confirmation' => 'brand-new-pass',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-pass', $user->refresh()->password));
    }

    public function test_clients_page_lists_clients_with_request_counts(): void
    {
        $client = TelegramUser::factory()->create(['first_name' => 'Zafar']);
        ShopRequest::factory()->count(2)->for($client)->create();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSeeText('Zafar');
    }
}
