<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'type',
        'amount',
        'description',
        'created_at',
    ];

    /**
     * Tranzaksiya qaysi userga tegishli
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tranzaksiya qaysi kategoriya ostida
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
