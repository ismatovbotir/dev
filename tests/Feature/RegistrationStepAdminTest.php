<?php

namespace Tests\Feature;

use App\Models\RegistrationStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationStepAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_steps_pages_require_login(): void
    {
        $this->get(route('admin.steps.index'))->assertRedirect(route('login'));
        $this->post(route('admin.steps.store'), [])->assertRedirect(route('login'));
    }

    public function test_default_steps_are_listed_in_order(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.steps.index'))
            ->assertOk()
            ->assertSeeInOrder(['Name', 'Phone', 'Point type', 'Brand', 'Location', 'Floor plan']);
    }

    public function test_a_choice_step_can_be_added(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.steps.store'), $this->payload([
                'type' => 'choice',
                'options' => ['uz' => "Kichik\nKatta", 'ru' => "Малый\nБольшой", 'en' => "Small\nLarge\n\nSmall"],
            ]))
            ->assertRedirect(route('admin.steps.index'))
            ->assertSessionHasNoErrors();

        $step = RegistrationStep::where('key', 'like', 'custom_%')->sole();
        $this->assertSame(['Small', 'Large'], $step->optionsFor('en'));
        $this->assertSame(['Kichik', 'Katta'], $step->optionsFor('uz'));
        $this->assertSame(7, $step->position);
        $this->assertStringStartsWith('custom_', $step->key);
    }

    public function test_choice_step_requires_options(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.steps.store'), $this->payload(['type' => 'choice', 'options' => ['uz' => '', 'ru' => '', 'en' => '']]))
            ->assertSessionHasErrors('options.uz');
    }

    public function test_every_language_needs_the_same_number_of_options(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.steps.store'), $this->payload([
                'type' => 'choice',
                'options' => ['uz' => "A\nB", 'ru' => "А\nБ", 'en' => "A\nB\nC"],
            ]))
            ->assertSessionHasErrors('options');

        $this->assertSame(0, RegistrationStep::where('is_core', false)->where('key', 'like', 'custom_%')->count());
    }

    public function test_page_is_split_into_registration_and_conversation_blocks(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.steps.index'))
            ->assertOk()
            ->assertSeeInOrder(['Registration', 'Language', 'Name', 'Phone', 'Conversation', 'Point type', 'Brand', 'Location', 'Floor plan']);
    }

    public function test_a_step_can_be_added_to_the_registration_block(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.steps.store'), $this->payload(['section' => 'registration']))
            ->assertSessionHasNoErrors();

        $custom = RegistrationStep::where('key', 'like', 'custom_%')->sole();
        $this->assertSame('registration', $custom->section->value);
        $this->assertSame(['name', 'phone', $custom->key], RegistrationStep::ordered()->limit(3)->pluck('key')->all());
    }

    public function test_create_form_preselects_the_block_from_the_link(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.steps.create', ['section' => 'registration']))
            ->assertOk()
            ->assertSee('value="registration" selected', false);
    }

    public function test_built_in_steps_cannot_move_to_another_block(): void
    {
        $name = RegistrationStep::where('key', 'name')->firstOrFail();

        $this->actingAs(User::factory()->create())
            ->put(route('admin.steps.update', $name), $this->payload(['section' => 'conversation']))
            ->assertSessionHasErrors('section');

        $this->assertSame('registration', $name->refresh()->section->value);
    }

    public function test_reordering_stays_inside_the_block(): void
    {
        $admin = User::factory()->create();
        $pointType = RegistrationStep::where('key', 'point_type')->firstOrFail();
        $phone = RegistrationStep::where('key', 'phone')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.steps.move', $pointType), ['direction' => 'up']);
        $this->actingAs($admin)->post(route('admin.steps.move', $phone), ['direction' => 'down']);

        $this->assertSame(['name', 'phone', 'point_type', 'brand', 'location', 'plan'], RegistrationStep::ordered()->pluck('key')->all());
    }

    public function test_all_three_languages_are_required(): void
    {
        $payload = $this->payload();
        unset($payload['question']['ru']);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.steps.store'), $payload)
            ->assertSessionHasErrors('question.ru');
    }

    public function test_core_step_wording_can_be_edited_but_its_type_cannot(): void
    {
        $phone = RegistrationStep::where('key', 'phone')->firstOrFail();
        $withoutType = $this->payload(['label' => ['uz' => 'Raqam', 'ru' => 'Номер', 'en' => 'Number']]);
        unset($withoutType['type'], $withoutType['section']);

        $this->actingAs(User::factory()->create())
            ->put(route('admin.steps.update', $phone), $withoutType)
            ->assertSessionHasNoErrors();

        $this->assertSame('Number', $phone->refresh()->labelFor('en'));
        $this->assertSame('phone', $phone->type->value);

        $this->actingAs(User::factory()->create())
            ->put(route('admin.steps.update', $phone), $this->payload(['type' => 'text']))
            ->assertSessionHasErrors('type');
    }

    public function test_steps_can_be_reordered(): void
    {
        $admin = User::factory()->create();
        $phone = RegistrationStep::where('key', 'phone')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.steps.move', $phone), ['direction' => 'up']);

        $this->assertSame(['phone', 'name', 'point_type', 'brand', 'location', 'plan'], RegistrationStep::ordered()->pluck('key')->all());

        $this->actingAs($admin)->post(route('admin.steps.move', $phone), ['direction' => 'up']);

        $this->assertSame(['phone', 'name', 'point_type', 'brand', 'location', 'plan'], RegistrationStep::ordered()->pluck('key')->all());
    }

    public function test_a_step_can_be_disabled_but_not_the_last_active_one(): void
    {
        $admin = User::factory()->create();
        RegistrationStep::where('key', '!=', 'phone')->update(['is_active' => false]);
        $phone = RegistrationStep::where('key', 'phone')->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.steps.toggle', $phone))->assertSessionHas('error');

        $this->assertTrue($phone->refresh()->is_active);
    }

    public function test_custom_steps_can_be_deleted_but_built_in_ones_cannot(): void
    {
        $admin = User::factory()->create();
        $custom = RegistrationStep::factory()->create();
        $core = RegistrationStep::where('key', 'name')->firstOrFail();

        $this->actingAs($admin)->delete(route('admin.steps.destroy', $custom));
        $this->actingAs($admin)->delete(route('admin.steps.destroy', $core))->assertSessionHas('error');

        $this->assertModelMissing($custom);
        $this->assertModelExists($core);
    }

    public function test_dashboard_renders_with_data(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSeeText('Dashboard')
            ->assertSeeText('Bot steps');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'type' => 'text',
            'section' => 'conversation',
            'label' => ['uz' => 'Maydon', 'ru' => 'Площадь', 'en' => 'Area'],
            'question' => ['uz' => 'Maydon?', 'ru' => 'Площадь?', 'en' => 'Area?'],
            'is_required' => '1',
            'is_active' => '1',
            ...$overrides,
        ];
    }
}
