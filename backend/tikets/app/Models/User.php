<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active'
    ];

    protected $hidden = [
        'password'
    ];

    public $timestamps = true;

    protected $attributes = [
        'is_active' => true,
    ];

    public function tokens()
    {
        return $this->hasMany(AuthToken::class, 'user_id');
    }
}
