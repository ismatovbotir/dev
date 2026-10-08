<?php

namespace App\Models;

use App\Enums\ConversationState;
use App\Enums\Language;
use Database\Factories\TelegramUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['chat_id', 'username', 'first_name', 'language', 'state', 'draft', 'profile', 'last_update_id'])]
class TelegramUser extends Model
{
    /** @use HasFactory<TelegramUserFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chat_id' => 'integer',
            'language' => Language::class,
            'state' => ConversationState::class,
            'draft' => 'array',
            'profile' => 'array',
        ];
    }

    /**
     * @return HasMany<ShopRequest, $this>
     */
    public function shopRequests(): HasMany
    {
        return $this->hasMany(ShopRequest::class);
    }

    /**
     * @return HasOne<ShopRequest, $this>
     */
    public function latestShopRequest(): HasOne
    {
        return $this->hasOne(ShopRequest::class)->latestOfMany();
    }

    public function locale(): string
    {
        return ($this->language ?? Language::Uz)->value;
    }
}
