<?php

namespace App\Models\V1;

// use Illuminate\Contracts\Auth\MustVerifyEmail;


use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
/**
 * @method static whereEmail($email)
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'telephone',
        'default_auth',
        'pays_id',
        'firebase_token',
        'password',
        'google_id',
        'facebook_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'telephone_verified_at' => 'datetime',
    ];


    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['user_extend_infos', 'children'];


    /**
     * Determine user role.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function userExtendInfos(): Attribute
    {
        return new Attribute(
            get: fn () => $this->getUserRoleAndPermissions($this->id),
        );
    }


    /**
     * Determine user role.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function children(): Attribute
    {
        return new Attribute(
            get: fn () => $this->getUserChildren($this->id),
        );
    }

}
