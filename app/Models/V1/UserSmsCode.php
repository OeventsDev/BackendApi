<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSmsCode extends Model
{
    use HasFactory;
    public $table = "sms_verifications";
    /**
     * The attributes that are mass assignable.
     * @var array
     **/

    protected $fillable = [

        'user_id',
        'otp',
        'telephone',
        'expire_at',
        'status'
    ];
}
