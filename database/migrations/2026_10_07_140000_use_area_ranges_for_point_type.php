<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Point type buttons become shop-area ranges. Options the admin has already edited are left alone.
     */
    public function up(): void
    {
        $step = DB::table('registration_steps')->where('key', 'point_type')->first();

        if ($step === null || json_decode((string) $step->options, true) !== $this->previousDefaults()) {
            return;
        }

        DB::table('registration_steps')->where('key', 'point_type')->update([
            'options' => json_encode([
                'uz' => ['100 m2 gacha', '100-400 m2', '500-1 000 m2', '1 000-2 000 m2', '2 000 m2 dan katta'],
                'ru' => ['до 100 м²', '100-400 м²', '500-1 000 м²', '1 000-2 000 м²', 'более 2 000 м²'],
                'en' => ['Up to 100 m²', '100-400 m²', '500-1,000 m²', '1,000-2,000 m²', 'Over 2,000 m²'],
            ], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('registration_steps')->where('key', 'point_type')->update([
            'options' => json_encode($this->previousDefaults(), JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    private function previousDefaults(): array
    {
        return [
            'uz' => ["Kichik do'kon", 'Supermarket', 'Gipermarket', 'Dark store', 'Ombor', "FMCG butik uslubidagi do'kon"],
            'ru' => ['Небольшой магазин', 'Супермаркет', 'Гипермаркет', 'Dark store', 'Склад', 'FMCG-бутик'],
            'en' => ['Small shop', 'Supermarket', 'Hypermarket', 'Dark store', 'Warehouse', 'Boutique-style FMCG shop'],
        ];
    }
};
