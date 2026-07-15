<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bot extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'platform_settings' => 'array',
            'is_active' => 'boolean',
            'api_key' => 'encrypted', // Автоматическое шифрование в БД
            'tg_token' => 'encrypted', // Автоматическое шифрование в БД
            'webhook_status' => 'boolean',
        ];
    }

    public function funnels(): HasMany
    {
        return $this->hasMany(Funnel::class);
    }

    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }
}