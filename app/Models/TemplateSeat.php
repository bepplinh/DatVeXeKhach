<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateSeat extends Model
{
    protected $fillable = [
        'seat_layout_template_id',
        'deck',
        'column_group',
        'index_in_column',
        'seat_label'
    ];

    public function template()
    {
        return $this->belongsTo(SeatLayoutTemplate::class, 'seat_layout_template_id');
    }
}
