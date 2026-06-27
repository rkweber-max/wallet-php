<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasUuids;

    public $incrementing = false;
    public $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'type',
        'amount',
        'balance_after',
        'wallet_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    public function uniqueIds(): array
    {
        return ['id'];
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
