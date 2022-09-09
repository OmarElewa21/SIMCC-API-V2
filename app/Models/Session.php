<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dyrynda\Database\Support\GeneratesUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Session extends BaseModel
{
    use SoftDeletes, GeneratesUuid;

    protected $fillable = [
        'name',
        'round_level_id',
        'is_default',
        'created_by',
        'updated_by',
        'deleted_at',
        'deleted_by',
    ];

    public function round_level()
    {
        return $this->belongsTo(RoundLevel::class);
    }

    public function participants()
    {
        return $this->hasMany(Participant::class);
    }
}
