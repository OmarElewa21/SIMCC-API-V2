<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dyrynda\Database\Support\GeneratesUuid;

class Section extends BaseModel
{
    use SoftDeletes, GeneratesUuid;

    protected $fillable = [
        'collection_id',
        'number',
        'sort_randomly',
        'allow_skips',
        'description'
    ];

    public static function booted()
    {
        parent::booted();

        static::creating(function($q) {
            $q->created_by = auth()->id();
        });
    }

    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    public function tasks()
    {
        return $this->belongsToMany(Task::class);
    }
}
