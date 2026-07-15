<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Step extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'variables_definition' => 'array', // Авто-парсинг JSON
        ];
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function outgoingTransitions(): HasMany
    {
        return $this->hasMany(Transition::class, 'from_step_id');
    }

    public function incomingTransitions(): HasMany
    {
        return $this->hasMany(Transition::class, 'to_step_id');
    }
}