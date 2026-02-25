<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Transaction extends Model
{
    use HasFactory;

    protected $transaction = 'transactions';

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
        return $this->belongsTo(TelegramUser::class, 'user_id', 'user_id');
    }

    public function DailyExpenses(?int $userId = null)
    {
        $query = DB::table($this->transaction)
            ->select('*')
            ->where('type', 'expense')
            ->whereDate('created_at', now()->subDay()->toDateString());

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }
}
