<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['ticket_id', 'user_id', 'estado', 'nota'])]
class TicketEvento extends Model
{
    const UPDATED_AT = null;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
