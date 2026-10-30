<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre'])]
class Sede extends Model
{
    public $timestamps = false;

    public function areas()
    {
        return $this->hasMany(Area::class)->orderBy('nombre');
    }
}
