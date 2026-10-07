<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_admin_from_env_config(): void
    {
        config(['admin.name' => 'Boss', 'admin.email' => 'boss@rsg.uz', 'admin.password' => 'S3cret-pass']);

        $this->seed(DatabaseSeeder::class);

        $admin = User::sole();
        $this->assertSame('boss@rsg.uz', $admin->email);
        $this->assertSame('Boss', $admin->name);
        $this->assertTrue(Hash::check('S3cret-pass', $admin->password));
    }

    public function test_seeding_twice_keeps_a_single_admin_and_applies_a_new_password(): void
    {
        config(['admin.email' => 'boss@rsg.uz', 'admin.password' => 'first-pass-1']);
        $this->seed(DatabaseSeeder::class);

        config(['admin.password' => 'second-pass-2']);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertTrue(Hash::check('second-pass-2', User::sole()->password));
    }

    public function test_seeder_fails_loudly_without_credentials(): void
    {
        config(['admin.email' => null, 'admin.password' => null]);

        $this->expectException(RuntimeException::class);

        $this->seed(DatabaseSeeder::class);
    }
}
