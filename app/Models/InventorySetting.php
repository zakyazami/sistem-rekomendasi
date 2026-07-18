<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'scope_key',
        'product_id',
        'lead_time_days',
        'review_period_days',
        'service_level',
        'prediction_horizon_days',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'service_level' => 'float',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
