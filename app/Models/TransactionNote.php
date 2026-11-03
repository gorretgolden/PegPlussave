<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionNote extends Model
{
    public $timestamps = false;

    protected $fillable = ['transaction_id', 'user_id', 'role', 'text', 'noted_at'];

    protected function casts(): array
    {
        return ['noted_at' => 'datetime'];
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
