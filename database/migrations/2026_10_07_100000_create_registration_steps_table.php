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
        Schema::create('registration_steps', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type', 16);
            $table->json('label');
            $table->json('question');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_core')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('shop_requests', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->change();
            $table->string('name')->nullable()->change();
            $table->string('brand')->nullable()->change();
            $table->json('answers')->nullable();
        });

        DB::table('telegram_users')
            ->whereIn('state', ['awaiting_phone', 'awaiting_name', 'awaiting_location', 'awaiting_brand'])
            ->update(['state' => 'idle', 'draft' => null]);

        $this->seedDefaultSteps();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_requests', function (Blueprint $table) {
            $table->dropColumn('answers');
        });

        Schema::dropIfExists('registration_steps');
    }

    private function seedDefaultSteps(): void
    {
        $steps = [
            [
                'key' => 'phone',
                'type' => 'phone',
                'label' => ['uz' => 'Telefon', 'ru' => 'Телефон', 'en' => 'Phone'],
                'question' => [
                    'uz' => "📱 <b>Telefon raqamingiz</b>\nPastdagi tugmani bosing yoki raqamni yozing.\n<i>Menejerimiz siz bilan shu raqam orqali bog'lanadi.</i>",
                    'ru' => "📱 <b>Ваш номер телефона</b>\nНажмите кнопку ниже или введите номер.\n<i>По нему с вами свяжется менеджер.</i>",
                    'en' => "📱 <b>Your phone number</b>\nTap the button below or type the number.\n<i>Our manager will use it to contact you.</i>",
                ],
            ],
            [
                'key' => 'name',
                'type' => 'text',
                'label' => ['uz' => 'Ism', 'ru' => 'Имя', 'en' => 'Name'],
                'question' => [
                    'uz' => "👤 <b>Ism va familiyangiz</b>\nSizga qanday murojaat qilaylik?",
                    'ru' => "👤 <b>Ваше имя и фамилия</b>\nКак к вам обращаться?",
                    'en' => "👤 <b>Your full name</b>\nHow should we address you?",
                ],
            ],
            [
                'key' => 'location',
                'type' => 'location',
                'label' => ['uz' => 'Joylashuv', 'ru' => 'Расположение', 'en' => 'Location'],
                'question' => [
                    'uz' => "📍 <b>Do'kon joylashuvi</b>\nPastdagi tugma orqali lokatsiya yuboring yoki manzilni yozing (shahar, tuman, ko'cha).",
                    'ru' => "📍 <b>Расположение магазина</b>\nОтправьте геолокацию кнопкой ниже или напишите адрес (город, район, улица).",
                    'en' => "📍 <b>Shop location</b>\nShare a location pin with the button below, or type the address (city, district, street).",
                ],
            ],
            [
                'key' => 'brand',
                'type' => 'text',
                'label' => ['uz' => 'Brend', 'ru' => 'Бренд', 'en' => 'Brand'],
                'question' => [
                    'uz' => "🏷 <b>Brend / tarmoq nomi</b>\nDo'kon qaysi nom ostida ishlaydi?",
                    'ru' => "🏷 <b>Бренд / название сети</b>\nПод каким названием будет работать магазин?",
                    'en' => "🏷 <b>Brand / chain name</b>\nUnder which name will the shop operate?",
                ],
            ],
        ];

        foreach ($steps as $position => $step) {
            DB::table('registration_steps')->insert([
                'key' => $step['key'],
                'type' => $step['type'],
                'label' => json_encode($step['label'], JSON_UNESCAPED_UNICODE),
                'question' => json_encode($step['question'], JSON_UNESCAPED_UNICODE),
                'options' => null,
                'is_required' => true,
                'is_active' => true,
                'is_core' => true,
                'position' => $position + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
