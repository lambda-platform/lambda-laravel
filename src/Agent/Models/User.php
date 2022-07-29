<?php

namespace Lambda\Agent\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements JWTSubject
{
//    protected $keyType = 'char';

    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public static $columns = [
        'id', 'login', 'status',
        'role', 'created_at', 'updated_at'
    ];
    protected $fillable = ['login', 'password', 'status', 'role', 'permissions'];

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
        if (config('lambda.jwt_claims')) {
            $claims = [];
            foreach (config('lambda.jwt_claims') as $c) {
                $claims[$c] = $this->{$c};
            }
            return $claims;
        }

        return [];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
}
