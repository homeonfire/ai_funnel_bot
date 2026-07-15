<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transition extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
        ];
    }

    public function fromStep(): BelongsTo
    {
        return $this->belongsTo(Step::class, 'from_step_id');
    }

    public function toStep(): BelongsTo
    {
        return $this->belongsTo(Step::class, 'to_step_id');
    }
}