<?php

namespace Lambda\Agent\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Customer extends Authenticatable implements JWTSubject
{
    protected $table = 'customer';
    use Notifiable;

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password'
    ];

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
}
