<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    /**
     * Kolom yang boleh diisi secara massal.
     */
    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'description',
    ];

    /**
     * Mendefinisikan relasi bahwa setiap transaksi dimiliki oleh satu User.
     */
    public function user()
    {
        return $this->belongsTo(TelegramUser::class, 'user_id', 'id');
    }
}
