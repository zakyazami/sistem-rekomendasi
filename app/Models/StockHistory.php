<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockHistory extends Model
{
    /** @use HasFactory<\Database\Factories\StockHistoryFactory> */
    use HasFactory;
    protected $fillable=[
        'product_id',
        'date',
        'initial_stock',
        'incoming_stock',
        'outgoing_stock',
        'final_stock',
    ];

    protected $casts=[

        'date'=>'date'

    ];

    public function product():BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

}
