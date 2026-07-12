<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;
    protected $fillable = [

        'category_id',

        'name',

        'moving_type',

        'minimum_stock',

        'description',

        'is_active'

    ];

    protected $casts = [

        'is_active' => 'boolean',

    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
