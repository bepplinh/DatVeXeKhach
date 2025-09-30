<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DraftCheckoutItem extends Model
{
    use HasFactory;

    protected $table = 'draft_checkout_items';

    protected $fillable = [
        'draft_checkout_id',
        'seat_id',
        'price',
        'seat_label',
        'fare_id',
    ];

    public function draft()
    {
        return $this->belongsTo(DraftCheckout::class, 'draft_checkout_id');
    }
}
