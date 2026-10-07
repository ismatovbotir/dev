<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Flow: name, phone, point type, brand, location (optional), plan upload.
     */
    public function up(): void
    {
        $this->update('name', 1, [
            'uz' => "👤 <b>Ism va familiyangiz</b>\nSizga qanday murojaat qilaylik?",
            'ru' => "👤 <b>Ваше имя и фамилия</b>\nКак к вам обращаться?",
            'en' => "👤 <b>Your name and surname</b>\nHow should we address you?",
        ]);

        $this->update('phone', 2, [
            'uz' => "📱 <b>Mobil raqamingiz</b>\nPastdagi tugmani bosing yoki raqamni yozing.\n<i>Menejerimiz siz bilan shu raqam orqali bog'lanadi.</i>",
            'ru' => "📱 <b>Ваш мобильный номер</b>\nНажмите кнопку ниже или введите номер.\n<i>По нему с вами свяжется менеджер.</i>",
            'en' => "📱 <b>Your mobile number</b>\nTap the button below or type the number.\n<i>Our manager will use it to contact you.</i>",
        ]);

        $this->insert([
            'key' => 'point_type',
            'type' => 'choice',
            'label' => ['uz' => 'Nuqta turi', 'ru' => 'Тип точки', 'en' => 'Point type'],
            'question' => [
                'uz' => "🏪 <b>Nuqta turi</b>\nBiz savdo nuqtalarini professional rejalashtirishni taklif qilamiz. Agar nuqtangiz bo'lsa, uning turini tanlang:",
                'ru' => "🏪 <b>Тип вашей точки</b>\nМы предлагаем профессиональное планирование торговых точек. Если у вас уже есть точка, выберите её тип:",
                'en' => "🏪 <b>Type of your point</b>\nWe offer professional retail point planning. If you already have a point, please select its type:",
            ],
            'options' => [
                'uz' => ["Kichik do'kon", 'Supermarket', 'Gipermarket', 'Dark store', 'Ombor', "FMCG butik uslubidagi do'kon"],
                'ru' => ['Небольшой магазин', 'Супермаркет', 'Гипермаркет', 'Dark store', 'Склад', 'FMCG-бутик'],
                'en' => ['Small shop', 'Supermarket', 'Hypermarket', 'Dark store', 'Warehouse', 'Boutique-style FMCG shop'],
            ],
            'is_required' => true,
            'position' => 3,
        ]);

        $this->update('brand', 4);

        $this->update('location', 5, [
            'uz' => "📍 <b>Joylashuv</b> <i>(ixtiyoriy)</i>\nXohlasangiz, lokatsiya yuboring yoki manzilni yozing.",
            'ru' => "📍 <b>Расположение</b> <i>(по желанию)</i>\nЕсли хотите, отправьте геолокацию или напишите адрес.",
            'en' => "📍 <b>Location</b> <i>(optional)</i>\nIf you would like, share a location pin or type the address.",
        ], isRequired: false);

        $this->insert([
            'key' => 'plan',
            'type' => 'file',
            'label' => ['uz' => 'Reja', 'ru' => 'План', 'en' => 'Floor plan'],
            'question' => [
                'uz' => "📐 <b>Do'kon rejasi</b>\nQo'lda chizilgan eskiz yoki istalgan rasmni yuboring: unda <b>o'lchamlar</b>, <b>ustunlar joylashuvi</b>, <b>eshik</b> va <b>derazalar</b> ko'rsatilgan bo'lsin. Foto yoki PDF bo'ladi.",
                'ru' => "📐 <b>План магазина</b>\nОтправьте рисунок от руки или любое изображение плана с <b>размерами</b>, <b>расположением колонн</b>, <b>дверей</b> и <b>окон</b>. Подойдёт фото или PDF.",
                'en' => "📐 <b>Plan of the shop</b>\nPlease send a hand-drawn sketch or any picture of the plan showing the <b>dimensions</b>, <b>column positions</b>, <b>doors</b> and <b>windows</b>. A photo or PDF works.",
            ],
            'options' => null,
            'is_required' => true,
            'position' => 6,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('registration_steps')->whereIn('key', ['point_type', 'plan'])->delete();
    }

    /**
     * @param  array<string, string>|null  $question
     */
    private function update(string $key, int $position, ?array $question = null, ?bool $isRequired = null): void
    {
        $values = ['position' => $position, 'updated_at' => now()];

        if ($question !== null) {
            $values['question'] = json_encode($question, JSON_UNESCAPED_UNICODE);
        }

        if ($isRequired !== null) {
            $values['is_required'] = $isRequired;
        }

        DB::table('registration_steps')->where('key', $key)->update($values);
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function insert(array $step): void
    {
        DB::table('registration_steps')->updateOrInsert(['key' => $step['key']], [
            'type' => $step['type'],
            'label' => json_encode($step['label'], JSON_UNESCAPED_UNICODE),
            'question' => json_encode($step['question'], JSON_UNESCAPED_UNICODE),
            'options' => $step['options'] === null ? null : json_encode($step['options'], JSON_UNESCAPED_UNICODE),
            'is_required' => $step['is_required'],
            'is_active' => true,
            'is_core' => false,
            'position' => $step['position'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
