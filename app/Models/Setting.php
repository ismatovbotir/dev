<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public const PLAN_EXAMPLES = 'plan_examples';

    public const PLAN_EXAMPLE_LIMIT = 10;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function read(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->first()?->value ?? $default;
    }

    public static function write(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * The sample plan images, keyed by id, each as ['path' => ..., 'name' => ...].
     *
     * @return array<int, array{path: string, name: string}>
     */
    public static function planExamples(): array
    {
        return collect(static::read(self::PLAN_EXAMPLES, []))
            ->filter(fn (mixed $example) => is_array($example) && isset($example['path']))
            ->mapWithKeys(fn (array $example, int|string $id) => [(int) $id => $example])
            ->sortKeys()
            ->all();
    }

    /**
     * Paths of the sample plan images that still exist on disk (at most one Telegram album), oldest first.
     *
     * @return list<string>
     */
    public static function planExamplePaths(): array
    {
        return collect(static::planExamples())
            ->pluck('path')
            ->filter(fn (string $path) => Storage::disk('local')->exists($path))
            ->take(self::PLAN_EXAMPLE_LIMIT)
            ->values()
            ->all();
    }
}
