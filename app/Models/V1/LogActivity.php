<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogActivity extends Model
{
    use HasFactory;
    public $table = "log_activity";

    protected $fillable = [

        'subject', 'url', 'method', 'ip', 'agent', 'user_id', 'response', 'user_name'

    ];
}
