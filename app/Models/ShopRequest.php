<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Database\Factories\ShopRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'telegram_user_id', 'phone', 'name', 'location_text', 'latitude', 'longitude', 'brand',
    'answers', 'status', 'admin_comment', 'drawing_path', 'drawing_name',
    'answered_by', 'answered_at', 'delivered_at',
])]
class ShopRequest extends Model
{
    /** @use HasFactory<ShopRequestFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'answers' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'answered_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TelegramUser, $this>
     */
    public function telegramUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function mapUrl(): ?string
    {
        return $this->hasCoordinates()
            ? "https://www.google.com/maps?q={$this->latitude},{$this->longitude}"
            : null;
    }
}
