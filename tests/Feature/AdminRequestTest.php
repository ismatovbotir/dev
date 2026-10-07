<?php

namespace Tests\Feature;

use App\Enums\Language;
use App\Enums\RequestStatus;
use App\Models\ShopRequest;
use App\Models\TelegramUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['services.telegram.token' => 'test-token']);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.requests.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_log_in(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'secret-pass'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'nope'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_list_shows_requests_and_filters_by_status(): void
    {
        ShopRequest::factory()->create(['name' => 'Alpha Client']);
        ShopRequest::factory()->create(['name' => 'Beta Client', 'status' => RequestStatus::Answered]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.requests.index', ['status' => 'answered']))
            ->assertOk()
            ->assertSee('Beta Client')
            ->assertDontSee('Alpha Client');
    }

    public function test_list_can_be_searched(): void
    {
        ShopRequest::factory()->create(['brand' => 'Korzinka']);
        ShopRequest::factory()->create(['brand' => 'Makro']);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.requests.index', ['q' => 'Korz']))
            ->assertSee('Korzinka')
            ->assertDontSee('Makro');
    }

    public function test_reply_stores_drawing_and_sends_it_to_the_client(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $admin = User::factory()->create();
        $shopRequest = ShopRequest::factory()->for(TelegramUser::factory()->state([
            'chat_id' => 777, 'language' => Language::Ru,
        ]))->create();

        $this->actingAs($admin)
            ->post(route('admin.requests.reply', $shopRequest), [
                'drawing' => UploadedFile::fake()->image('layout.png'),
                'admin_comment' => 'Includes 4 fridges',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $shopRequest->refresh();
        $this->assertSame(RequestStatus::Answered, $shopRequest->status);
        $this->assertNotNull($shopRequest->delivered_at);
        $this->assertSame($admin->id, $shopRequest->answered_by);
        Storage::disk('local')->assertExists($shopRequest->drawing_path);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendPhoto')
            && str_contains($request->body(), 'Includes 4 fridges')
            && str_contains($request->body(), '777'));
    }

    public function test_pdf_is_sent_as_a_document(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
        $shopRequest = ShopRequest::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('admin.requests.reply', $shopRequest), [
            'drawing' => UploadedFile::fake()->create('layout.pdf', 100, 'application/pdf'),
        ]);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendDocument'));
    }

    public function test_failed_delivery_keeps_the_reply_but_does_not_mark_answered(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'chat not found'], 400)]);
        $shopRequest = ShopRequest::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.requests.reply', $shopRequest), [
                'drawing' => UploadedFile::fake()->image('layout.jpg'),
            ])
            ->assertSessionHas('error');

        $shopRequest->refresh();
        $this->assertSame(RequestStatus::New, $shopRequest->status);
        $this->assertNull($shopRequest->delivered_at);
        $this->assertNotNull($shopRequest->drawing_path);
    }

    public function test_first_reply_requires_a_drawing_and_valid_file_type(): void
    {
        $shopRequest = ShopRequest::factory()->create();
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.requests.reply', $shopRequest), [])
            ->assertSessionHasErrors('drawing');

        $this->actingAs($admin)
            ->post(route('admin.requests.reply', $shopRequest), [
                'drawing' => UploadedFile::fake()->create('virus.exe', 10),
            ])
            ->assertSessionHasErrors('drawing');
    }

    public function test_status_can_be_changed(): void
    {
        $shopRequest = ShopRequest::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('admin.requests.status', $shopRequest), ['status' => 'in_progress'])
            ->assertSessionHasNoErrors();

        $this->assertSame(RequestStatus::InProgress, $shopRequest->refresh()->status);
    }

    public function test_drawing_download_requires_login(): void
    {
        $shopRequest = ShopRequest::factory()->create(['drawing_path' => 'drawings/x.png', 'drawing_name' => 'x.png']);

        $this->get(route('admin.requests.drawing', $shopRequest))->assertRedirect(route('login'));
    }
}
