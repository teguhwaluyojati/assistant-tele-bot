<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PoopTracker extends Model
{
    use HasFactory;
    protected $table = 'poop_tracker';

    protected $fillable = [
        'user_id',
        'type',
        'notes',
        'created_at',
        'updated_at',
    ];
}
