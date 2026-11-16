<?php

namespace App\Models;

use App\Enums\AuthEventType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class AuthEvent extends Model
{
    use HasUuids, MassPrunable;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => AuthEventType::class,
        ];
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->where('created_at', '<', now()->subDays((int) config('audit.retention_days')));
    }
}
