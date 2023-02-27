<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @method static whereId($id)
 */
class Ville extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'region_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function quartiers(){
        return $this->hasMany(Quartier::class);
    }
    public function region(){
        return $this->belongsTo(Region::class)->with('pays');
    }
}
