<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionCheck extends Model
{
    public $timestamps = false;

    protected $fillable = ['transaction_id', 'user_id', 'role', 'action', 'comment', 'checked_at'];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
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
