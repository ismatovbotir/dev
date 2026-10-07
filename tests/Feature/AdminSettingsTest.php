<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\ShopRequest;
use App\Models\TelegramUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

    public function test_bot_connection_check_reports_the_bot_username(): void
    {
        config(['services.telegram.token' => 'test-token']);
        Http::fake([
            'api.telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'rsg_leads_bot']]),
            'api.telegram.org/*/getWebhookInfo' => Http::response(['ok' => true, 'result' => ['url' => '']]),
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.settings.bot-check'))
            ->assertSessionHas('status', fn (string $message) => str_contains($message, '@rsg_leads_bot'));
    }

    public function test_bot_connection_check_reports_failures(): void
    {
        config(['services.telegram.token' => null]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.settings.bot-check'))
            ->assertSessionHas('error');
    }

    public function test_several_floor_plan_examples_can_be_uploaded_at_once_and_previewed(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.settings.plan-examples'), ['examples' => $this->images(4)])
            ->assertSessionHasNoErrors();

        $examples = Setting::planExamples();
        $this->assertSame([1, 2, 3, 4], array_keys($examples));
        Storage::disk('local')->assertExists(array_column($examples, 'path'));

        $this->actingAs($admin)->get(route('admin.settings.plan-example', 3))->assertOk();
        $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk()->assertSee('Floor plan examples')->assertSee('image1.jpg');
    }

    public function test_adding_more_images_later_keeps_the_existing_ones(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.settings.plan-examples'), ['examples' => $this->images(2)]);
        $firstPath = Setting::planExamples()[1]['path'];

        $this->actingAs($admin)->post(route('admin.settings.plan-examples'), ['examples' => $this->images(3)]);

        $this->assertSame([1, 2, 3, 4, 5], array_keys(Setting::planExamples()));
        Storage::disk('local')->assertExists($firstPath);
    }

    public function test_selected_images_can_be_removed_and_their_files_are_deleted(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.settings.plan-examples'), ['examples' => $this->images(3)]);
        $examples = Setting::planExamples();

        $this->actingAs($admin)->post(route('admin.settings.plan-examples'), ['remove' => [1, 3]]);

        Storage::disk('local')->assertMissing([$examples[1]['path'], $examples[3]['path']]);
        Storage::disk('local')->assertExists($examples[2]['path']);
        $this->assertSame([2], array_keys(Setting::planExamples()));
    }

    public function test_removing_and_adding_in_one_save_replaces_the_image(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.settings.plan-examples'), ['examples' => $this->images(2)]);
        $removedPath = Setting::planExamples()[2]['path'];

        $this->actingAs($admin)->post(route('admin.settings.plan-examples'), ['remove' => [2], 'examples' => $this->images(1)]);

        $examples = Setting::planExamples();
        $this->assertCount(2, $examples);
        Storage::disk('local')->assertMissing($removedPath);
        Storage::disk('local')->assertExists(array_column($examples, 'path'));
    }

    public function test_examples_must_be_images_under_five_megabytes(): void
    {
        Storage::fake('local');

        $this->actingAs(User::factory()->create())
            ->post(route('admin.settings.plan-examples'), ['examples' => [
                UploadedFile::fake()->create('plan.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->image('huge.jpg')->size(6000),
            ]])
            ->assertSessionHasErrors(['examples.0', 'examples.1']);

        $this->assertSame([], Setting::planExamples());
    }

    public function test_at_most_ten_examples_can_be_kept(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.settings.plan-examples'), ['examples' => $this->images(9)])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.settings.plan-examples'), ['examples' => $this->images(2)])->assertSessionHasErrors('examples');
        $this->assertCount(9, Setting::planExamples());

        $this->actingAs($admin)->post(route('admin.settings.plan-examples'), ['remove' => [1], 'examples' => $this->images(2)])->assertSessionHasNoErrors();
        $this->assertCount(10, Setting::planExamples());
    }

    public function test_example_previews_require_login_and_missing_ones_are_404(): void
    {
        $this->get(route('admin.settings.plan-example', 1))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->get(route('admin.settings.plan-example', 2))->assertNotFound();
    }

    /**
     * @return list<UploadedFile>
     */
    private function images(int $count): array
    {
        return array_map(fn (int $number) => UploadedFile::fake()->image("image{$number}.jpg"), range(1, $count));
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
