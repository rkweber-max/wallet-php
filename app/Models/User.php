<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Override;

class User extends Authenticatable
{
    use HasUuids;

    public $incrementing = false;
    public $keyType = 'string';


    protected $fillable = [
        'name',
        'email',
        'password'
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'password'   => 'hashed'
    ];

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    public function uniqueIds(): array
    {
        return ['id'];
    }

    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }
}
