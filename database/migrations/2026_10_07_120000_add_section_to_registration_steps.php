<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('registration_steps', function (Blueprint $table) {
            $table->string('section', 16)->default('conversation')->after('type');
        });

        Schema::table('telegram_users', function (Blueprint $table) {
            $table->json('profile')->nullable()->after('draft');
        });

        DB::table('registration_steps')->whereIn('key', ['name', 'phone'])->update(['section' => 'registration']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registration_steps', function (Blueprint $table) {
            $table->dropColumn('section');
        });

        Schema::table('telegram_users', function (Blueprint $table) {
            $table->dropColumn('profile');
        });
    }
};
